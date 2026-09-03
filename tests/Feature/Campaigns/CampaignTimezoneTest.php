<?php

use App\Enums\CampaignRole;
use App\Livewire\Campaigns\Settings;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('defaults to UTC and lets an owner change it', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->timezone)->toBe('UTC');

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->assertSet('timezone', 'UTC')
        ->set('timezone', 'America/Chicago')
        ->call('save')
        ->assertHasNoErrors();

    expect($campaign->refresh()->timezone)->toBe('America/Chicago');
});

it('rejects a timezone that does not exist', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('timezone', 'Middle/Earth')
        ->call('save')
        ->assertHasErrors('timezone');

    expect($campaign->refresh()->timezone)->toBe('UTC');
});

it('shows session times in the campaign timezone to every member', function () {
    $campaign = Campaign::factory()->create(['timezone' => 'America/New_York']);
    GameSession::factory()->for($campaign)->number(1)->create([
        'title' => 'The Ashfall Road',
        'scheduled_at' => Carbon::parse('2026-09-10 23:00:00', 'UTC'),
    ]);

    foreach ([ownerOf($campaign), memberOf($campaign, CampaignRole::Player)] as $user) {
        $this->actingAs($user)
            ->get(route('sessions.show', [$campaign, 1]))
            ->assertOk()
            ->assertSee('19:00')
            ->assertSee('Thu 10 Sep 2026');
    }
});

it('keeps a player out of the settings screen', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(memberOf($campaign, CampaignRole::Player))
        ->get(route('campaigns.settings', $campaign))
        ->assertForbidden();
});
