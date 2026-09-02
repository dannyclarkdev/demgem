<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Livewire\Entities\Show;
use App\Models\Campaign;
use App\Models\Entity;
use App\Rules\UniqueEntityName;
use Livewire\Livewire;

it('soft deletes and moves children up to the grandparent', function () {
    $campaign = Campaign::factory()->create();
    $region = Entity::factory()->for($campaign)->type(EntityType::Location)->create(['name' => 'Vell']);
    $city = Entity::factory()->childOf($region)->create(['name' => 'Harrowgate', 'slug' => 'harrowgate']);
    $district = Entity::factory()->childOf($city)->create(['name' => 'The Pilings']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'locations', 'slug' => 'harrowgate'])
        ->call('delete')
        ->assertRedirect(route('entities.index', [$campaign, 'locations']));

    $this->assertSoftDeleted($city);
    expect($district->fresh()->parent_id)->toBe($region->id);
});

it('forbids a player from deleting', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $entity = Entity::factory()->for($campaign)->forPlayers()->create(['slug' => 'mara-voss']);

    Livewire::actingAs($player)
        ->test(Show::class, ['campaign' => $campaign, 'type' => 'characters', 'slug' => 'mara-voss'])
        ->call('delete')
        ->assertForbidden();

    $this->assertNotSoftDeleted($entity);
});

it('hides a deleted entity from the index and returns 404 on its page', function () {
    $campaign = Campaign::factory()->create();
    $entity = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Gone Soon', 'slug' => 'gone-soon']);
    $entity->delete();

    $this->actingAs(ownerOf($campaign))->get(route('entities.index', [$campaign, 'characters']))->assertDontSee('Gone Soon');
    $this->actingAs(ownerOf($campaign))->get(route('entities.show', [$campaign, 'characters', 'gone-soon']))->assertNotFound();
});

it('frees the name for reuse after deletion', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->create(['name' => 'Mara Voss', 'slug' => 'mara-voss'])->delete();

    $rule = new UniqueEntityName($campaign->id, EntityType::Character);
    $failed = false;
    $rule->validate('name', 'Mara Voss', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
