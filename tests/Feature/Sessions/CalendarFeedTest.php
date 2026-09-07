<?php

use App\Enums\CampaignRole;
use App\Enums\Visibility;
use App\Livewire\Profile\Edit;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

function feedUrl(User $user): string
{
    return route('calendar.feed', ['token' => $user->calendarToken()]);
}

it('lists the sessions a player can see across every campaign, and nothing they cannot', function () {
    $player = User::factory()->create();
    $vell = Campaign::factory()->create(['name' => 'Vell']);
    $harrow = Campaign::factory()->create(['name' => 'Harrowgate']);
    memberOf($vell, CampaignRole::Player, $player);
    memberOf($harrow, CampaignRole::Player, $player);
    $elsewhere = Campaign::factory()->create(['name' => 'Elsewhere']);

    GameSession::factory()->for($vell)->number(1)->planned()->create(['title' => 'The cellar', 'scheduled_at' => now()->addDays(3)]);
    GameSession::factory()->for($harrow)->number(4)->planned()->create(['title' => 'The gatehouse', 'scheduled_at' => now()->addDays(5)]);
    GameSession::factory()->for($vell)->number(2)->planned()->create([
        'title' => 'The ambush', 'scheduled_at' => now()->addDays(10), 'visibility' => Visibility::Dm,
        'strong_start' => 'The duke is already dead', 'dm_notes' => 'They must not learn about the twin', 'recap' => 'Unpublished recap prose',
    ]);
    GameSession::factory()->for($vell)->number(3)->unscheduled()->create(['title' => 'No date at all']);
    GameSession::factory()->for($elsewhere)->number(1)->planned()->create(['title' => 'Somebody elses game', 'scheduled_at' => now()->addDays(3)]);

    $body = $this->get(feedUrl($player))
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8')
        ->getContent();

    expect($body)->toContain('Session 1: The cellar · Vell')
        ->and($body)->toContain('Session 4: The gatehouse · Harrowgate')
        ->and($body)->not->toContain('The ambush')
        ->and($body)->not->toContain('No date at all')
        ->and($body)->not->toContain('Somebody elses game')
        ->and($body)->not->toContain('already dead')
        ->and($body)->not->toContain('the twin')
        ->and($body)->not->toContain('Unpublished recap prose');
});

it('carries no session prose for a GM either, only the name and the link', function () {
    $campaign = Campaign::factory()->create(['name' => 'Vell']);
    $session = GameSession::factory()->for($campaign)->number(2)->planned()->create([
        'title' => 'The ambush', 'scheduled_at' => now()->addDays(10), 'visibility' => Visibility::Dm,
        'strong_start' => 'The duke is already dead', 'dm_notes' => 'They must not learn about the twin',
        'live_notes' => 'Live note prose', 'recap' => 'Recap prose',
    ]);

    $body = $this->get(feedUrl(ownerOf($campaign)))->assertOk()->getContent();

    expect($body)->toContain('Session 2: The ambush · Vell')
        ->and($body)->toContain($session->url())
        ->and($body)->not->toContain('already dead')
        ->and($body)->not->toContain('the twin')
        ->and($body)->not->toContain('Live note prose')
        ->and($body)->not->toContain('Recap prose');
});

it('marks a cancelled session cancelled and ends every event after the campaign length', function () {
    $campaign = Campaign::factory()->create(['session_length_minutes' => 180]);
    $player = memberOf($campaign, CampaignRole::Player);
    $start = now()->addDays(3)->setTime(19, 0)->setTimezone('UTC');

    GameSession::factory()->for($campaign)->number(1)->cancelled()->create(['scheduled_at' => $start]);

    $body = $this->get(feedUrl($player))->assertOk()->getContent();

    expect($body)->toContain("STATUS:CANCELLED\r\n")
        ->and($body)->toContain('DTSTART:'.$start->format('Ymd\THis\Z'))
        ->and($body)->toContain('DTEND:'.$start->copy()->addMinutes(180)->format('Ymd\THis\Z'));
});

it('keeps the same UID across fetches so a calendar updates rather than duplicates', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    GameSession::factory()->for($campaign)->number(1)->planned()->create(['scheduled_at' => now()->addDays(3)]);

    $first = $this->get(feedUrl($player))->getContent();
    $second = $this->get(feedUrl($player))->getContent();

    preg_match('/^UID:(.+)$/m', $first, $one);
    preg_match('/^UID:(.+)$/m', $second, $two);

    expect($one[1])->toBe($two[1])->and($one[1])->not->toBe('');
});

it('is a bare 404 for a token nobody holds, and throttled', function () {
    $this->get(route('calendar.feed', ['token' => str_repeat('a', 40)]))
        ->assertNotFound();

    $route = Route::getRoutes()->getByName('calendar.feed');

    expect($route->gatherMiddleware())->toContain('throttle:30,1')
        ->and($route->gatherMiddleware())->not->toContain('auth');
});

it('mints a token on first ask and a reset kills the old link', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    expect($player->calendar_token)->toBeNull();

    $token = $player->calendarToken();

    expect(strlen($token))->toBe(40)
        ->and($player->fresh()->calendar_token)->toBe($token)
        ->and($player->calendarToken())->toBe($token);

    Livewire::actingAs($player)
        ->test(Edit::class)
        ->assertSee(route('calendar.feed', ['token' => $token]))
        ->call('resetCalendarLink');

    $fresh = $player->fresh()->calendar_token;

    expect($fresh)->not->toBe($token);

    $this->get(route('calendar.feed', ['token' => $token]))->assertNotFound();
    $this->get(route('calendar.feed', ['token' => $fresh]))->assertOk();
});

it('offers a link on the profile before one exists', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->assertSee('Get a calendar link')
        ->call('getCalendarLink');

    expect($user->fresh()->calendar_token)->not->toBeNull();
});
