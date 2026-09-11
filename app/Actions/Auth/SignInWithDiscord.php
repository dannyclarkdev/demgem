<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;

/**
 * The Discord callback's outcomes, in one place and in one order.
 *
 * 1. A linked row for this Discord id: that user signs in.
 * 2. A user already signed in (linking from the profile): the row is linked to them.
 * 3. A user with this email: linked and signed in, when Discord says the email is
 *    verified. Refused otherwise, because an unverified email is how an account is
 *    taken over.
 * 4. Nobody: a new user, with a random password they never see.
 */
class SignInWithDiscord
{
    public const PROVIDER = SocialAccount::DISCORD;

    public function handle(ProviderUser $discord, ?User $current = null): User
    {
        $providerId = (string) $discord->getId();

        if ($providerId === '') {
            throw new DiscordSignInRefused('Discord did not say who you are. Try again.');
        }

        return DB::transaction(function () use ($discord, $providerId, $current): User {
            $linked = SocialAccount::query()
                ->where('provider', self::PROVIDER)
                ->where('provider_id', $providerId)
                ->first();

            if ($linked !== null) {
                if ($current !== null && $linked->user_id !== $current->id) {
                    throw new DiscordSignInRefused('That Discord account is already linked to a different demgem account.');
                }

                return $linked->user;
            }

            if ($current !== null) {
                $this->link($current, $discord, $providerId);

                return $current;
            }

            $email = mb_strtolower(trim((string) $discord->getEmail()));

            if ($email === '') {
                throw new DiscordSignInRefused('Your Discord account has no email address, and demgem needs one for invites and reminders. Create an account with a password instead.');
            }

            $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();

            if ($existing !== null) {
                if (! $this->emailIsVerified($discord)) {
                    throw new DiscordSignInRefused('An account with that email already exists. Log in with your password, then link Discord from your profile.');
                }

                $this->link($existing, $discord, $providerId);

                return $existing;
            }

            $user = User::create([
                'name' => $this->nameFrom($discord),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);

            if ($this->emailIsVerified($discord)) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->link($user, $discord, $providerId);

            return $user;
        });
    }

    private function link(User $user, ProviderUser $discord, string $providerId): void
    {
        $user->socialAccounts()->create([
            'provider' => self::PROVIDER,
            'provider_id' => $providerId,
            'name' => Str::limit($this->nameFrom($discord), 120, ''),
            'avatar_url' => $this->avatarFrom($discord),
        ]);
    }

    /**
     * Discord's display name, or its username when there is none. Never empty: a
     * user with no name is a row nothing can address.
     */
    private function nameFrom(ProviderUser $discord): string
    {
        $name = trim((string) ($discord->getName() ?: $discord->getNickname()));

        return $name !== '' ? Str::limit($name, 120, '') : 'Discord user';
    }

    private function avatarFrom(ProviderUser $discord): ?string
    {
        $avatar = (string) $discord->getAvatar();

        // Only Discord's own CDN, over https, and nothing a user typed.
        return preg_match('#^https://cdn\.discordapp\.com/#', $avatar) === 1 ? Str::limit($avatar, 500, '') : null;
    }

    /**
     * Discord's raw user object carries "verified". Socialite exposes the raw payload
     * through getRaw() on its concrete users; the contract does not, so the check is
     * defensive and reads false when nothing says otherwise.
     */
    private function emailIsVerified(ProviderUser $discord): bool
    {
        if (! method_exists($discord, 'getRaw')) {
            return false;
        }

        $raw = $discord->getRaw();

        return is_array($raw) && ($raw['verified'] ?? false) === true;
    }
}
