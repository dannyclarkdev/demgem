<?php

use App\Enums\CampaignRole;
use App\Livewire\Profile\Edit;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('creates a key from the profile and shows the secret once', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('tokenName', 'My assistant')
        ->set('tokenCanWrite', true)
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSee('Copy it now. It is not shown again.');

    $secret = $component->get('newToken');

    expect($secret)->toBeString()
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('My assistant')
        ->and($user->tokens()->first()->abilities)->toBe(['read', 'write']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('My assistant')
        ->assertSee('Read and write')
        ->assertDontSee($secret);
});

it('makes a read-only key unless the box is ticked, and needs a name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('tokenName', 'Read me')
        ->call('createToken')
        ->assertHasNoErrors();

    expect($user->tokens()->first()->abilities)->toBe(['read']);

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('tokenName', '')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);
});

it('revokes a key from the profile, and a revoked key is refused', function () {
    $user = User::factory()->create();
    $secret = $user->createToken('Old script', ['read'])->plainTextToken;

    $this->withToken($secret)->getJson('/api/v1/me')->assertOk();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->call('revokeToken', $user->tokens()->first()->id)
        ->assertRedirect(route('profile.edit'));

    expect($user->tokens()->count())->toBe(0);

    // Livewire::actingAs left the user on the web guard, which auth:sanctum accepts.
    // Forget it, so the bearer token is the only credential the request carries.
    $this->app['auth']->forgetGuards();

    $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
});

it('only revokes its owner’s keys', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $other->createToken('Not yours', ['read']);

    Livewire::actingAs($owner)
        ->test(Edit::class)
        ->call('revokeToken', $other->tokens()->first()->id);

    expect($other->tokens()->count())->toBe(1);
});

it('answers /me with the key holder and their memberships, by role', function () {
    $duchy = Campaign::factory()->create(['name' => 'The Drowned Duchy']);
    $marsh = Campaign::factory()->create(['name' => 'Ashen Marsh']);
    $user = memberOf($duchy, CampaignRole::Player, User::factory()->create(['name' => 'Tobin Ash', 'email' => 'tobin@example.com']));
    memberOf($marsh, CampaignRole::CoGm, $user);
    Campaign::factory()->create(['name' => 'Not mine']);

    $secret = $user->createToken('script', ['read'])->plainTextToken;

    $this->withToken($secret)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => 'Tobin Ash',
                'email' => 'tobin@example.com',
                'campaigns' => [
                    ['id' => $marsh->id, 'name' => 'Ashen Marsh', 'role' => 'co_gm'],
                    ['id' => $duchy->id, 'name' => 'The Drowned Duchy', 'role' => 'player'],
                ],
            ],
        ]);
});

it('refuses a request with no key, and leaves out a deleted campaign', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $campaign = Campaign::factory()->create(['name' => 'Gone']);
    $user = memberOf($campaign, CampaignRole::Player);
    $secret = $user->createToken('script', ['read'])->plainTextToken;

    $campaign->delete();

    $this->withToken($secret)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.campaigns', []);
});

it('throttles the API per key holder', function () {
    expect(RateLimiter::limiter('api'))->not->toBeNull();

    $user = User::factory()->create();
    $secret = $user->createToken('script', ['read'])->plainTextToken;

    $this->withToken($secret)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60');
});
