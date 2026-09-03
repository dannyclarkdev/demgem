<?php

use App\Livewire\Sessions\Prep;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Secret;
use Livewire\Livewire;

it('records the session a secret came out in', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('revealSecret', $secret->id);

    $secret->refresh();

    expect($secret->isRevealed())->toBeTrue()
        ->and($secret->revealed_in_session_id)->toBe($session->id);
});

it('records the session that revealed it, not the one that prepped it', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    $second = GameSession::factory()->for($campaign)->number(2)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($first)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 2])
        ->call('revealSecret', $secret->id);

    $secret->refresh();

    expect($secret->game_session_id)->toBe($first->id)
        ->and($secret->revealed_in_session_id)->toBe($second->id);
});

it('undoes a reveal completely', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->revealedIn($session)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->call('unrevealSecret', $secret->id);

    $secret->refresh();

    expect($secret->revealed_at)->toBeNull()
        ->and($secret->revealed_in_session_id)->toBeNull();
});

it('moves a revealed secret out of the ready list and into the revealed one', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($session)->create(['body' => 'The ring is a forgery.']);

    $component = Livewire::actingAs(ownerOf($campaign))->test(Prep::class, ['campaign' => $campaign, 'number' => 1]);

    expect($component->viewData('secrets')->pluck('id')->all())->toBe([$secret->id])
        ->and($component->viewData('revealedSecrets')->count())->toBe(0);

    $component->call('revealSecret', $secret->id);

    expect($component->viewData('secrets')->count())->toBe(0)
        ->and($component->viewData('revealedSecrets')->pluck('id')->all())->toBe([$secret->id]);
});
