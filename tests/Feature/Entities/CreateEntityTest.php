<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('lets a DM create every entity type', function (EntityType $type) {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => $type->slug()])
        ->set('name', 'The Salt Cathedral')
        ->set('body', 'Half drowned. Always cold.')
        ->set('visibility', Visibility::Players->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $entity = $campaign->entities()->firstOrFail();

    expect($entity->type)->toBe($type)
        ->and($entity->slug)->toBe('the-salt-cathedral')
        ->and($entity->visibility)->toBe(Visibility::Players)
        ->and($entity->created_by)->toBe(ownerOf($campaign)->id);
})->with(fn () => collect(EntityType::cases())->mapWithKeys(fn ($t) => [$t->value => $t])->all());

it('renders the create page for a co-GM and forbids it for a player', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $player = memberOf($campaign, CampaignRole::Player);

    $this->actingAs($coGm)->get(route('entities.create', [$campaign, 'locations']))->assertOk()->assertSeeLivewire(Form::class);
    $this->actingAs($player)->get(route('entities.create', [$campaign, 'locations']))->assertForbidden();
});

it('returns 404 for an unknown entity type segment', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))->get("/campaigns/{$campaign->id}/dragons")->assertNotFound();
});

it('requires a name', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes'])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect(Entity::count())->toBe(0);
});

it('rejects a duplicate name of the same type, case-insensitively', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Harrowgate']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'locations'])
        ->set('name', 'harrowgate')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('allows the same name on a different type and gives the slug a suffix', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Raven', 'slug' => 'raven']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'items'])
        ->set('name', 'Raven')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->entities()->where('type', EntityType::Item->value)->firstOrFail()->slug)->toBe('raven-2');
});

it('does not produce a slug that collides with a route segment', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes'])
        ->set('name', 'Create')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->entities()->firstOrFail()->slug)->toBe('create-2');
});

it('syncs tags and selected viewers', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $other = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('tags', 'ally, Harbor , ally')
        ->set('visibility', Visibility::Selected->value)
        ->set('viewer_ids', [$player->id])
        ->call('save')
        ->assertHasNoErrors();

    $entity = $campaign->entities()->firstOrFail();

    expect($entity->tags->pluck('name')->sort()->values()->all())->toBe(['Harbor', 'ally'])
        ->and($entity->viewers->pluck('id')->all())->toBe([$player->id])
        ->and($entity->isVisibleTo($other, CampaignRole::Player))->toBeFalse();
});

it('rejects a viewer who is not a member of the campaign', function () {
    $campaign = Campaign::factory()->create();
    $stranger = memberOf(Campaign::factory()->create(), CampaignRole::Player);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'characters'])
        ->set('name', 'Mara Voss')
        ->set('visibility', Visibility::Selected->value)
        ->set('viewer_ids', [$stranger->id])
        ->call('save')
        ->assertHasErrors(['viewer_ids.0']);
});

it('prefills the name from the query string', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.create', [$campaign, 'locations', 'name' => 'Harrowgate']))
        ->assertOk()
        ->assertSee('Harrowgate');
});
