<?php

use App\Actions\Sessions\SendSessionReminders;
use App\Enums\CampaignRole;
use App\Enums\Rsvp;
use App\Enums\Visibility;
use App\Livewire\Campaigns\Members;
use App\Livewire\Campaigns\Settings;
use App\Mail\SessionReminder;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\SessionRsvp;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 12:00:00');
    Mail::fake();
});

function remindingCampaign(int $leadHours = 24): Campaign
{
    return Campaign::factory()->create([
        'name' => 'Vell',
        'timezone' => 'Europe/London',
        'reminder_lead_hours' => $leadHours,
    ]);
}

function sessionIn(Campaign $campaign, int $hours, array $attributes = []): GameSession
{
    return GameSession::factory()->for($campaign)->number(12)->planned()->create([
        'title' => 'The Salt Cathedral',
        'scheduled_at' => now()->addHours($hours),
        ...$attributes,
    ]);
}

function sentTo(): array
{
    $emails = [];

    Mail::assertQueued(SessionReminder::class, function (SessionReminder $mail) use (&$emails) {
        foreach ($mail->to as $recipient) {
            $emails[] = $recipient['address'];
        }

        return true;
    });

    sort($emails);

    return $emails;
}

it('mails everyone inside the window except the no and the one who turned it off', function () {
    $campaign = remindingCampaign(24);
    $session = sessionIn($campaign, 20);
    $owner = ownerOf($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['email' => 'tobin@example.com']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['email' => 'mara@example.com']));
    $ren = memberOf($campaign, CampaignRole::Spectator, User::factory()->create(['email' => 'ren@example.com']));
    $quiet = memberOf($campaign, CampaignRole::Player, User::factory()->create(['email' => 'quiet@example.com']));

    SessionRsvp::factory()->forSession($session, $mara)->saying(Rsvp::No)->create();
    $campaign->members()->where('user_id', $quiet->id)->update(['reminders_enabled' => false]);

    $sent = app(SendSessionReminders::class)->handle();

    expect($sent)->toBe(3)
        ->and(sentTo())->toBe(collect([$owner->email, $tobin->email, $ren->email])->sort()->values()->all())
        ->and($session->fresh()->reminder_sent_at)->not->toBeNull();
});

it('sends once, and again only after the date moves', function () {
    $campaign = remindingCampaign(24);
    $session = sessionIn($campaign, 20);
    memberOf($campaign, CampaignRole::Player);

    expect(app(SendSessionReminders::class)->handle())->toBe(2)
        ->and(app(SendSessionReminders::class)->handle())->toBe(0);

    $session->update(['title' => 'Renamed, not moved']);

    expect($session->fresh()->reminder_sent_at)->not->toBeNull()
        ->and(app(SendSessionReminders::class)->handle())->toBe(0);

    $session->update(['scheduled_at' => now()->addHours(6)]);

    expect($session->fresh()->reminder_sent_at)->toBeNull()
        ->and(app(SendSessionReminders::class)->handle())->toBe(2);
});

it('leaves a session outside the window, on a campaign with reminders off, or already past', function () {
    $off = Campaign::factory()->create(['reminder_lead_hours' => null]);
    memberOf($off, CampaignRole::Player);
    sessionIn($off, 2);

    $far = remindingCampaign(24);
    memberOf($far, CampaignRole::Player);
    $farSession = sessionIn($far, 30);

    $past = remindingCampaign(24);
    memberOf($past, CampaignRole::Player);
    $pastSession = sessionIn($past, -1);

    $played = remindingCampaign(24);
    memberOf($played, CampaignRole::Player);
    sessionIn($played, 5, ['status' => 'played']);

    expect(app(SendSessionReminders::class)->handle())->toBe(0)
        ->and($farSession->fresh()->reminder_sent_at)->toBeNull()
        ->and($pastSession->fresh()->reminder_sent_at)->toBeNull();

    Mail::assertNothingQueued();
});

it('never mails a player about a session they cannot see', function () {
    $campaign = remindingCampaign(24);
    sessionIn($campaign, 20, ['visibility' => Visibility::Dm]);
    $owner = ownerOf($campaign);
    memberOf($campaign, CampaignRole::Player, User::factory()->create(['email' => 'tobin@example.com']));
    $coGm = memberOf($campaign, CampaignRole::CoGm, User::factory()->create(['email' => 'cogm@example.com']));

    expect(app(SendSessionReminders::class)->handle())->toBe(2)
        ->and(sentTo())->toBe(collect([$owner->email, $coGm->email])->sort()->values()->all());
});

it('says the campaign, the session, and the time in the campaign zone, and no prose', function () {
    $campaign = remindingCampaign(24);
    $session = sessionIn($campaign, 20, [
        'strong_start' => 'The duke is already dead',
        'dm_notes' => 'They must not learn about the twin',
        'live_notes' => 'Live note prose',
        'recap' => 'Recap prose',
    ]);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));

    $html = (new SessionReminder($session, $campaign, $tobin))->render();

    expect($html)->toContain('Vell')
        ->and($html)->toContain('Session 12')
        ->and($html)->toContain('The Salt Cathedral')
        ->and($html)->toContain('Tue 8 Sep 2026 at 09:00 BST')
        ->and($html)->toContain($session->url())
        ->and($html)->toContain(route('campaigns.members', $campaign))
        ->and($html)->not->toContain('already dead')
        ->and($html)->not->toContain('the twin')
        ->and($html)->not->toContain('Live note prose')
        ->and($html)->not->toContain('Recap prose');
});

it('runs from the command, every fifteen minutes on the schedule', function () {
    $campaign = remindingCampaign(24);
    sessionIn($campaign, 20);

    $this->artisan('demgem:send-reminders')
        ->expectsOutputToContain('1 reminder')
        ->assertSuccessful();

    Mail::assertQueued(SessionReminder::class, 1);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'demgem:send-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('lets a member turn their own reminders off from the members page', function () {
    $campaign = Campaign::factory()->create();
    $tobin = memberOf($campaign, CampaignRole::Player);

    Livewire::actingAs($tobin)
        ->test(Members::class, ['campaign' => $campaign])
        ->assertSee('Email me before each session')
        ->call('setReminders', false);

    expect($campaign->members()->where('user_id', $tobin->id)->value('reminders_enabled'))->toBeFalsy();

    Livewire::actingAs($tobin)
        ->test(Members::class, ['campaign' => $campaign])
        ->call('setReminders', true);

    expect($campaign->members()->where('user_id', $tobin->id)->value('reminders_enabled'))->toBeTruthy();
});

it('lets the GM choose the lead time, off by default', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->fresh()->reminder_lead_hours)->toBeNull();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('reminderLeadHours', '48')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->fresh()->reminder_lead_hours)->toBe(48);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('reminderLeadHours', '36')
        ->call('save')
        ->assertHasErrors('reminderLeadHours');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('reminderLeadHours', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->fresh()->reminder_lead_hours)->toBeNull();
});
