<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use App\Models\User;
use App\Support\CurrentCampaign;

it('returns 404 for a session number that belongs to another campaign', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    GameSession::factory()->for($theirs)->number(1)->create(['title' => 'Their session']);

    $this->actingAs(ownerOf($mine))
        ->get(route('sessions.show', [$mine, 1]))
        ->assertNotFound();

    $this->actingAs(ownerOf($mine))
        ->get(route('sessions.edit', [$mine, 1]))
        ->assertNotFound();
});

it('keeps another campaign out of the sessions list', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    GameSession::factory()->for($mine)->number(1)->create(['title' => 'Mine alone']);
    GameSession::factory()->for($theirs)->number(1)->create(['title' => 'Theirs alone']);

    $this->actingAs(ownerOf($mine))
        ->get(route('sessions.index', $mine))
        ->assertOk()
        ->assertSee('Mine alone')
        ->assertDontSee('Theirs alone');
});

it('stops a non-member from reaching a session at all', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('sessions.index', $campaign))->assertNotFound();
    $this->actingAs($outsider)->get(route('sessions.show', [$campaign, 1]))->assertNotFound();
});

it('scopes sessions, scenes, and secrets to the campaign in context', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();

    $ours = GameSession::factory()->for($mine)->number(1)->create();
    Scene::factory()->inSession($ours)->create();
    Secret::factory()->for($mine)->preparedFor($ours)->create();

    $other = GameSession::factory()->for($theirs)->number(1)->create();
    Scene::factory()->inSession($other)->create();
    Secret::factory()->for($theirs)->preparedFor($other)->create();

    app(CurrentCampaign::class)->set($mine, CampaignRole::Owner);

    expect(GameSession::query()->count())->toBe(1)
        ->and(Scene::query()->count())->toBe(1)
        ->and(Secret::query()->count())->toBe(1);
});
