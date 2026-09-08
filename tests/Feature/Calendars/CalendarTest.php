<?php

use App\Actions\Campaigns\ImportCampaign;
use App\Actions\Campaigns\ReadCampaignFile;
use App\Enums\CampaignRole;
use App\Livewire\Calendars\Edit;
use App\Livewire\Calendars\Show;
use App\Livewire\Campaigns\Show as Dashboard;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\User;
use App\Support\Reckoning\GameDate;
use Livewire\Livewire;

/**
 * Defining the world's calendar, reading the day, and moving it. What lands on the
 * grid and the timeline, and who may see it, is TimelineTest.
 */
it('lets a GM define a calendar and the dashboard names today', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Edit::class, ['campaign' => $campaign])
        ->set('name', 'The Tide Reckoning')
        ->set('era', 'AR')
        ->set('months', [
            ['name' => 'Thaw', 'days' => 10],
            ['name' => 'Harvestmoon', 'days' => 20],
            ['name' => 'Frost', 'days' => 30],
        ])
        ->set('weekdays', 'Sunday, Tideday')
        ->set('moons', [['name' => 'The Drowned Moon', 'cycle' => '8', 'offset' => '0']])
        ->set('leapEvery', '4')
        ->set('leapMonth', '1')
        ->set('current', ['year' => 1042, 'month' => 2, 'day' => 3])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('calendar.show', $campaign));

    $calendar = Calendar::query()->sole();

    expect($calendar->campaign_id)->toBe($campaign->id)
        ->and($calendar->months[1]['name'])->toBe('Harvestmoon')
        ->and($calendar->weekdays)->toBe(['Sunday', 'Tideday'])
        ->and($calendar->reckoning()->moons[0]['cycle'])->toBe(8.0)
        ->and($calendar->leap_every)->toBe(4)
        ->and($calendar->today()->equals(new GameDate(1042, 2, 3)))->toBeTrue()
        // Day 3 of Harvestmoon 1042: (1041 * 60 + 260) + 10 + 2 is even, so a Sunday.
        ->and($calendar->todayFormatted())->toBe('Sunday, 3 Harvestmoon 1042 AR');

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Dashboard::class, ['campaign' => $campaign])
        ->assertSee('Sunday, 3 Harvestmoon 1042 AR')
        ->assertSee('The Drowned Moon');
});

it('starts a new calendar from the earth', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Edit::class, ['campaign' => $campaign])
        ->assertSet('months.1.name', 'February')
        ->assertSet('weekdays', 'Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday')
        ->assertSet('leapEvery', '4')
        ->assertSet('leapMonth', '2');
});

it('edits the calendar it already has', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Edit::class, ['campaign' => $campaign])
        ->assertSet('name', 'The Tide Reckoning')
        ->assertSet('months.2.name', 'Frost')
        ->set('name', 'The Salt Reckoning')
        ->call('save')
        ->assertHasNoErrors();

    expect(Calendar::query()->count())->toBe(1)
        ->and(Calendar::query()->sole()->name)->toBe('The Salt Reckoning');
});

it('refuses a current date the calendar does not have', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Edit::class, ['campaign' => $campaign])
        ->set('months', [['name' => 'Only', 'days' => 30]])
        ->set('current', ['year' => 1, 'month' => 1, 'day' => 31])
        ->call('save')
        ->assertHasErrors(['current.day']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Edit::class, ['campaign' => $campaign])
        ->set('current', ['year' => 0, 'month' => 1, 'day' => 1])
        ->call('save')
        ->assertHasErrors(['current.year']);

    expect(Calendar::query()->count())->toBe(0);
});

it('refuses a month with no days, and a leap month it does not have', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Edit::class, ['campaign' => $campaign])
        ->set('months', [['name' => 'Only', 'days' => 0]])
        ->set('leapMonth', '2')
        ->call('save')
        ->assertHasErrors(['months.0.days', 'leapMonth']);
});

it('keeps a player off the edit screen', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('calendar.edit', $campaign))
        ->assertForbidden();
});

it('advances the day across a month end and a year end', function () {
    $campaign = Campaign::factory()->create();
    $calendar = Calendar::factory()->inCampaign($campaign)->on(new GameDate(1042, 1, 10))->create();

    $show = Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign]);

    $show->call('advance', 1);
    expect($calendar->refresh()->today()->toArray())->toBe(['year' => 1042, 'month' => 2, 'day' => 1]);

    $show->call('advance', 50);
    expect($calendar->refresh()->today()->toArray())->toBe(['year' => 1043, 'month' => 1, 'day' => 1]);

    $show->call('advance', -1);
    expect($calendar->refresh()->today()->toArray())->toBe(['year' => 1042, 'month' => 3, 'day' => 30]);
});

