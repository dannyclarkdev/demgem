<?php

namespace App\Actions\Quests;

use App\Models\GameSession;
use App\Models\QuestObjective;

class ToggleObjective
{
    /**
     * Ticks an objective. The session is recorded only when the GM ticks it from the
     * Run screen, because that is the only place with a session in hand; from the quest
     * page there is no session context and the column stays null.
     */
    public function complete(QuestObjective $objective, ?GameSession $session = null): void
    {
        $objective->update([
            'completed_at' => now(),
            'completed_in_session_id' => $session?->id,
        ]);
    }

    /**
     * Unticking clears both columns. A step that is not done was not done anywhere.
     */
    public function reopen(QuestObjective $objective): void
    {
        $objective->update([
            'completed_at' => null,
            'completed_in_session_id' => null,
        ]);
    }

    public function toggle(QuestObjective $objective, ?GameSession $session = null): void
    {
        $objective->isComplete()
            ? $this->reopen($objective)
            : $this->complete($objective, $session);
    }
}
