<?php

namespace App\Actions\Sessions;

use App\Enums\Rsvp;
use App\Enums\SessionStatus;
use App\Jobs\PostToDiscord;
use App\Mail\SessionReminder;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\GameSession;
use App\Models\SessionRsvp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * The reminder pass. Runs outside any campaign context, so every query names its
 * campaign and the global scope has nothing to do.
 */
class SendSessionReminders
{
    /**
     * @return int How many reminders were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        foreach ($this->due() as $session) {
            $campaign = $session->campaign;
            $declined = SessionRsvp::withoutGlobalScopes()
                ->where('game_session_id', $session->id)
                ->where('rsvp', Rsvp::No->value)
                ->pluck('user_id');

            $recipients = CampaignMember::query()
                ->where('campaign_id', $campaign->id)
                ->where('reminders_enabled', true)
                ->whereNotIn('user_id', $declined)
                ->with('user')
                ->get()
                ->filter(fn (CampaignMember $member) => $session->isVisibleTo($member->role));

            foreach ($recipients as $member) {
                Mail::to($member->user)->queue(new SessionReminder($session, $campaign, $member->user));
                $queued++;
            }

            // The channel gets the same reminder, inside the same stamp, so the inbox
            // and the thread agree about which sessions were announced.
            if ($campaign->discord_webhook_url !== null) {
                dispatch(PostToDiscord::forReminder($session));
            }

            // Stamped after queueing, so a crash between the two sends twice rather
            // than never. That is the right way round for a reminder.
            $session->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        }

        return $queued;
    }

    /**
     * Planned, unsent, still ahead, and inside its campaign's window. The last clause
     * is what keeps a scheduler that was down for a week from mailing the party
     * about last Thursday.
     *
     * @return iterable<int, GameSession>
     */
    private function due(): iterable
    {
        return GameSession::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('status', SessionStatus::Planned->value)
            ->whereNull('reminder_sent_at')
            ->where('scheduled_at', '>', now())
            ->whereHas('campaign', function (Builder $query): void {
                $query->whereNotNull('reminder_lead_hours');
            })
            ->with('campaign')
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn (GameSession $session) => $session->scheduled_at <= now()->addHours($session->campaign->reminder_lead_hours ?? 0));
    }
}
