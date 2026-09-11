<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\DiscordSignInRefused;
use App\Actions\Auth\SignInWithDiscord;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Continue with Discord. The redirect sends the browser to Discord with the two
 * scopes demgem needs and no more; the callback hands what Discord said to
 * SignInWithDiscord and ends where the auth middleware wanted the user to go.
 */
class DiscordAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.discord.client_id')) && filled(config('services.discord.client_secret'));
    }

    public function redirect(): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver(SignInWithDiscord::PROVIDER);

        return $driver->scopes(['identify', 'email'])->redirect();
    }

    public function callback(Request $request, SignInWithDiscord $signIn): RedirectResponse
    {
        abort_unless(self::isConfigured(), 404);

        // Discord sends the user back with an error when they pressed Cancel.
        if ($request->filled('error')) {
            return redirect()->route('login')->with('status', 'Discord did not sign you in. Nothing was changed.');
        }

        try {
            $discord = Socialite::driver(SignInWithDiscord::PROVIDER)->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->with('status', 'That sign-in link had expired. Try again.');
        }

        /** @var User|null $current */
        $current = Auth::user();

        try {
            $user = $signIn->handle($discord, $current);
        } catch (DiscordSignInRefused $refused) {
            return $current !== null
                ? redirect()->route('profile.edit')->with('status', $refused->getMessage())
                : redirect()->route('login')->with('status', $refused->getMessage());
        }

        if ($current !== null) {
            return redirect()->route('profile.edit')->with('status', 'Discord is linked to your account.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('campaigns.index'));
    }
}
