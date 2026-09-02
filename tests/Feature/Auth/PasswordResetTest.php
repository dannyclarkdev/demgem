<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('renders the forgot password page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Forgot your password?');
});

it('sends a reset link to a registered email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the reset form from the emailed link', function () {
    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'x@example.com']))
        ->assertOk()
        ->assertSee('Choose a new password');
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-correct-horse',
            'password_confirmation' => 'new-correct-horse',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('new-correct-horse', $user->fresh()->password))->toBeTrue();
});
