<?php

use App\Enums\CampaignRole;
use App\Enums\PrepRole;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use App\Models\User;

function apiPreppedSession(Campaign $campaign, int $number = 12): GameSession
{
    $session = GameSession::factory()->for($campaign)->number($number)->withRecap('They burned the bridge behind them.')->withPrep()->create([
        'title' => 'The Salt Cathedral',
        'live_notes' => 'Mara lied about the key.',
    ]);
    Scene::factory()->inSession($session)->withNotes('The duke watches from the gallery.')->create(['title' => 'The gallery']);
    Secret::factory()->preparedFor($session)->create(['body' => 'The duke has a twin.']);
    $duke = Entity::factory()->for($campaign)->dmOnly()->create(['name' => 'The Drowned Duke']);
    $session->entities()->attach($duke->id, ['role' => PrepRole::Npc->value, 'position' => 0]);

    return $session;
}

it('lists the sessions the key holder may see', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(1)->planned()->withPrep()->create(['title' => 'The Salt Cathedral']);
    GameSession::factory()->for($campaign)->number(2)->hidden()->create(['title' => 'The one they must not see']);

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/sessions")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', 1)
        ->assertJsonPath('data.0.label', 'Session 1')
        ->assertJsonPath('data.0.title', 'The Salt Cathedral')
        ->assertJsonMissingPath('data.0.strong_start')
        ->assertJsonMissingPath('data.0.visibility');

    asKey(ownerOf($campaign))
        ->getJson("/api/v1/campaigns/{$campaign->id}/sessions")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.strong_start', 'The bell above the tavern door rings, and nobody opened it.')
        ->assertJsonPath('data.1.visibility', 'dm');
});

it('gives a player the schedule, and the recap only once it is published', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $session = apiPreppedSession($campaign);

    $response = asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/sessions/12")
        ->assertOk()
        ->assertJsonPath('data.number', 12)
        ->assertJsonPath('data.status', 'played')
        ->assertJsonPath('data.recap', null)
        ->assertJsonPath('data.recap_published_at', null)
        ->assertJsonPath('data.url', $session->url());

    foreach (['strong_start', 'live_notes', 'dm_notes', 'scenes', 'secrets', 'prepped', 'visibility'] as $key) {
        $response->assertJsonMissingPath("data.{$key}");
    }

    foreach (['burned the bridge', 'nobody opened it', 'Mara lied', 'off screen', 'from the gallery', 'has a twin', 'Drowned Duke'] as $prose) {
        expect($response->getContent())->not->toContain($prose);
    }

    $session->update(['recap_published_at' => now()]);

    asKey($player)
        ->getJson("/api/v1/campaigns/{$campaign->id}/sessions/12")
        ->assertOk()
        ->assertJsonPath('data.recap', 'They burned the bridge behind them.')
        ->assertJsonMissingPath('data.scenes');
});

it('gives a GM everything, including the draft recap, the scenes, the secrets, and the prep', function () {
    $campaign = Campaign::factory()->create();
    apiPreppedSession($campaign);

    asKey(ownerOf($campaign))
        ->getJson("/api/v1/campaigns/{$campaign->id}/sessions/12")
        ->assertOk()
        ->assertJsonPath('data.recap', 'They burned the bridge behind them.')
        ->assertJsonPath('data.recap_published_at', null)
        ->assertJsonPath('data.visibility', Visibility::Players->value)
        ->assertJsonPath('data.strong_start', 'The bell above the tavern door rings, and nobody opened it.')
        ->assertJsonPath('data.live_notes', 'Mara lied about the key.')
        ->assertJsonPath('data.dm_notes', 'Keep the duke off screen tonight.')
        ->assertJsonPath('data.scenes.0.title', 'The gallery')
        ->assertJsonPath('data.scenes.0.notes', 'The duke watches from the gallery.')
        ->assertJsonPath('data.secrets.0.body', 'The duke has a twin.')
        ->assertJsonPath('data.secrets.0.revealed_at', null)
        ->assertJsonPath('data.prepped.0.name', 'The Drowned Duke')
        ->assertJsonPath('data.prepped.0.role', 'npc');
});

it('is a 404 for a hidden session, a missing number, and a non-member, and a 401 without a key', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(3)->hidden()->create();

    asKey($player)->getJson("/api/v1/campaigns/{$campaign->id}/sessions/3")->assertNotFound();
    asKey($player)->getJson("/api/v1/campaigns/{$campaign->id}/sessions/99")->assertNotFound();
    asKey(User::factory()->create())->getJson("/api/v1/campaigns/{$campaign->id}/sessions")->assertNotFound();
    withoutKey()->getJson("/api/v1/campaigns/{$campaign->id}/sessions")->assertUnauthorized();
});
