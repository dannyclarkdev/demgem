<?php

use App\Enums\CampaignRole;
use App\Enums\Ruleset;
use App\Livewire\Campaigns\Settings;
use App\Models\Campaign;
use Livewire\Livewire;

it('lets DM roles open settings', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);

    $this->actingAs($user)
        ->get(route('campaigns.settings', $campaign))
        ->assertOk()
        ->assertSeeLivewire(Settings::class);
})->with([
    'owner' => CampaignRole::Owner,
    'co_gm' => CampaignRole::CoGm,
]);

it('forbids settings for players and spectators', function (CampaignRole $role) {
    $campaign = Campaign::factory()->create();
    $user = memberOf($campaign, $role);

    $this->actingAs($user)
        ->get(route('campaigns.settings', $campaign))
        ->assertForbidden();
})->with([
    'player' => CampaignRole::Player,
    'spectator' => CampaignRole::Spectator,
]);

it('updates the name, description, and ruleset', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('name', 'Renamed Campaign')
        ->set('description', '')
        ->set('ruleset', Ruleset::Generic->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('campaigns.settings', $campaign));

    expect($campaign->fresh())
        ->name->toBe('Renamed Campaign')
        ->description->toBeNull()
        ->ruleset->toBe(Ruleset::Generic);
});

it('rejects an empty name', function () {
    $campaign = Campaign::factory()->create();

    Livewire::actingAs(ownerOf($campaign))
        ->test(Settings::class, ['campaign' => $campaign])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});
