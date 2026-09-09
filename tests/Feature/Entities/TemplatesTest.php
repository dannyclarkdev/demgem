<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Livewire\Entities\Templates;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\EntityTemplate;
use App\Support\CurrentCampaign;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('lets a GM manage reusable body templates', function () {
    $campaign = Campaign::factory()->create();
    $screen = Livewire::actingAs(ownerOf($campaign))->test(Templates::class, ['campaign' => $campaign])
        ->set('name', 'An NPC outline')->set('body', "## Wants\n\n    A code block\n")
        ->call('save')->assertHasNoErrors()->assertSee('An NPC outline');
    $template = EntityTemplate::query()->sole();
    expect($template->body)->toBe("## Wants\n\n    A code block\n");
    $screen->call('edit', $template->id)->set('name', 'A tavern outline')->set('type', 'location')
        ->call('save')->assertHasNoErrors()->assertSee('A tavern outline');
    expect($template->fresh()->type)->toBe(EntityType::Location);
    $screen->call('delete', $template->id)->assertHasNoErrors()->assertSee('No templates yet');
    expect(EntityTemplate::query()->count())->toBe(0);
});

it('requires explicit replacement of a draft and creates an independent copy', function () {
    $campaign = Campaign::factory()->create();
    $template = EntityTemplate::factory()->for($campaign)->create(['name' => 'NPC outline', 'body' => 'Starting prose']);
    $wrongType = EntityTemplate::factory()->for($campaign)->create(['type' => EntityType::Location, 'name' => 'Secret location outline']);
    $screen = Livewire::actingAs(ownerOf($campaign))->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->assertSee('NPC outline')->assertDontSee('Secret location outline')
        ->set('name', 'Mara')->set('body', 'Unsaved prose')->set('templateId', $template->id)
        ->assertSet('body', 'Unsaved prose')->call('useTemplate')->assertSet('confirmTemplate', true)
        ->assertSet('body', 'Unsaved prose')->call('useTemplate', true)->assertSet('body', 'Starting prose');
    $template->update(['body' => 'Later template']);
    $template->delete();
    $screen->call('save')->assertHasNoErrors();
    expect(Entity::query()->where('name', 'Mara')->sole()->body)->toBe('Starting prose');
});

it('keeps the draft when a selected template disappears or has the wrong type', function () {
    $campaign = Campaign::factory()->create();
    $template = EntityTemplate::factory()->for($campaign)->create(['type' => EntityType::Location]);
    Livewire::actingAs(ownerOf($campaign))->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('body', 'Keep this draft')->set('templateId', $template->id)->call('useTemplate', true)
        ->assertHasErrors('templateId')->assertSet('body', 'Keep this draft');
});

it('refuses invalid template input', function (string $field, mixed $value) {
    $campaign = Campaign::factory()->create();
    Livewire::actingAs(ownerOf($campaign))->test(Templates::class, ['campaign' => $campaign])
        ->set('name', 'Valid name')->set($field, $value)->call('save')->assertHasErrors($field);
    expect(EntityTemplate::query()->count())->toBe(0);
})->with([
    'blank name' => ['name', '   '],
    'long name' => ['name', str_repeat('n', 121)],
    'unknown type' => ['type', 'dragon'],
    'long body' => ['body', str_repeat('b', 100001)],
]);

it('keeps templates out of player pages and refuses their direct access', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $entity = Entity::factory()->for($campaign)->pcOf($player)->create();
    EntityTemplate::factory()->for($campaign)->create(['name' => 'Secret royal outline', 'body' => 'The prince is a ghost']);
    Livewire::actingAs($player)->test(Form::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => $entity->slug])
        ->assertDontSee('Secret royal outline')->assertDontSee('The prince is a ghost')->call('useTemplate')->assertForbidden();
    Livewire::actingAs($player)->test(Templates::class, ['campaign' => $campaign])->assertForbidden();
});

it('refuses foreign templates and rechecks removed membership on the next request', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $foreign = EntityTemplate::factory()->create();
    $screen = Livewire::actingAs($owner)->test(Templates::class, ['campaign' => $campaign]);
    $screen->call('edit', $foreign->id)->assertNotFound();
    app(CurrentCampaign::class)->clear();
    $screen = Livewire::actingAs($owner)->test(Templates::class, ['campaign' => $campaign]);
    $campaign->members()->where('user_id', $owner->id)->delete();
    $screen->call('save')->assertNotFound();
});

it('allows template management only for GM roles', function (CampaignRole $role, bool $allowed) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);
    $template = EntityTemplate::factory()->for($campaign)->create();
    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $template))->toBe($allowed);
    }
    expect(Gate::forUser($user)->allows('create', [EntityTemplate::class, $campaign]))->toBe($allowed);
})->with([
    [CampaignRole::CoGm, true],
    [CampaignRole::Player, false],
    [CampaignRole::Spectator, false],
]);

it('shows an unavailable message if the last template is deleted before it is applied', function () {
    $campaign = Campaign::factory()->create();
    $template = EntityTemplate::factory()->for($campaign)->create();
    $screen = Livewire::actingAs(ownerOf($campaign))->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('body', 'Keep my draft')->set('templateId', $template->id);
    $template->delete();
    $screen->call('useTemplate')->assertHasErrors('templateId')->assertSet('body', 'Keep my draft')
        ->assertSee('That template is no longer available for this entity type. Your draft has been kept.');
});
