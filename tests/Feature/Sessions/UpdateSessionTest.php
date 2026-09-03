<?php

use App\Enums\CampaignRole;
use App\Enums\SessionStatus;
use App\Enums\Visibility;
use App\Livewire\Sessions\Form;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('lets a GM change the number, title, date, status, and visibility', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'America/New_York']);
    $session = GameSession::factory()->for($campaign)->number(2)->create(['title' => 'Old title']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 2])
        ->assertSet('title', 'Old title')
        ->set('number', '3')
        ->set('title', 'The Ashfall Road')
        ->set('scheduled_at', '2026-10-01T18:30')
        ->set('status', SessionStatus::Played->value)
        ->set('visibility', Visibility::Dm->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $session->refresh();

    expect($session->number)->toBe(3)
        ->and($session->title)->toBe('The Ashfall Road')
        ->and($session->status)->toBe(SessionStatus::Played)
        ->and($session->visibility)->toBe(Visibility::Dm)
        ->and($session->scheduledAtIn('America/New_York')->format('Y-m-d H:i'))->toBe('2026-10-01 18:30')
        ->and($session->updated_by)->toBe(ownerOf($campaign)->id);
});

it('shows the stored time back in the campaign timezone', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'Australia/Sydney']);
    $session = GameSession::factory()->for($campaign)->number(1)->create([
        'scheduled_at' => Carbon::parse('2026-09-10 09:00:00', 'UTC'),
    ]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => $session->number])
        ->assertSet('scheduled_at', '2026-09-10T19:00');
});

it('takes any status in either direction, because a table can end early', function (SessionStatus $from, SessionStatus $to) {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create(['status' => $from]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 1])
        ->set('status', $to->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($session->refresh()->status)->toBe($to);
})->with([
    'planned to played' => [SessionStatus::Planned, SessionStatus::Played],
    'played back to planned' => [SessionStatus::Played, SessionStatus::Planned],
    'cancelled back to planned' => [SessionStatus::Cancelled, SessionStatus::Planned],
    'played to cancelled' => [SessionStatus::Played, SessionStatus::Cancelled],
]);

it('keeps players off the edit form', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(1)->create();

    $this->actingAs(memberOf($campaign, $role))
        ->get(route('sessions.edit', [$campaign, $session->number]))
        ->assertForbidden();
})->with(['player' => CampaignRole::Player, 'spectator' => CampaignRole::Spectator]);

it('returns 404 when the session number does not exist', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.edit', [$campaign, 99]))
        ->assertNotFound();
});
