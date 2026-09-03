<?php

use App\Enums\CampaignRole;
use App\Enums\SessionStatus;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\Secret;
use Illuminate\Support\Carbon;

it('shows every session to GM roles and only player-visible ones to the rest', function (CampaignRole $role, int $expected) {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->create();
    GameSession::factory()->for($campaign)->number(2)->hidden()->create();

    expect(GameSession::query()->visibleTo($role)->count())->toBe($expected);
})->with([
    'owner' => [CampaignRole::Owner, 2],
    'co_gm' => [CampaignRole::CoGm, 2],
    'player' => [CampaignRole::Player, 1],
    'spectator' => [CampaignRole::Spectator, 1],
]);

it('answers the row check the same way the scope does', function () {
    $hidden = GameSession::factory()->hidden()->create();
    $shown = GameSession::factory()->create();

    expect($hidden->isVisibleTo(CampaignRole::Owner))->toBeTrue()
        ->and($hidden->isVisibleTo(CampaignRole::Player))->toBeFalse()
        ->and($shown->isVisibleTo(CampaignRole::Player))->toBeTrue();
});

it('hides an unpublished recap from players and shows it to GMs', function () {
    $session = GameSession::factory()->withRecap()->create();

    expect($session->hasPublishedRecap())->toBeFalse()
        ->and($session->isRecapVisibleTo(CampaignRole::Owner))->toBeTrue()
        ->and($session->isRecapVisibleTo(CampaignRole::Player))->toBeFalse();
});

it('shows a published recap to players only on a session they can see', function () {
    $open = GameSession::factory()->published()->create();
    $draft = GameSession::factory()->published()->hidden()->create();

    expect($open->isRecapVisibleTo(CampaignRole::Player))->toBeTrue()
        ->and($draft->isRecapVisibleTo(CampaignRole::Player))->toBeFalse()
        ->and($draft->isRecapVisibleTo(CampaignRole::CoGm))->toBeTrue();
});

it('flags a played session with no published recap', function () {
    expect(GameSession::factory()->withRecap()->create()->needsRecap())->toBeTrue()
        ->and(GameSession::factory()->published()->create()->needsRecap())->toBeFalse()
        ->and(GameSession::factory()->planned()->create()->needsRecap())->toBeFalse();
});

it('flags a planned session whose date has passed', function () {
    expect(GameSession::factory()->overdue()->create()->isOverdue())->toBeTrue()
        ->and(GameSession::factory()->planned()->create()->isOverdue())->toBeFalse()
        ->and(GameSession::factory()->unscheduled()->create()->isOverdue())->toBeFalse()
        ->and(GameSession::factory()->played()->create()->isOverdue())->toBeFalse();
});

it('falls back to the session number when there is no title', function () {
    $titled = GameSession::factory()->number(4)->create(['title' => 'The Ashfall Road']);
    $bare = GameSession::factory()->number(5)->create(['title' => null]);

    expect($titled->displayTitle())->toBe('The Ashfall Road')
        ->and($bare->displayTitle())->toBe('Session 5')
        ->and($titled->label())->toBe('Session 4');
});

it('reads the scheduled time in the campaign timezone', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'America/New_York']);
    $session = GameSession::factory()->for($campaign)->create([
        'scheduled_at' => Carbon::parse('2026-09-10 23:00:00', 'UTC'),
    ]);

    expect($session->scheduledAtIn($campaign->timezone)->format('Y-m-d H:i'))->toBe('2026-09-10 19:00')
        ->and($session->scheduled_at->format('Y-m-d H:i'))->toBe('2026-09-10 23:00');
});

it('defaults a campaign to UTC', function () {
    expect(Campaign::factory()->create()->timezone)->toBe('UTC');
});

it('carries unrevealed secrets forward from the pool and from earlier sessions only', function () {
    $campaign = Campaign::factory()->create();
    $first = GameSession::factory()->for($campaign)->number(1)->create();
    $second = GameSession::factory()->for($campaign)->number(2)->create();
    $third = GameSession::factory()->for($campaign)->number(3)->create();

    Secret::factory()->for($campaign)->pooled()->create(['body' => 'From the pool']);
    Secret::factory()->for($campaign)->preparedFor($first)->create(['body' => 'Left over']);
    Secret::factory()->for($campaign)->preparedFor($first)->revealedIn($first)->create(['body' => 'Already out']);
    Secret::factory()->for($campaign)->preparedFor($second)->create(['body' => 'For tonight']);
    Secret::factory()->for($campaign)->preparedFor($third)->create(['body' => 'For later']);

    $carried = Secret::query()->carriedInto($second)->pluck('body');

    expect($carried)->toHaveCount(2)
        ->and($carried)->toContain('From the pool')
        ->and($carried)->toContain('Left over');
});

it('keeps the number of a trashed session', function () {
    $campaign = Campaign::factory()->create();
    $session = GameSession::factory()->for($campaign)->number(7)->create();

    $session->delete();

    expect(GameSession::withTrashed()->max('number'))->toBe(7)
        ->and(GameSession::query()->where('number', 7)->exists())->toBeFalse();
});

it('casts status and visibility to enums', function () {
    $session = GameSession::factory()->cancelled()->create();

    expect($session->status)->toBe(SessionStatus::Cancelled)
        ->and($session->refresh()->status)->toBe(SessionStatus::Cancelled);
});
