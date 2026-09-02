<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;

it('returns matching entities the viewer may see', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss']);
    Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'Mara the Drowned']);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Abbess Corvane']);

    $this->actingAs($player)
        ->getJson(route('entities.autocomplete', [$campaign, 'q' => 'mar']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Mara Voss')
        ->assertJsonPath('0.needsPrefix', false);

    $this->actingAs(ownerOf($campaign))
        ->getJson(route('entities.autocomplete', [$campaign, 'q' => 'mar']))
        ->assertJsonCount(2);
});

it('flags names that exist in more than one type', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->type(EntityType::Item)->forPlayers()->create(['name' => 'Raven', 'slug' => 'raven-item']);
    Entity::factory()->for($campaign)->type(EntityType::Character)->forPlayers()->create(['name' => 'Raven', 'slug' => 'raven']);

    $response = $this->actingAs(ownerOf($campaign))->getJson(route('entities.autocomplete', [$campaign, 'q' => 'raven']));

    $response->assertOk()->assertJsonCount(2);
    expect(collect($response->json())->every(fn ($row) => $row['needsPrefix'] === true))->toBeTrue();
});

it('returns 404 for a non-member', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('entities.autocomplete', [$campaign, 'q' => 'a']))
        ->assertNotFound();
});

it('caps results at ten', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->count(12)->sequence(fn ($s) => ['name' => 'Guard '.$s->index])->create();

    $this->actingAs(ownerOf($campaign))
        ->getJson(route('entities.autocomplete', [$campaign, 'q' => 'guard']))
        ->assertJsonCount(10);
});
