<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * The one authorisation callback in the app, exercised the way Laravel exercises it.
 *
 * The suite runs on the null broadcaster, whose auth() does nothing at all, so these
 * tests point the connection at reverb with throwaway credentials. Nothing here
 * touches a socket: the broadcaster verifies the channel and signs the response
 * locally, and the signing is the only thing the credentials are for.
 */
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    // Channels register on whichever broadcaster was default when the application
    // booted, which here is the null one. Pointing the connection at reverb builds a
    // second broadcaster that has never seen routes/channels.php, so load it again.
    require base_path('routes/channels.php');
});

function authoriseChannel(string $channel): TestResponse
{
    return test()->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

it('lets a member listen to their own campaign, and says who they are', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $member = memberOf($campaign, $role);

    $response = $this->actingAs($member)->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-campaign.{$campaign->id}",
    ]);

    $response->assertOk();

    $data = json_decode($response->json('channel_data'), true, 512, JSON_THROW_ON_ERROR);

    // Pusher's channel_data carries the id as a string, which is the protocol, not us.
    expect((int) $data['user_id'])->toBe($member->id)
        ->and($data['user_info']['name'])->toBe($member->name)
        ->and($data['user_info']['role'])->toBe($role->value);
})->with([
    'owner' => CampaignRole::CoGm,
    'player' => CampaignRole::Player,
    'spectator' => CampaignRole::Spectator,
]);

it('refuses a member of another campaign', function () {
    $campaign = Campaign::factory()->create();
    $stranger = memberOf(Campaign::factory()->create(), CampaignRole::Player);

    $this->actingAs($stranger)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "presence-campaign.{$campaign->id}",
        ])
        ->assertForbidden();
});

it('refuses a user who belongs to no campaign at all', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "presence-campaign.{$campaign->id}",
        ])
        ->assertForbidden();
});

it('refuses a guest', function () {
    $campaign = Campaign::factory()->create();

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-campaign.{$campaign->id}",
    ])->assertForbidden();
});

it('refuses a campaign that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-campaign.01000000000000000000000000',
        ])
        ->assertForbidden();
});

it('stops a removed member on their next subscription', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);

    $this->actingAs($player)
        ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "presence-campaign.{$campaign->id}"])
        ->assertOk();

    $campaign->members()->where('user_id', $player->id)->delete();
    $campaign->forgetMemberCache();

    $this->actingAs($player)
        ->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "presence-campaign.{$campaign->id}"])
        ->assertForbidden();
});
