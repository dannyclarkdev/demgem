<?php

use App\Enums\CampaignRole;
use App\Livewire\Sessions\Index;
use App\Models\Campaign;
use App\Models\GameSession;
use Livewire\Livewire;

it('groups by status, with upcoming first', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->published()->create(['title' => 'Done and told']);
    GameSession::factory()->for($campaign)->number(2)->withRecap()->create(['title' => 'Waiting on words']);
    GameSession::factory()->for($campaign)->number(3)->planned()->create(['title' => 'Next Thursday']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['Upcoming', 'Next Thursday', 'Needs a recap', 'Waiting on words', 'Past', 'Done and told']);
});

it('keeps an overdue planned session in upcoming and marks it for the GM', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->overdue()->create(['title' => 'We never played it']);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['Upcoming', 'We never played it', 'Overdue']);

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSee('We never played it')
        ->assertDontSee('Overdue');
});

it('keeps a cancelled session in front of the party while its date is ahead', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->cancelled()->create([
        'title' => 'Called off',
        'scheduled_at' => now()->addDays(2),
    ]);
    GameSession::factory()->for($campaign)->number(2)->cancelled()->create([
        'title' => 'Long gone',
        'scheduled_at' => now()->subMonth(),
    ]);

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['Upcoming', 'Called off', 'Past', 'Long gone']);
});

it('never shows a player the needs-a-recap group', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->withRecap('Draft only')->create(['title' => 'Played it']);

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertDontSee('Needs a recap')
        ->assertDontSee('Draft only')
        ->assertSee('Played it');
});

it('sorts upcoming sessions by date and puts undated ones last', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(1)->unscheduled()->create(['title' => 'Someday']);
    GameSession::factory()->for($campaign)->number(2)->create(['title' => 'In three weeks', 'scheduled_at' => now()->addWeeks(3)]);
    GameSession::factory()->for($campaign)->number(3)->create(['title' => 'Tomorrow', 'scheduled_at' => now()->addDay()]);

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['Tomorrow', 'In three weeks', 'Someday']);
});

it('filters by number and by title', function () {
    $campaign = Campaign::factory()->create();
    GameSession::factory()->for($campaign)->number(7)->create(['title' => 'The Ashfall Road']);
    GameSession::factory()->for($campaign)->number(8)->create(['title' => 'Harrowgate']);

    Livewire::actingAs(ownerOf($campaign))
        ->test(Index::class, ['campaign' => $campaign])
        ->set('search', 'ashfall')
        ->assertSee('The Ashfall Road')
        ->assertDontSee('Harrowgate')
        ->set('search', '8')
        ->assertSee('Harrowgate')
        ->assertDontSee('The Ashfall Road');
});

it('offers the first session on an empty list', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(ownerOf($campaign))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSee('No sessions yet')
        ->assertSee('Plan session 1');

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('sessions.index', $campaign))
        ->assertOk()
        ->assertSee('The GM has not scheduled a session yet.')
        ->assertDontSee('Plan session 1');
});
