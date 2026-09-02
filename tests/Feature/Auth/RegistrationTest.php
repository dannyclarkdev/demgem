<?php

use App\Models\User;

it('renders the registration page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account');
});

it('registers a user and redirects to campaigns', function () {
    $response = $this->post(route('register'), [
        'name' => 'Danny',
        'email' => 'danny@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertRedirect(route('campaigns.index'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'danny@example.com']);
});

it('redirects to the intended url after registration', function () {
    $response = $this->withSession(['url.intended' => '/profile'])->post(route('register'), [
        'name' => 'Danny',
        'email' => 'danny@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertRedirect('/profile');
});

it('rejects an empty payload', function () {
    $this->from(route('register'))
        ->post(route('register'), [])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
});

it('rejects an email that is already registered', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register'), [
        'name' => 'Danny',
        'email' => 'taken@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
