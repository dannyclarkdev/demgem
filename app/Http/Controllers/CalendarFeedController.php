<?php

namespace App\Http\Controllers;

use App\Calendar\IcsCalendar;
use App\Calendar\IcsEvent;
use App\Enums\SessionStatus;
use App\Models\CampaignMember;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One feed per user, across every campaign they belong to. The token is the
 * credential, because a calendar app cannot log in; what it opens holds only what
 * its owner may see, and nothing from any session but its name and its link.
 */
class CalendarFeedController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $user = User::query()->where('calendar_token', $token)->first();

        abort_if($user === null, 404);

        $calendar = new IcsCalendar('demgem');
        $host = $request->getHost();
        $now = now();

        $memberships = $user->campaignMemberships()->with('campaign')->get();

        foreach ($memberships as $membership) {
            foreach ($this->sessions($membership) as $session) {
                $calendar->add(new IcsEvent(
                    uid: 'session-'.$session->id.'@'.$host,
                    summary: $session->label().': '.$session->displayTitle().' · '.$membership->campaign->name,
                    description: $session->url(),
                    startsAt: $session->scheduled_at,
                    endsAt: $session->scheduled_at->copy()->addMinutes($membership->campaign->session_length_minutes),
                    status: $session->status === SessionStatus::Cancelled ? 'CANCELLED' : 'CONFIRMED',
                    stampedAt: $session->updated_at ?? $now,
                ));
            }
        }

        return response($calendar->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="demgem.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Every dated session this member may see, under their role in that campaign.
     * Outside a campaign context, so the global scope is off and the filter is spelled
     * out.
     *
     * @return iterable<int, GameSession>
     */
    private function sessions(CampaignMember $membership): iterable
    {
        return GameSession::withoutGlobalScopes()
            ->where('campaign_id', $membership->campaign_id)
            ->whereNull('deleted_at')
            ->whereNotNull('scheduled_at')
            ->visibleTo($membership->role)
            ->orderBy('scheduled_at')
            ->get();
    }
}
