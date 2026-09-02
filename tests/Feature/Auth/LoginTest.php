<?php

use App\Models\User;

it('renders the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back');
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('campaigns.index'));

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))->post(route('login'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});

it('redirects guests from protected pages to login', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

it('sends a logged in user from the root to campaigns', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('campaigns.index'));
});
