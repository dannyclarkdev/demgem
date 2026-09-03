<?php

namespace App\Actions\Encounters;

use App\Models\Combatant;
use Illuminate\Support\Str;

class SetConditions
{
    /**
     * Replaces the list. Free text with a suggested list in the UI, because the tracker
     * is system-light and a fixed condition list is a ruleset decision.
     *
     * @param  list<string>  $conditions
     */
    public function handle(Combatant $combatant, array $conditions): void
    {
        $combatant->update(['conditions' => $this->clean($conditions)]);
    }

    public function add(Combatant $combatant, string $condition): void
    {
        $this->handle($combatant, [...$combatant->conditionList(), $condition]);
    }

    public function remove(Combatant $combatant, string $condition): void
    {
        $this->handle($combatant, array_values(array_filter(
            $combatant->conditionList(),
            fn (string $existing) => mb_strtolower($existing) !== mb_strtolower(trim($condition)),
        )));
    }

    /**
     * @param  list<string>  $conditions
     * @return list<string>
     */
    private function clean(array $conditions): array
    {
        return collect($conditions)
            ->map(fn (string $condition) => Str::limit(trim($condition), Combatant::MAX_CONDITION_LENGTH, ''))
            ->filter()
            ->unique(fn (string $condition) => mb_strtolower($condition))
            ->take(Combatant::MAX_CONDITIONS)
            ->values()
            ->all();
    }
}
