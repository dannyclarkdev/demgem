<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\Show;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use Livewire\Livewire;

it('soft deletes a session and returns its secrets to the pool', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->create();
    $scene = Scene::factory()->inSession($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->call('delete')
        ->assertRedirect(route('sessions.index', $campaign));

    expect(GameSession::query()->count())->toBe(0)
        ->and(GameSession::withTrashed()->count())->toBe(1)
        ->and($secret->refresh()->game_session_id)->toBeNull()
        ->and($scene->refresh()->game_session_id)->toBe($session->id);
});

it('keeps a revealed secret pointing at the session that revealed it', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->revealedIn($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->call('delete');

    expect($secret->refresh()->revealed_in_session_id)->toBe($session->id)
        ->and($secret->revealed_at)->not->toBeNull();
});

it('stops a player from deleting a session', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->call('delete')
        ->assertForbidden();

    expect(GameSession::query()->count())->toBe(1);
});

it('drops a deleted session out of every list', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create(['title' => 'The Ashfall Road']);
    $session->delete();

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertDontSee('The Ashfall Road');

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.show', [$campaign, 1]))
        ->assertNotFound();
});
