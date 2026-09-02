<?php

use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('renders the profile page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeLivewire(Edit::class);
});

it('updates the name and email', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('updateProfile')
        ->assertHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com');
});

it('rejects an email another user owns', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('email', 'taken@example.com')
        ->call('updateProfile')
        ->assertHasErrors(['email']);

    expect($user->fresh()->email)->not->toBe('taken@example.com');
});

it('changes the password when the current password matches', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('current_password', 'password')
        ->set('password', 'new-correct-horse')
        ->set('password_confirmation', 'new-correct-horse')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('new-correct-horse', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class)
        ->set('current_password', 'wrong')
        ->set('password', 'new-correct-horse')
        ->set('password_confirmation', 'new-correct-horse')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
