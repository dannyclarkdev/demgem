<?php

namespace App\Http\Controllers;

use App\Actions\Invites\AcceptInvite;
use App\Exceptions\InvalidInviteException;
use App\Models\CampaignInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InviteController extends Controller
{
    public function show(Request $request, string $token): View|Response
    {
        $invite = CampaignInvite::findByToken($token);

        if ($invite === null || ! $invite->isValid()) {
            return $this->invalid();
        }

        return view('invites.show', [
            'invite' => $invite,
            'membership' => $invite->campaign->memberFor($request->user()),
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvite $acceptInvite): RedirectResponse|Response
    {
        $invite = CampaignInvite::findByToken($token);

        if ($invite === null || ! $invite->isValid()) {
            return $this->invalid();
        }

        $campaign = $invite->campaign;

        if ($campaign->memberFor($request->user()) !== null) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('status', "You are already a member of {$campaign->name}.");
        }

        try {
            $acceptInvite->handle($invite, $request->user());
        } catch (InvalidInviteException) {
            return $this->invalid();
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('status', "Welcome to {$campaign->name}.");
    }

    /**
     * One page for unknown, expired, exhausted, and revoked tokens. Never say which.
     */
    private function invalid(): Response
    {
        return response()->view('invites.invalid', [], 404);
    }
}
