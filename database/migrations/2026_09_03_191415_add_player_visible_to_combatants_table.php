<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The switch slice 3 named and did not build: which rows of the turn order the party
 * may see on /table.
 *
 * It defaults to false, and AddCombatants flips it to true only for a combatant added
 * from a player character. The surprise round is a real thing, and the first fight
 * where a hidden ambusher appears on the party's screens before it appears in the
 * fiction is the last fight the GM trusts the feature.
 *
 * The index is on (encounter_id, player_visible) because the player table view always
 * asks both questions at once, and the GM's tracker never asks the second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combatants', function (Blueprint $table) {
            $table->boolean('player_visible')->default(false)->after('position');

            $table->index(['encounter_id', 'player_visible']);
        });
    }

    public function down(): void
    {
        Schema::table('combatants', function (Blueprint $table) {
            $table->dropIndex(['encounter_id', 'player_visible']);
            $table->dropColumn('player_visible');
        });
    }
};
