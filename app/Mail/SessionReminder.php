<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One email before a session. It names the campaign, the session, the time in the
 * campaign's zone, and where to answer. Nothing from the session itself reaches it:
 * mail is forwarded and mail is searched, and a strong start does not belong in
 * either.
 */
class SessionReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public GameSession $session,
        public Campaign $campaign,
        public User $member,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->name.': '.$this->session->label().' is '.$this->session->scheduled_at?->diffForHumans(),
        );
    }

    public function content(): Content
    {
        $when = $this->session->scheduledAtIn($this->campaign->timezone);

        return new Content(
            markdown: 'mail.session-reminder',
            with: [
                'campaignName' => $this->campaign->name,
                'sessionLabel' => $this->session->label(),
                'sessionTitle' => $this->session->title,
                'when' => $when?->format('D j M Y \a\t H:i T'),
                'sessionUrl' => $this->session->url(),
                'membersUrl' => route('campaigns.members', $this->campaign),
            ],
        );
    }
}
