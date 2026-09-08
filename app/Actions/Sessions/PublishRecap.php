<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The recap is published on purpose, never as a side effect of marking a session
 * played. The page and the API both come through here, so whatever happens when a
 * recap goes up happens once and in one place.
 */
class PublishRecap
{
    public function __construct(private readonly UpdateSession $updateSession) {}

    /**
     * @param  string|null  $recap  A new draft to save on the way, or null to publish what is there.
     */
    public function handle(GameSession $session, User $actor, ?string $recap = null): GameSession
    {
        $text = $recap ?? $session->recap;

        if (! filled($text)) {
            throw ValidationException::withMessages(['recap' => 'Write the recap before you publish it.']);
        }

        return $this->updateSession->handle($session, $actor, [
            'recap' => $text,
            'recap_published_at' => $session->recap_published_at ?? now(),
        ]);
    }
}
