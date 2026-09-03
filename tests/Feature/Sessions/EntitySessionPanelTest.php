<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;

it('lists the sessions that mention an entity, newest first', function () {
    $campaign = Campaign::factory()->create();
    $mara = Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    GameSession::factory()->for($campaign)->number(1)->create(['recap' => 'We met [[Mara Voss]].', 'title' => 'First night']);
    GameSession::factory()->for($campaign)->number(4)->create(['strong_start' => '[[Mara Voss]] is waiting.', 'title' => 'Fourth night']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertSeeInOrder(['Appears in sessions', 'Session 4', 'Session 1']);
});

it('finds an entity mentioned only in a scene note, for GMs', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    $session = GameSession::factory()->for($campaign)->number(2)->create(['title' => 'The toll']);
    Scene::factory()->inSession($session)->withNotes('[[Mara Voss]] blocks the bridge.')->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertSeeInOrder(['Appears in sessions', 'Session 2']);
});

it('never leads a player to a session through a scene note or GM notes', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);

    $viaScene = GameSession::factory()->for($campaign)->number(2)->create(['title' => 'Scene only']);
    Scene::factory()->inSession($viaScene)->withNotes('[[Mara Voss]] blocks the bridge.')->create();

    GameSession::factory()->for($campaign)->number(3)->create([
        'title' => 'Notes only',
        'dm_notes' => '[[Mara Voss]] is the traitor.',
        'strong_start' => '[[Mara Voss]] is waiting.',
        'live_notes' => 'They asked about [[Mara Voss]].',
    ]);

    $this->actingAs($player)
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertDontSee('Appears in sessions')
        ->assertDontSee('Scene only')
        ->assertDontSee('Notes only');
});

it('leads a player to a session only through a published recap', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);

    GameSession::factory()->for($campaign)->number(1)->create([
        'title' => 'Draft recap',
        'recap' => 'We met [[Mara Voss]].',
    ]);
    GameSession::factory()->for($campaign)->number(2)->published('We met [[Mara Voss]] again.')->create(['title' => 'Published recap']);

    $this->actingAs($player)
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertSee('Appears in sessions')
        ->assertSee('Session 2')
        ->assertDontSee('Session 1');
});

it('keeps a hidden session out of the panel even with a published recap', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    GameSession::factory()->for($campaign)->number(9)->published('We met [[Mara Voss]].')->hidden()->create();

    $this->actingAs($player)
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertDontSee('Appears in sessions');
});

it('drops a session out of the panel when its mention goes away', function () {
    $campaign = Campaign::factory()->create();
    Entity::factory()->for($campaign)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    $session = GameSession::factory()->for($campaign)->number(1)->create(['recap' => 'We met [[Mara Voss]].']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertSee('Appears in sessions');

    $session->update(['recap' => 'We met nobody.']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('entities.show', [$campaign, 'characters', 'mara-voss']))
        ->assertDontSee('Appears in sessions');
});

it('shows no sessions from another campaign', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    Entity::factory()->for($mine)->forPlayers()->create(['name' => 'Mara Voss', 'slug' => 'mara-voss']);
    GameSession::factory()->for($theirs)->number(1)->create(['recap' => 'We met [[Mara Voss]].']);

    $this->actingAs(ownerOf($mine))
        ->get(route('entities.show', [$mine, 'characters', 'mara-voss']))
        ->assertOk()
        ->assertDontSee('Appears in sessions');
});
