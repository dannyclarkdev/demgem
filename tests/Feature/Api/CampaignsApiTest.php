<?php

use App\Enums\CampaignRole;
use App\Models\Calendar;
use App\Models\Campaign;
use App\Models\User;
use App\Support\Reckoning\GameDate;

it('lists the campaigns the key holder belongs to, with their role in each', function () {
    $duchy = Campaign::factory()->create(['name' => 'The Drowned Duchy', 'timezone' => 'Europe/London']);
    $marsh = Campaign::factory()->create(['name' => 'Ashen Marsh']);
    Campaign::factory()->create(['name' => 'Not mine']);
    $user = memberOf($duchy, CampaignRole::Player);
    memberOf($marsh, CampaignRole::CoGm, $user);
    Calendar::factory()->inCampaign($duchy)->on(new GameDate(1042, 3, 7))->create(['name' => 'Tide Reckoning']);

    asKey($user)
        ->getJson('/api/v1/campaigns')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ashen Marsh')
        ->assertJsonPath('data.0.role', 'co_gm')
        ->assertJsonPath('data.0.calendar', null)
        ->assertJsonPath('data.1.name', 'The Drowned Duchy')
        ->assertJsonPath('data.1.role', 'player')
        ->assertJsonPath('data.1.timezone', 'Europe/London')
        ->assertJsonPath('data.1.calendar.name', 'Tide Reckoning')
        ->assertJsonPath('data.1.calendar.today', ['year' => 1042, 'month' => 3, 'day' => 7])
        ->assertJsonPath('data.1.url', route('campaigns.show', $duchy));
});

it('shows one campaign to a member, and a 404 to anyone else', function () {
    $duchy = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $player = memberOf($duchy, CampaignRole::Player);
    $stranger = User::factory()->create();

    asKey($player)
        ->getJson("/api/v1/campaigns/{$duchy->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'The Drowned Duchy')
        ->assertJsonPath('data.role', 'player');

    asKey($stranger)
        ->getJson("/api/v1/campaigns/{$duchy->id}")
        ->assertNotFound();

    asKey($player)
        ->getJson('/api/v1/campaigns/does-not-exist')
        ->assertNotFound();

    $duchy->delete();

    asKey($player)
        ->getJson("/api/v1/campaigns/{$duchy->id}")
        ->assertNotFound();
});

it('refuses both routes without a key', function () {
    $duchy = Campaign::factory()->create();

    withoutKey()->getJson('/api/v1/campaigns')->assertUnauthorized();
    withoutKey()->getJson("/api/v1/campaigns/{$duchy->id}")->assertUnauthorized();
});
