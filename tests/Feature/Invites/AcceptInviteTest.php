<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignInvite;
use App\Models\User;

it('sends a guest to login and remembers the invite url', function () {
    $invite = CampaignInvite::factory()->create();

    $this->get($invite->url())
        ->assertRedirect(route('login'))
        ->assertSessionHas('url.intended', $invite->url());
});

it('shows the campaign name and role to a logged in non-member', function () {
    $invite = CampaignInvite::factory()->role(CampaignRole::Player)->create();

    $this->actingAs(User::factory()->create())
        ->get($invite->url())
        ->assertOk()
        ->assertSee($invite->campaign->name)
        ->assertSee('Player')
        ->assertSee('Join');
});

it('joins the campaign with the invite role and counts the use', function () {
    $invite = CampaignInvite::factory()->role(CampaignRole::Spectator)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('invites.accept', $invite->token))
        ->assertRedirect(route('campaigns.show', $invite->campaign))
        ->assertSessionHas('status');

    expect($invite->campaign->fresh()->roleFor($user))->toBe(CampaignRole::Spectator)
        ->and($invite->fresh()->uses)->toBe(1);
});

it('does not change the role of an existing member', function () {
    $campaign = Campaign::factory()->create();
    $coGm = memberOf($campaign, CampaignRole::CoGm);
    $invite = CampaignInvite::factory()->for($campaign)->role(CampaignRole::Spectator)->create();

    $this->actingAs($coGm)->get($invite->url())->assertOk()->assertSee('already a member');

    $this->actingAs($coGm)
        ->post(route('invites.accept', $invite->token))
        ->assertRedirect(route('campaigns.show', $campaign));

    expect($campaign->fresh()->roleFor($coGm))->toBe(CampaignRole::CoGm)
        ->and($campaign->members()->count())->toBe(2)
        ->and($invite->fresh()->uses)->toBe(0);
});

it('treats the owner opening their own link as an existing member', function () {
    $campaign = Campaign::factory()->create();
    $invite = CampaignInvite::factory()->for($campaign)->create();

    $this->actingAs(ownerOf($campaign))
        ->post(route('invites.accept', $invite->token))
        ->assertRedirect(route('campaigns.show', $campaign));

    expect($campaign->fresh()->roleFor(ownerOf($campaign)))->toBe(CampaignRole::Owner);
});

it('shows one identical page for every kind of invalid invite', function (Closure $invite) {
    $token = $invite();

    $response = $this->actingAs(User::factory()->create())->get(route('invites.show', $token));

    $response->assertNotFound()->assertSee('This invite is not valid');

    $this->actingAs(User::factory()->create())
        ->post(route('invites.accept', $token))
        ->assertNotFound()
        ->assertSee('This invite is not valid');
})->with([
    'unknown token' => fn () => str_repeat('x', 40),
    'expired' => fn () => CampaignInvite::factory()->expired()->create()->token,
    'exhausted' => fn () => CampaignInvite::factory()->exhausted()->create()->token,
    'revoked' => fn () => CampaignInvite::factory()->revoked()->create()->token,
]);

it('stops accepting once max uses is reached', function () {
    $invite = CampaignInvite::factory()->singleUse()->create();

    $this->actingAs(User::factory()->create())->post(route('invites.accept', $invite->token))->assertRedirect();
    $this->actingAs(User::factory()->create())->post(route('invites.accept', $invite->token))->assertNotFound();

    expect($invite->campaign->members()->count())->toBe(2);
});

it('lets a removed user rejoin through a valid link', function () {
    $campaign = Campaign::factory()->create();
    $player = memberOf($campaign, CampaignRole::Player);
    $campaign->memberFor($player)->delete();
    $invite = CampaignInvite::factory()->for($campaign)->create();

    $this->actingAs($player)->post(route('invites.accept', $invite->token))->assertRedirect();

    expect($campaign->fresh()->roleFor($player))->toBe(CampaignRole::Player);
});
