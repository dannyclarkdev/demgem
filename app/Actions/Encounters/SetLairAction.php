<?php

namespace App\Actions\Encounters;

use App\Events\EncounterChanged;
use App\Models\Encounter;
use Illuminate\Support\Str;

class SetLairAction
{
    /**
     * The GM's own reminder, and the count it sits on in the order.
     *
     * Empty text clears both, because the note is the switch: a lair action with
     * nothing written on it is nothing to show, and leaving a stray count behind would
     * put a marker in the order that says nothing.
     */
    public function handle(Encounter $encounter, ?string $note, ?int $initiative = null): void
    {
        $note = $note === null ? null : trim($note);

        if ($note === null || $note === '') {
            $encounter->update(['lair_action_note' => null, 'lair_initiative' => null]);

            EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);

            return;
        }

        $encounter->update([
            'lair_action_note' => Str::limit($note, Encounter::MAX_LAIR_NOTE_LENGTH, ''),
            'lair_initiative' => $initiative === null
                ? Encounter::DEFAULT_LAIR_INITIATIVE
                : max(-99, min(999, $initiative)),
        ]);

        EncounterChanged::dispatch($encounter->campaign_id, $encounter->id);
    }
}
