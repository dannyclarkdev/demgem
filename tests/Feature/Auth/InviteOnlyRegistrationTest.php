<?php

use App\Enums\CampaignRole;
use App\Models\Campaign;
use App\Models\CampaignInvite;
use App\Models\User;
use App\Support\Auth\RegistrationGate;

/**
 * The default mode is invite. Every test that wants the old public page says so.
 */
function registerPayload(string $email = 'tobin@example.test'): array
{
    return [
        'name' => 'Tobin Ashgrove',
        'email' => $email,
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ];
}

it('closes the register page once the install has a user and no invite is pending', function () {
    User::factory()->create();

    $this->get(route('register'))
        ->assertForbidden()
        ->assertSee('invite only');

    $this->post(route('register'), registerPayload())->assertForbidden();

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
});

it('lets the first person on an empty install register', function () {
    $this->get(route('register'))->assertOk()->assertSee('Create your account');

    $this->post(route('register'), registerPayload())->assertRedirect(route('campaigns.index'));

    $this->assertAuthenticated();
});

it('opens the register page to a guest who opened an invite, and lands them on it', function () {
    $campaign = Campaign::factory()->create(['name' => 'The Ashgrove Chronicle']);
    $invite = CampaignInvite::factory()->for($campaign)->role(CampaignRole::Player)->create();

    $this->get($invite->url())
        ->assertOk()
        ->assertSee('The Ashgrove Chronicle')
        ->assertSee('Create an account')
        ->assertSessionHas(RegistrationGate::SESSION_KEY, $invite->token)
        ->assertSessionHas('url.intended', $invite->url());

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('You are joining')
        ->assertSee('The Ashgrove Chronicle');

    $this->post(route('register'), registerPayload())->assertRedirect($invite->url());

    $this->assertAuthenticated();

    $this->post(route('invites.accept', $invite->token))
        ->assertRedirect(route('campaigns.show', $campaign))
        ->assertSessionMissing(RegistrationGate::SESSION_KEY);

    $user = User::query()->where('email', 'tobin@example.test')->firstOrFail();

    expect($campaign->fresh()->roleFor($user))->toBe(CampaignRole::Player);
});

it('does not open the page for an invite that died after it was opened', function () {
    User::factory()->create();
    $invite = CampaignInvite::factory()->create();

    $this->get($invite->url())->assertOk();

    $invite->forceFill(['revoked_at' => now()])->save();

    $this->get(route('register'))->assertForbidden();
    $this->post(route('register'), registerPayload())->assertForbidden();

    $this->assertGuest();
});

it('does not open the page for a token that was never an invite', function () {
    User::factory()->create();

    $this->withSession([RegistrationGate::SESSION_KEY => 'not-a-token'])
        ->get(route('register'))
        ->assertForbidden();
});

it('keeps a public register page in open mode', function () {
    config()->set('registration.mode', 'open');
    User::factory()->create();

    $this->get(route('register'))->assertOk()->assertSee('Create your account');
    $this->post(route('register'), registerPayload())->assertRedirect(route('campaigns.index'));

    $this->assertAuthenticated();
});

it('reads any other mode as invite', function () {
    config()->set('registration.mode', 'yes please');
    User::factory()->create();

    $this->get(route('register'))->assertForbidden();
});

it('offers the register link on the login page only while the door is open', function () {
    $this->get(route('login'))->assertOk()->assertSee('Create an account');

    User::factory()->create();

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Create an account')
        ->assertSee('Ask your GM for an invite link');

    $invite = CampaignInvite::factory()->create();
    $this->get($invite->url())->assertOk();

    $this->get(route('login'))->assertOk()->assertSee('Create an account');
});

it('sends a logged in user straight to the join button without touching the session', function () {
    $invite = CampaignInvite::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get($invite->url())
        ->assertOk()
        ->assertSee('Join')
        ->assertDontSee('Create an account')
        ->assertSessionMissing(RegistrationGate::SESSION_KEY);
});
