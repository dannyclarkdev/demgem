<?php

use App\Enums\CampaignRole;
use App\Enums\PrepRole;
use App\Livewire\Sessions\Prep;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use Livewire\Livewire;

it('counts what is prepped and what is still empty', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create(['strong_start' => 'A bell rings.']);
    Scene::factory()->inSession($session)->count(2)->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren']);
    $inn = Entity::factory()->for($campaign)->create(['name' => 'The Grey Lantern']);
    $session->entities()->attach($inn->id, ['role' => PrepRole::Location->value, 'position' => 0]);

    $steps = collect(Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->viewData('checklist'))
        ->keyBy('label');

    expect($steps['Review the party']['done'])->toBeTrue()
        ->and($steps['Review the party']['count'])->toBe(1)
        ->and($steps['Write a strong start']['done'])->toBeTrue()
        ->and($steps['Outline scenes']['count'])->toBe(2)
        ->and($steps['Pick locations']['done'])->toBeTrue()
        ->and($steps['Pick npcs']['done'])->toBeFalse()
        ->and($steps['Pick treasure']['count'])->toBe(0);
});

it('starts every step unticked on a fresh session', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create(['strong_start' => null]);

    $steps = Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->viewData('checklist');

    expect(collect($steps)->pluck('done')->filter()->count())->toBe(0)
        ->and($steps)->toHaveCount(7);
});

it('lists the party with the player behind each character', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->pcOf($player)->create(['name' => 'Wren']);
    Entity::factory()->for($campaign)->create(['name' => 'Some NPC']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.prep', [$campaign, 1]))
        ->assertOk()
        ->assertSeeInOrder(['The party', 'Wren', $player->name]);
});
