<?php

use App\Enums\CampaignRole;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Livewire\Sessions\Form;
use App\Models\Campaign;
use App\Models\GameSession;
use Livewire\Livewire;

it('lets a GM plan a session', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'America/New_York']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->assertSet('number', '1')
        ->set('title', 'The Ashfall Road')
        ->set('scheduled_at', '2026-09-10T19:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $session = $campaign->gameSessions()->sole();

    expect($session->number)->toBe(1)
        ->and($session->title)->toBe('The Ashfall Road')
        ->and($session->status)->toBe(SessionStatus::Planned)
        ->and($session->visibility)->toBe(Visibility::Players)
        ->and($session->created_by)->toBe(ownerOf($campaign)->id)
        ->and($session->scheduled_at->format('Y-m-d H:i'))->toBe('2026-09-10 23:00');
});

it('stores the time the GM typed in the campaign timezone', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'Europe/Berlin']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('scheduled_at', '2026-12-01T20:30')
        ->call('save')
        ->assertHasNoErrors();

    $session = $campaign->gameSessions()->sole();

    expect($session->scheduled_at->format('H:i'))->toBe('19:30')
        ->and($session->scheduledAtIn('Europe/Berlin')->format('H:i'))->toBe('20:30');
});

it('plans a session with no date yet', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('title', 'Whenever everyone is free')
        ->set('scheduled_at', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->gameSessions()->sole()->scheduled_at)->toBeNull();
});

it('opens the create page for a co-GM and forbids it for a player', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(memberOf($campaign, CampaignRole::CoGm))
        ->get(route('sessions.create', $campaign))
        ->assertOk()
        ->assertSeeLivewire(Form::class);

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.create', $campaign))
        ->assertForbidden();
});

it('rejects a session hidden behind a visibility it does not support', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('visibility', Visibility::Selected->value)
        ->call('save')
        ->assertHasErrors('visibility');

    expect(GameSession::count())->toBe(0);
});

it('rejects a negative number and one past the ceiling', function (string $number) {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('number', $number)
        ->call('save')
        ->assertHasErrors('number');

    expect(GameSession::count())->toBe(0);
})->with(['negative' => '-1', 'too big' => '10000', 'not a number' => 'four']);

it('allows session zero', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign])
        ->set('number', '0')
        ->set('title', 'Session zero')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->gameSessions()->sole()->number)->toBe(0);
});
