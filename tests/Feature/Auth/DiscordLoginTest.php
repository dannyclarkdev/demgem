<?php

use App\Livewire\Profile\Edit;
use App\Models\Campaign;
use App\Models\CampaignInvite;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

/**
 * The callback is driven with a faked provider: Socialite's driver is swapped for a
 * mock whose user() returns what Discord would have said. No network, no app key.
 */
function discordSays(string $id, ?string $email, bool $verified = true, string $name = 'Tobin', ?string $avatar = null): void
{
    $user = (new SocialiteUser)->setRaw(['id' => $id, 'email' => $email, 'verified' => $verified])->map([
        'id' => $id,
        'nickname' => 'tobin_ashgrove',
        'name' => $name,
        'email' => $email,
        'avatar' => $avatar,
    ]);

    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
}

beforeEach(function () {
    config()->set('services.discord.client_id', 'test-client');
    config()->set('services.discord.client_secret', 'test-secret');
});

it('shows the button only when the install has a Discord app', function () {
    $this->get(route('login'))->assertOk()->assertSee('Continue with Discord');

    config()->set('services.discord.client_id', null);

    $this->get(route('login'))->assertOk()->assertDontSee('Continue with Discord');
    $this->get(route('auth.discord.redirect'))->assertNotFound();
    $this->get(route('auth.discord.callback'))->assertNotFound();
});

it('makes a new account for a Discord user nobody has seen, and lands them on the invite', function () {
    $campaign = Campaign::factory()->create();
    $invite = CampaignInvite::factory()->for($campaign)->create();

    // A guest opens the invite, is sent to log in, and continues with Discord.
    $this->get(route('invites.show', $invite->token))->assertRedirect(route('login'));

    discordSays('123456789012345678', 'tobin@example.test', verified: true, name: 'Tobin Ashgrove', avatar: 'https://cdn.discordapp.com/avatars/1/abc.png');

    $this->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('invites.show', $invite->token));

    $user = User::query()->where('email', 'tobin@example.test')->firstOrFail();

    $this->assertAuthenticatedAs($user);

    expect($user->name)->toBe('Tobin Ashgrove')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->socialAccount('discord')?->provider_id)->toBe('123456789012345678')
        ->and($user->socialAccount('discord')?->avatar_url)->toBe('https://cdn.discordapp.com/avatars/1/abc.png');
});

it('signs a linked user in without touching their name', function () {
    $user = User::factory()->create(['name' => 'Danny']);
    SocialAccount::factory()->for($user)->discord('42')->create();

    discordSays('42', 'other@example.test', name: 'Somebody Else');

    $this->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('campaigns.index'));

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->name)->toBe('Danny')
        ->and(User::query()->count())->toBe(1);
});

it('links a verified email to the account that already has it', function () {
    $user = User::factory()->create(['email' => 'danny@example.test', 'name' => 'Danny']);

    discordSays('77', 'Danny@Example.test', verified: true, name: 'dannyclarkdev');

    $this->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('campaigns.index'));

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->name)->toBe('Danny')
        ->and($user->socialAccount('discord')?->provider_id)->toBe('77')
        ->and(User::query()->count())->toBe(1);
});

it('refuses an unverified email that matches an account, and says what to do', function () {
    User::factory()->create(['email' => 'danny@example.test']);

    discordSays('77', 'danny@example.test', verified: false);

    $this->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'link Discord from your profile'));

    $this->assertGuest();

    expect(SocialAccount::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
});

it('refuses a Discord account with no email', function () {
    discordSays('77', null);

    $this->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'no email'));

    $this->assertGuest();

    expect(User::query()->count())->toBe(0);
});

it('links Discord to the signed-in user from the profile', function () {
    $user = User::factory()->create();

    discordSays('55', 'whatever@example.test');

    $this->actingAs($user)
        ->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('profile.edit'));

    expect($user->socialAccount('discord')?->provider_id)->toBe('55');
});

it('refuses to link a Discord account that another user already linked', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    SocialAccount::factory()->for($other)->discord('99')->create();

    discordSays('99', 'whatever@example.test');

    $this->actingAs($user)
        ->get(route('auth.discord.callback', ['code' => 'x', 'state' => 'y']))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'different demgem account'));

    expect($user->socialAccount('discord'))->toBeNull()
        ->and($other->socialAccount('discord')?->provider_id)->toBe('99');
});

it('shows the linked account on the profile and unlinks it', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->discord('55')->create(['name' => 'tobin_ashgrove']);

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->assertSee('Connected accounts')
        ->assertSee('tobin_ashgrove')
        ->assertSee('Unlink')
        ->call('unlinkDiscord')
        ->assertRedirect(route('profile.edit'));

    expect($user->socialAccounts()->count())->toBe(0);

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->assertSee('Link Discord');
});

it('sends a cancelled sign-in back to the login page with nothing changed', function () {
    $this->get(route('auth.discord.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
});
