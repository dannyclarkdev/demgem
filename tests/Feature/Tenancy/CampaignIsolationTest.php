<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\User;

it('returns 404 for every campaign page of a campaign the user does not belong to', function (string $routeName) {
    $mine = Campaign::factory()->create();
    $theirs = Campaign::factory()->create();
    $user = memberOf($mine, CampaignRole::Owner);

    $this->actingAs($user)
        ->get(route($routeName, $theirs))
        ->assertNotFound();
})->with(['campaigns.show', 'campaigns.settings', 'campaigns.members']);

it('returns 404 for a campaign id that does not exist', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('campaigns.show', 'does-not-exist'))
        ->assertNotFound();
});

it('redirects guests to login', function () {
    $campaign = Campaign::factory()->create();

    $this->get(route('campaigns.show', $campaign))->assertRedirect(route('login'));
});
