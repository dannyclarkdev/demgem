<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Models\Campaign;
use App\Models\Entity;

it('shows the visible ancestors in the breadcrumb and omits hidden ones', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $region = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Vell']);
    $secretDistrict = Entity::factory()->childOf($region)->dmOnly()->create(['name' => 'Undercity']);
    $room = Entity::factory()->childOf($secretDistrict)->forPlayers()->create(['name' => 'Throne Room', 'slug' => 'throne-room']);

    $this->actingAs($player)
        ->get($room->url())
        ->assertOk()
        ->assertSee('Vell')
        ->assertDontSee('Undercity');

    $this->actingAs(ownerOf($campaign))
        ->get($room->url())
        ->assertSeeInOrder(['Vell', 'Undercity', 'Throne Room']);
});

it('lists only visible children', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $region = Entity::factory()->for($campaign)->type(EntityType::Location)->forPlayers()->create(['name' => 'Vell', 'slug' => 'vell']);
    Entity::factory()->childOf($region)->forPlayers()->create(['name' => 'Harrowgate']);
    Entity::factory()->childOf($region)->dmOnly()->create(['name' => 'Undercity']);

    $this->actingAs($player)
        ->get($region->url())
        ->assertSee('Harrowgate')
        ->assertDontSee('Undercity');
});

it('shows the parent name on the index only when the viewer can see the parent', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $secret = Entity::factory()->for($campaign)->type(EntityType::Location)->dmOnly()->create(['name' => 'Undercity Hub']);
    Entity::factory()->childOf($secret)->forPlayers()->create(['name' => 'Flooded Stair']);

    $this->actingAs($player)
        ->get(route('entities.index', [$campaign, 'locations']))
        ->assertSee('Flooded Stair')
        ->assertDontSee('Undercity Hub');
});

it('survives a parent loop without hanging', function () {
    $campaign = Campaign::factory()->create();
    $a = Entity::factory()->for($campaign)->type(EntityType::Location)->create();
    $b = Entity::factory()->childOf($a)->create();
    $a->forceFill(['parent_id' => $b->id])->save();

    expect($b->ancestors()->count())->toBeLessThanOrEqual(2);
});
