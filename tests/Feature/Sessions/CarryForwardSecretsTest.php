<?php

use App\Livewire\Sessions\Prep;
use App\Livewire\Sessions\Show;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Secret;
use Livewire\Livewire;

it('waits for the GM in the next session when it was never revealed', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    GameSession::factory()->for($campaign)->number(2)->create();

    Secret::factory()->for($campaign)->preparedFor($first)->create(['body' => 'Never came up']);
    Secret::factory()->for($campaign)->preparedFor($first)->revealedIn($first)->create(['body' => 'Already out']);

    $carried = Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 2])
        ->viewData('carriedSecrets');

    expect($carried->pluck('body')->all())->toBe(['Never came up']);
});

it('offers pooled secrets to every session', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    Secret::factory()->for($campaign)->pooled()->create(['body' => 'Written for the campaign']);

    $carried = Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->viewData('carriedSecrets');

    expect($carried->pluck('body')->all())->toBe(['Written for the campaign']);
});

it('never carries a secret backwards from a later session', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    $sixth = GameSession::factory()->for($campaign)->number(6)->create();
    Secret::factory()->for($campaign)->preparedFor($sixth)->create(['body' => 'Saved for later']);

    $carried = Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 1])
        ->viewData('carriedSecrets');

    expect($carried)->toHaveCount(0);
});

it('pins a carried secret to this session at the end of the list', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    $second = GameSession::factory()->for($campaign)->number(2)->create();

    $waiting = Secret::factory()->for($campaign)->preparedFor($first)->create(['body' => 'Left over']);
    Secret::factory()->for($campaign)->preparedFor($second, 0)->create(['body' => 'Written today']);

    $component = Livewire::actingAs(ownerOf($campaign))->test(Prep::class, ['campaign' => $campaign, 'number' => 2]);

    $component->call('carrySecretForward', $waiting->id);

    $waiting->refresh();

    expect($waiting->game_session_id)->toBe($second->id)
        ->and($waiting->position)->toBe(1)
        ->and($component->viewData('secrets')->pluck('body')->all())->toBe(['Written today', 'Left over'])
        ->and($component->viewData('carriedSecrets'))->toHaveCount(0);
});

it('returns the secrets of a deleted session to the pool, where the next session finds them', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    GameSession::factory()->for($campaign)->number(2)->create();
    $secret = Secret::factory()->for($campaign)->preparedFor($first)->create(['body' => 'Still true']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign, 'number' => 1])
        ->call('delete');

    expect($secret->refresh()->game_session_id)->toBeNull();

    $carried = Livewire::actingAs(ownerOf($campaign))
        ->test(Prep::class, ['campaign' => $campaign, 'number' => 2])
        ->viewData('carriedSecrets');

    expect($carried->pluck('body')->all())->toBe(['Still true']);
});

it('shows carried secrets on the prep screen with a way to pull them in', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    GameSession::factory()->for($campaign)->number(2)->create();
    Secret::factory()->for($campaign)->preparedFor($first)->create(['body' => 'The bridge toll is a lie']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.prep', [$campaign, 2]))
        ->assertOk()
        ->assertSeeInOrder(['Carried over', 'The bridge toll is a lie', 'Pull in']);
});

it('leaves a carried secret alone in another campaign', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    GameSession::factory()->for($mine)->number(2)->create();
    $theirFirst = GameSession::factory()->for($theirs)->number(1)->create();
    Secret::factory()->for($theirs)->preparedFor($theirFirst)->create(['body' => 'Their secret']);

    $carried = Livewire::actingAs(ownerOf($mine))
        ->test(Prep::class, ['campaign' => $mine, 'number' => 2])
        ->viewData('carriedSecrets');

    expect($carried)->toHaveCount(0);
});
