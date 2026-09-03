<?php

namespace App\Actions\Encounters;

use App\Models\Encounter;
use Illuminate\Support\Facades\DB;

class DeleteEncounter
{
    /**
     * A hard delete, unlike everything else in the app. Nothing links to an encounter,
     * no player sees one, and there is no restore UI to reach it from. Combatants
     * cascade in the database; the explicit delete keeps the intent visible and works
     * the same whether or not the driver enforces the constraint.
     */
    public function handle(Encounter $encounter): void
    {
        DB::transaction(function () use ($encounter): void {
            $encounter->combatants()->delete();
            $encounter->delete();
        });
    }
}
