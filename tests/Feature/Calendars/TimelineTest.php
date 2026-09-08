<?php

use App\Enums\CampaignRole;
use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Livewire\Calendars\Show as CalendarShow;
use App\Livewire\Calendars\Timeline;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\GameSession;
use App\Support\Reckoning\GameDate;
use Livewire\Livewire;

/**
 * The leak test for the timeline and the month grid. A hidden event and a GM-only
 * session must be absent from a player's HTML and snapshot, from both screens.
 */
function aDatedWorld(): Campaign
{
    $campaign = Campaign::factory()->create();
    Calendar::factory()->inCampaign($campaign)->on(new GameDate(1042, 2, 3))->create();

    Entity::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => EntityType::Event,
        'name' => 'The Harbor Fire',
        'visibility' => Visibility::Players,
        'happens_year' => 1042,
        'happens_month' => 1,
        'happens_day' => 4,
    ]);
    Entity::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => EntityType::Event,
        'name' => 'The Duke Drowns Again',
        'visibility' => Visibility::Dm,
        'happens_year' => 1042,
        'happens_month' => 2,
        'happens_day' => 9,
    ]);
    Entity::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => EntityType::Event,
        'name' => 'The Founding Of Vell',
        'visibility' => Visibility::Players,
        'happens_year' => 900,
        'happens_month' => 3,
        'happens_day' => 1,
    ]);
    Entity::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => EntityType::Event,
        'name' => 'An Undated Rumor',
        'visibility' => Visibility::Players,
    ]);

    GameSession::factory()->create([
        'campaign_id' => $campaign->id,
        'number' => 1,
        'title' => 'Under The Pilings',
        'visibility' => Visibility::Players,
        'in_game_start_year' => 1042,
        'in_game_start_month' => 2,
        'in_game_start_day' => 1,
        'in_game_end_year' => 1042,
        'in_game_end_month' => 2,
        'in_game_end_day' => 2,
    ]);
    GameSession::factory()->create([
        'campaign_id' => $campaign->id,
        'number' => 2,
        'title' => 'The Secret Session',
        'visibility' => Visibility::Dm,
        'in_game_start_year' => 1042,
        'in_game_start_month' => 2,
        'in_game_start_day' => 5,
    ]);

    return $campaign;
}

it('keeps a hidden event and a GM-only session off a player\'s timeline', function () {
    $campaign = aDatedWorld();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Timeline::class, ['campaign' => $campaign])
        ->assertSee('The Harbor Fire')
        ->assertSee('Under The Pilings')
        ->assertSee('The Founding Of Vell')
        ->assertDontSee('The Duke Drowns Again')
        ->assertDontSee('The Secret Session')
        ->assertDontSee('An Undated Rumor');

    $html = Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(Timeline::class, ['campaign' => $campaign])
        ->html();

    expect($html)->not->toContain('Drowns Again')->not->toContain('Secret Session');
});

it('shows the GM everything in world order, with today between the right rows', function () {
    $campaign = aDatedWorld();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Timeline::class, ['campaign' => $campaign])
        ->assertSeeInOrder([
            'The Founding Of Vell',
            'The Harbor Fire',
            'Under The Pilings',
            'Today',
            'The Secret Session',
            'The Duke Drowns Again',
        ])
        ->assertSee('900 AR')
        ->assertSee('1042 AR')
        ->assertDontSee('An Undated Rumor');
});

it('puts the day\'s events and sessions on the grid, gated', function () {
    $campaign = aDatedWorld();

    Livewire::actingAs(memberOf($campaign, CampaignRole::Player))
        ->test(CalendarShow::class, ['campaign' => $campaign])
        ->assertSee('Under The Pilings')
        ->assertDontSee('The Secret Session')
        ->assertDontSee('The Duke Drowns Again')
        ->call('previousMonth')
        ->assertSee('The Harbor Fire');

    Livewire::actingAs(ownerOf($campaign))
        ->test(CalendarShow::class, ['campaign' => $campaign])
        ->assertSee('The Secret Session')
        ->assertSee('The Duke Drowns Again');
});

it('shows an empty timeline until something is dated', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Timeline::class, ['campaign' => $campaign])
        ->assertSee('No calendar yet')
        ->assertDontSee('Nothing dated yet');

    Calendar::factory()->inCampaign($campaign)->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Timeline::class, ['campaign' => $campaign])
        ->assertSee('Nothing dated yet');
});
