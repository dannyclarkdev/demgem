<?php

namespace App\Support\Auth;

use App\Models\CampaignInvite;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * May this request make an account?
 *
 * Mode "open" says yes to everyone. Mode "invite", the default, says yes to a
 * session that holds a valid invite token, and to the first person to reach an
 * install with no users, who is about to make the first campaign. The invite page
 * writes the token for a guest; nothing else does. The token is looked up on every
 * ask rather than remembered as a flag, so an invite revoked between the tap and
 * the form closes the door with it.
 *
 * Fortify's register view, CreateNewUser, and the Discord sign-in all ask here and
 * nowhere else.
 */
final class RegistrationGate
{
    public const SESSION_KEY = 'registration.invite';

    public function isInviteOnly(): bool
    {
        return config('registration.mode') !== 'open';
    }

    public function allows(Request $request): bool
    {
        if (! $this->isInviteOnly()) {
            return true;
        }

        if ($this->pendingInvite($request) !== null) {
            return true;
        }

        return ! User::query()->exists();
    }

    public function pendingInvite(Request $request): ?CampaignInvite
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $invite = CampaignInvite::findByToken($token);

        return $invite !== null && $invite->isValid() ? $invite : null;
    }

    /**
     * A guest opened an invite. Every door they take next, register, login, or
     * Discord, redirects to the intended URL, so the invite is that URL.
     */
    public function remember(Request $request, CampaignInvite $invite): void
    {
        $request->session()->put(self::SESSION_KEY, $invite->token);
        $request->session()->put('url.intended', $invite->url());
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