it('sets the date outright', function () {
    $campaign = Campaign::factory()->create();
    $calendar = Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign])
        ->set('date', ['year' => 1050, 'month' => 3, 'day' => 12])
        ->call('setDate')
        ->assertHasNoErrors();

    expect($calendar->refresh()->today()->toArray())->toBe(['year' => 1050, 'month' => 3, 'day' => 12]);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign])
        ->set('date', ['year' => 1050, 'month' => 1, 'day' => 11])
        ->call('setDate')
        ->assertHasErrors(['date.day']);
});

it('lets a player read the day and nothing else', function () {
    $campaign = Campaign::factory()->create();
    $calendar = Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Show::class, ['campaign' => $campaign])
        ->assertSee('Sunday, 3 Harvest 1042 AR')
        ->assertDontSee('Advance a day')
        ->call('advance', 1)
        ->assertForbidden();

    expect($calendar->refresh()->current_day)->toBe(3);
});

it('lays the month out on the week', function () {
    $campaign = Campaign::factory()->create();
    // Harvest 1042 starts on day number 1041 * 60 + 260 + 10, which is even: a Sunday.
    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign])
        ->assertSee('Harvest 1042')
        ->assertSee('Sunday')
        ->assertSee('Moonday')
        ->assertViewHas('leadingBlanks', 0)
        ->assertViewHas('days', fn (array $days) => count($days) === 20 && $days[2]['isToday'] === true)
        ->call('nextMonth')
        ->assertSee('Frost 1042')
        // Frost follows twenty days of Harvest, so it starts on the same weekday.
        ->assertViewHas('leadingBlanks', 0)
        ->call('nextMonth')
        ->assertSee('Thaw 1043')
        // Thirty days of Frost, still even. 1043 is not a leap year.
        ->assertViewHas('leadingBlanks', 0)
        ->assertViewHas('days', fn (array $days) => count($days) === 10)
        ->call('previousMonth')
        ->call('previousMonth')
        ->call('previousMonth')
        ->assertSee('Thaw 1042')
        ->call('goToToday')
        ->assertSee('Harvest 1042');
});

it('shows every member an empty calendar page and a GM the way to fill it', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Show::class, ['campaign' => $campaign])
        ->assertSee('No calendar yet')
        ->assertSee(route('calendar.edit', $campaign));

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Show::class, ['campaign' => $campaign])
        ->assertSee('No calendar yet')
        ->assertDontSee(route('calendar.edit', $campaign));

    Livewire::actingAs(ownerOf($campaign))
        ->test(Dashboard::class, ['campaign' => $campaign])
        ->assertDontSee('In the world');
});

it('exports the calendar under the campaign and restores it on import', function () {
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->create();

    $document = exportedArray($campaign);

    expect($document['campaign']['calendar'])->toMatchArray([
        'name' => 'The Tide Reckoning',
        'era' => 'AR',
        'weekdays' => ['Sunday', 'Moonday'],
        'leap_every' => 4,
        'leap_month' => 1,
        'current' => ['year' => 1042, 'month' => 2, 'day' => 3],
    ])->and($document['campaign']['calendar']['months'][1])->toBe(['name' => 'Harvest', 'days' => 20]);

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    expect($read->errors)->toBe([]);

    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());
    $restored = $copy->calendar;

    expect($restored)->not->toBeNull()
        ->and($restored->months)->toBe(Calendar::query()->whereKeyNot($restored->id)->sole()->months)
        ->and($restored->moons[0]['name'])->toBe('Pale')
        ->and($restored->todayFormatted())->toBe('Sunday, 3 Harvest 1042 AR');
});

it('imports a campaign with no calendar and drops one it cannot read', function () {
    $campaign = Campaign::factory()->create();
    $document = exportedArray($campaign);

    expect($document['campaign']['calendar'])->toBeNull();

    $document['campaign']['calendar'] = ['name' => 'Broken', 'months' => [], 'current' => ['year' => 1, 'month' => 1, 'day' => 1]];

    $read = app(ReadCampaignFile::class)->handle(json_encode($document, JSON_THROW_ON_ERROR));
    $copy = app(ImportCampaign::class)->handle($read->document, User::factory()->create());

    expect($copy->calendar)->toBeNull()
        ->and($read->report->truncated)->toBe(1);
});
