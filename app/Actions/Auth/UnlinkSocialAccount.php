<?php

namespace App\Actions\Auth;

use App\Models\User;

class UnlinkSocialAccount
{
    /**
     * Allowed always. A user with no way in but Discord still has the reset link,
     * and the profile card says so before they press it.
     */
    public function handle(User $user, string $provider): void
    {
        $user->socialAccounts()->where('provider', $provider)->delete();
    }
}
