<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\GameSession;

it('hides a draft session from players everywhere', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);
    GameSession::factory()->for($campaign)->number(1)->hidden()->create(['title' => 'The Betrayal']);
    GameSession::factory()->for($campaign)->number(2)->create(['title' => 'The Ashfall Road']);

    $this->actingAs($user)
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSee('The Ashfall Road')
        ->assertDontSee('The Betrayal');

    $this->actingAs($user)
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertDontSee('The Betrayal');
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('shows a draft session to GM roles', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->hidden()->create(['title' => 'The Betrayal']);

    $this->actingAs(memberOf($campaign, $role))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSee('The Betrayal');

    $this->actingAs(memberOf($campaign, $role))
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk();
})->with(['owner' => CampaignRole::Owner, 'co_gm' => CampaignRole::CoGm]);

it('counts only visible sessions in the sidebar', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(1)->create();
    GameSession::factory()->for($campaign)->number(2)->hidden()->create();
    GameSession::factory()->for($campaign)->number(3)->hidden()->create();

    $this->actingAs($player)->get(route('campaigns.show', $campaign))->assertSeeInOrder(['Sessions', '1']);
    $this->actingAs(ownerOf($campaign))->get(route('campaigns.show', $campaign))->assertSeeInOrder(['Sessions', '3']);
});

it('never puts a GM-only field of a session in a player response', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(1)->create([
        'strong_start' => 'A bell rings and nobody opened the door.',
        'live_notes' => 'They bribed the guard for 40 gold.',
        'dm_notes' => 'The duke is lying.',
        'recap' => 'An unpublished draft recap.',
    ]);

    $response = $this->actingAs($player)->get(route('sessions.show', [$campaign, 1]))->assertOk();

    $response->assertDontSee('A bell rings')
        ->assertDontSee('bribed the guard')
        ->assertDontSee('The duke is lying')
        ->assertDontSee('unpublished draft recap');
});

it('shows a published recap to a player and hides an unpublished one', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(1)->published('They burned the bridge behind them.')->create();
    GameSession::factory()->for($campaign)->number(2)->withRecap('Still being written.')->create();

    $this->actingAs($player)
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertOk()
        ->assertSee('They burned the bridge behind them.');

    $this->actingAs($player)
        ->get(route('sessions.show', [$campaign, 2]))
        ->assertOk()
        ->assertDontSee('Still being written.');
});

it('keeps a published recap on a draft session away from players', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->published('Secret history.')->hidden()->create();

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertNotFound();

    $this->actingAs(ownerOf($campaign))
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('Secret history.');
});
