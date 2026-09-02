<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Form;
use App\Models\Campaign;
use App\Models\Entity;
use Livewire\Livewire;

it('renames an entity and regenerates its slug', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Old Town', 'slug' => 'old-town']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'locations', 'slug' => 'old-town'])
        ->set('name', 'Harrowgate')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('entities.show', [$campaign, 'locations', 'harrowgate']));

    expect($entity->fresh())->name->toBe('Harrowgate')->slug->toBe('harrowgate')
        ->and($entity->fresh()->updated_by)->toBe(ownerOf($campaign)->id);
});

it('keeps the slug when the name does not change', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->type(EntityType::Note)->create(['name' => 'Rumors', 'slug' => 'rumors']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'notes', 'slug' => 'rumors'])
        ->set('body', 'New rumors.')
        ->call('save')
        ->assertHasNoErrors();

    expect($entity->fresh())->slug->toBe('rumors')->body->toBe('New rumors.');
});

it('nests under a parent of the same type', function () {
    $campaign = Campaign::factory()->create();
    $vell = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Vell']);
    $city = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Harrowgate', 'slug' => 'harrowgate']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'locations', 'slug' => 'harrowgate'])
        ->set('parent_id', $vell->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($city->fresh()->parent_id)->toBe($vell->id);
});

it('rejects a parent of another type or another campaign', function (string $kind) {
    $campaign = Campaign::factory()->create();
    $city = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['slug' => 'harrowgate']);
    $parent = match ($kind) {
        'faction' => Entity::factory()->for($campaign)->type(EntityType::Faction)->create(),
        'other-campaign' => Entity::factory()->type(EntityType::Location)->create(),
    };

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'locations', 'slug' => 'harrowgate'])
        ->set('parent_id', $parent->id)
        ->call('save')
        ->assertHasErrors(['parent_id']);

    expect($city->fresh()->parent_id)->toBeNull();
})->with(['faction', 'other-campaign']);

it('rejects itself as parent', function () {
    $campaign = Campaign::factory()->create();
    $city = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['slug' => 'harrowgate']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'type' => 'locations', 'slug' => 'harrowgate'])
        ->set('parent_id', $city->id)
        ->call('save')
        ->assertHasErrors(['parent_id']);
});

it('forbids a player from editing an NPC they can see', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->create(['slug' => 'mara-voss']);

    $this->actingAs($player)
        ->get(route('entities.edit', [$campaign, 'characters', 'mara-voss']))
        ->assertForbidden();
});

it('returns 404 when a player tries to edit a hidden entity', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->dmOnly()->create(['slug' => 'the-duke']);

    $this->actingAs($player)
        ->get(route('entities.edit', [$campaign, 'characters', 'the-duke']))
        ->assertNotFound();
});
