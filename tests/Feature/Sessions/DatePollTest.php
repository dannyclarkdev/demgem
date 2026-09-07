<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\CampaignRole;
use App\Enums\Visibility;
use App\Livewire\Sessions\Attendance;
use App\Livewire\Sessions\Form;
use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\SessionDateOption;
use App\Models\SessionDateVote;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

function datelessSession(Campaign $campaign): GameSession
{
    return GameSession::factory()->for($campaign)->number(12)->planned()->unscheduled()->create(['title' => 'The Salt Cathedral']);
}

function pollOf(GameSession $session): Collection
{
    return SessionDateOption::withoutGlobalScopes()->where('game_session_id', $session->id)->orderBy('position')->get();
}

it('lets the GM offer three times, refuse a duplicate, and take one back', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'Europe/London']);
    $session = datelessSession($campaign);

    $component = Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->set('newOption', '2026-09-17T19:00')->call('addDateOption')->assertHasNoErrors()
        ->set('newOption', '2026-09-18T19:00')->call('addDateOption')->assertHasNoErrors()
        ->set('newOption', '2026-09-24T19:00')->call('addDateOption')->assertHasNoErrors()
        ->set('newOption', '2026-09-17T19:00')->call('addDateOption')->assertHasErrors('newOption');

    $options = pollOf($session);

    expect($options)->toHaveCount(3)
        ->and($options->first()->starts_at->toIso8601String())->toBe('2026-09-17T18:00:00+00:00')
        ->and($options->pluck('position')->all())->toBe([0, 1, 2]);

    $component->call('removeDateOption', $options[1]->id);

    expect(pollOf($session)->pluck('starts_at')->map->toIso8601String()->all())
        ->toBe(['2026-09-17T18:00:00+00:00', '2026-09-24T18:00:00+00:00']);
});

it('counts each column from the ticks of two players', function () {
    $campaign = Campaign::factory()->create();
    $session = datelessSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $mara = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Mara Vell']));
    [$thursday, $friday] = [
        SessionDateOption::factory()->forSession($session)->create(['starts_at' => '2026-09-17 18:00:00', 'position' => 0]),
        SessionDateOption::factory()->forSession($session)->create(['starts_at' => '2026-09-18 18:00:00', 'position' => 1]),
    ];

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('toggleDateVote', $thursday->id)
        ->call('toggleDateVote', $friday->id);

    Livewire::actingAs($mara)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('toggleDateVote', $friday->id);

    $votes = SessionDateVote::withoutGlobalScopes()->get()->groupBy('session_date_option_id')->map->count()->all();

    expect($votes)->toBe([$thursday->id => 1, $friday->id => 2]);

    // Ticking again unticks.
    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('toggleDateVote', $thursday->id)
        ->assertSee('Tobin Ash')
        ->assertSee('Mara Vell')
        ->assertSee('2 can make it')
        ->assertDontSee('1 can make it');

    expect(SessionDateVote::withoutGlobalScopes()->where('session_date_option_id', $thursday->id)->count())->toBe(0);
});

it('refuses a player who tries to offer a time or pick one, and a player on a GM-only session', function () {
    $campaign = Campaign::factory()->create();
    $session = datelessSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player);
    $option = SessionDateOption::factory()->forSession($session)->create();

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->assertDontSeeHtml('addDateOption')
        ->assertDontSeeHtml('pickDate')
        ->set('newOption', '2026-09-17T19:00')
        ->call('addDateOption')
        ->assertForbidden();

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('pickDate', $option->id)
        ->assertForbidden();

    $hidden = GameSession::factory()->for($campaign)->number(13)->planned()->unscheduled()->create(['visibility' => Visibility::Dm]);
    $hiddenOption = SessionDateOption::factory()->forSession($hidden)->create();

    Livewire::actingAs($tobin)
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $hidden])
        ->assertForbidden();

    expect(SessionDateVote::withoutGlobalScopes()->where('session_date_option_id', $hiddenOption->id)->count())->toBe(0);
});

