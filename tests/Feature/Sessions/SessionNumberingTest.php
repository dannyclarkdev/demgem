<?php

use App\Actions\Sessions\CreateSession;
use App\Livewire\Sessions\Form;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Livewire;

it('starts at one and counts up', function () {
    $campaign = Campaign::factory()->create();
    $action = app(CreateSession::class);
    $owner = ownerOf($campaign);

    expect($action->nextNumber($campaign))->toBe(1);

    $action->handle($campaign, $owner, []);

    expect($action->nextNumber($campaign))->toBe(2);
});

it('keeps counting past a trashed session so a restore never collides', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    $second = GameSession::factory()->for($campaign)->number(2)->create();

    $second->delete();

    expect(app(CreateSession::class)->nextNumber($campaign))->toBe(3);
});

it('numbers each campaign on its own', function () {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    GameSession::factory()->for($theirs)->number(9)->create();

    expect(app(CreateSession::class)->nextNumber($mine))->toBe(1);
});

it('rejects a number another session already uses', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(3)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('number', '3')
        ->call('save')
        ->assertHasErrors('number');

    expect($campaign->gameSessions()->count())->toBe(1);
});

it('rejects a number a trashed session still holds', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(4)->create()->delete();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('number', '4')
        ->call('save')
        ->assertHasErrors('number');
});

it('lets a session keep its own number while editing', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(5)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 5])
        ->set('title', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($session->refresh()->title)->toBe('Renamed');
});

it('retries once when another GM takes the number first', function () {
    $campaign = Campaign::factory()->create();
    $owner = ownerOf($campaign);
    $action = app(CreateSession::class);

    // Stand in for the race: another GM commits number 1 between the lookup and the insert.
    $raced = false;

    GameSession::creating(function () use ($campaign, &$raced): void {
        if ($raced) {
            return;
        }

        $raced = true;

        GameSession::withoutEvents(fn () => GameSession::factory()->for($campaign)->number(1)->create());
    });

    $session = $action->handle($campaign, $owner, []);

    expect($raced)->toBeTrue()
        ->and($session->number)->toBe(2)
        ->and($campaign->gameSessions()->count())->toBe(2);
});

it('never renumbers a session the GM numbered by hand', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(3)->create();

    expect(fn () => app(CreateSession::class)->handle($campaign, ownerOf($campaign), ['number' => 3]))
        ->toThrow(UniqueConstraintViolationException::class);
});
