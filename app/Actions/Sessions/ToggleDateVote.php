<?php

namespace App\Actions\Sessions;

use App\Models\SessionDateOption;
use App\Models\User;

class ToggleDateVote
{
    /**
     * A tick, or the tick taken back. Returns whether the member can now make it.
     */
    public function handle(SessionDateOption $option, User $member): bool
    {
        $existing = $option->votes()->where('user_id', $member->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        $option->votes()->create([
            'campaign_id' => $option->campaign_id,
            'user_id' => $member->id,
        ]);

        return true;
    }
}
