<?php

namespace App\Actions\Decisions;

use App\Models\Decision;
use App\Models\GameSession;

class UpdateDecision
{
    /**
     * The three fields a GM edits. The consequence is the one written later, often
     * sessions after the choice, which is why editing is not an afterthought here.
     */
    public function handle(Decision $decision, string $choice, ?string $consequence, ?GameSession $session): Decision
    {
        $decision->update([
            'choice' => trim($choice),
            'consequence' => filled($consequence) ? trim((string) $consequence) : null,
            'game_session_id' => $session?->id,
        ]);

        return $decision;
    }
}