it('picks a time, writes it in UTC, and closes the poll in one go', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'Europe/London']);
    $session = datelessSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player);
    $thursday = SessionDateOption::factory()->forSession($session)->create(['starts_at' => '2026-09-17 18:00:00']);
    $friday = SessionDateOption::factory()->forSession($session)->create(['starts_at' => '2026-09-18 18:00:00', 'position' => 1]);
    SessionDateVote::factory()->forOption($thursday, $tobin)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('pickDate', $friday->id)
        ->assertHasNoErrors();

    $session->refresh();

    expect($session->scheduled_at?->toIso8601String())->toBe('2026-09-18T18:00:00+00:00')
        ->and(pollOf($session))->toHaveCount(0)
        ->and(SessionDateVote::withoutGlobalScopes()->count())->toBe(0)
        ->and($session->acceptsRsvps())->toBeTrue();
});

it('leaves the poll intact when the pick names an option that is not there', function () {
    $campaign = Campaign::factory()->create();
    $session = datelessSession($campaign);
    $other = datelessSession(Campaign::factory()->create());
    $option = SessionDateOption::factory()->forSession($session)->create();
    $elsewhere = SessionDateOption::factory()->forSession($other)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session])
        ->call('pickDate', $elsewhere->id)
        ->assertNotFound();

    expect($session->fresh()->scheduled_at)->toBeNull()
        ->and(pollOf($session)->pluck('id')->all())->toBe([$option->id])
        ->and($elsewhere->fresh())->not->toBeNull();
});

it('keeps the poll when a date is typed into the form, says so, and lets the GM clear it', function () {
    $campaign = Campaign::factory()->create();
    $session = datelessSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player);
    $option = SessionDateOption::factory()->forSession($session)->create();
    SessionDateVote::factory()->forOption($option, $tobin)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Form::class, ['campaign' => $campaign, 'number' => 12])
        ->set('scheduled_at', '2026-10-01T19:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(pollOf($session))->toHaveCount(1);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Attendance::class, ['campaign' => $campaign, 'session' => $session->fresh()])
        ->assertSee('This session has a date now')
        ->call('clearDateOptions');

    expect(pollOf($session))->toHaveCount(0)
        ->and(SessionDateVote::withoutGlobalScopes()->count())->toBe(0);
});

it('exports the options with votes by name, and the round trip restores the options and not the votes', function () {
    $campaign = Campaign::factory()->create();
    $session = datelessSession($campaign);
    $tobin = memberOf($campaign, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash']));
    $option = SessionDateOption::factory()->forSession($session)->create(['starts_at' => '2026-09-17 18:00:00']);
    SessionDateVote::factory()->forOption($option, $tobin)->create();

    $exported = exportedArray($campaign);

    expect($exported['sessions'][0]['date_options'])->toBe([
        ['id' => $option->id, 'starts_at' => '2026-09-17T18:00:00+00:00', 'position' => 0, 'votes' => ['Tobin Ash']],
    ]);

    $result = app(ReadCampaignFile::class)->handle(json_encode($exported, JSON_THROW_ON_ERROR));

    expect($result->errors)->toBe([])
        ->and(collect($result->report->losses())->pluck('label')->all())
        ->toContain('1 answer about dates and attendance will be left behind');

    $copy = app(ImportCampaign::class)->handle($result->document, User::factory()->create());

    $restored = SessionDateOption::withoutGlobalScopes()->where('campaign_id', $copy->id)->get();

    expect($restored)->toHaveCount(1)
        ->and($restored->first()->id)->not->toBe($option->id)
        ->and($restored->first()->starts_at->toIso8601String())->toBe('2026-09-17T18:00:00+00:00')
        ->and(SessionDateVote::withoutGlobalScopes()->where('campaign_id', $copy->id)->count())->toBe(0);
});
