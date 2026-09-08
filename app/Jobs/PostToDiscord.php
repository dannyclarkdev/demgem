<?php

namespace App\Jobs;

use App\Discord\DiscordWebhook;
use App\Models\Campaign;
use App\Models\GameSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One line to a campaign's Discord channel. Queued, so a GM's click never waits on
 * Discord; three tries and then a log line, so a Discord outage never reaches a
 * GM's screen or stops a reminder pass.
 *
 * Every message is names and links, never prose. The channel's membership is not
 * the campaign's membership, and the rule that keeps a strong start out of an email
 * keeps a recap out of a channel.
 */
class PostToDiscord implements ShouldQueue
{
    use Queueable;

    public const TIMEOUT_SECONDS = 10;

    public const ATTEMPTS = 3;

    public function __construct(
        public Campaign $campaign,
        public string $content,
    ) {}

    public static function forRecap(GameSession $session): self
    {
        $campaign = $session->campaign;

        return new self($campaign, sprintf(
            '**%s** · %s · The recap is up: %s',
            $campaign->name,
            self::sessionName($session),
            $session->url(),
        ));
    }

    public static function forReminder(GameSession $session): self
    {
        $campaign = $session->campaign;

        return new self($campaign, sprintf(
            "**%s** · %s · %s · Say whether you're coming: %s",
            $campaign->name,
            self::sessionName($session),
            $session->scheduledAtIn($campaign->timezone)?->format('D j M Y \a\t H:i T'),
            $session->url(),
        ));
    }

    public static function test(Campaign $campaign): self
    {
        return new self($campaign, sprintf(
            '**%s** is connected to this channel. Recaps and reminders land here.',
            $campaign->name,
        ));
    }

    public function handle(): void
    {
        // Checked when it was saved, and checked again here, because this is the moment
        // a request leaves the server. A URL removed between queueing and running is
        // a null, and a null posts nothing.
        $webhook = DiscordWebhook::tryFrom((string) $this->campaign->discord_webhook_url);

        if ($webhook === null) {
            return;
        }

        try {
            // Three tries for an outage, one for a refusal: a 404 means the webhook was
            // deleted in Discord, and asking twice more will not bring it back.
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(self::ATTEMPTS, 250, fn (Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->asJson()
                ->post($webhook->url, ['content' => $this->content]);
        } catch (ConnectionException $exception) {
            Log::warning('Discord did not answer.', ['campaign' => $this->campaign->id, 'error' => $exception->getMessage()]);

            return;
        }

        if ($response->failed()) {
            Log::warning('Discord refused the message.', ['campaign' => $this->campaign->id, 'status' => $response->status()]);
        }
    }

    /**
     * "Session 12: The Salt Cathedral", or "Session 12" when it has no title.
     */
    private static function sessionName(GameSession $session): string
    {
        return filled($session->title) ? $session->label().': '.$session->title : $session->label();
    }
}
