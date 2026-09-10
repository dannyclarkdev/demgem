<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The four things a 5e table tracks on paper because the tracker would not hold them.
 *
 * Every one of these is a scalar, one-to-one with its row, so each is a column rather
 * than a child table. Nothing here is a list, which is the signal .ai/rules/models.md
 * puts the child-table decision on.
 *
 * concentrating_on is the whole concentration story: null is "not concentrating", so
 * there is no second boolean that can come to disagree with it. The death save counts
 * are counts and not a list, because nothing about them is ordered and nothing is
 * individually undone except the last one. The legendary pair is written together or
 * not at all, so both are nullable rather than defaulting to zero: zero left is a
 * creature that has spent them, and null is a creature that never had any.
 *
 * lair_initiative is a plain signed integer to match combatants.initiative. A lair
 * action is GM-written rather than read out of the dataset: the 2024 document does not
 * print one in a shape the tracker could use, and a half-parsed rule is worse than a
 * reminder in the GM's own words.
 *
 * stat_blocks.legendary_action_uses is parsed by demgem:import-srd out of prose the
 * dataset already ships, so the data file and its checksum do not move. Every one of
 * the thirty creatures with legendary actions prints "Legendary Action Uses: 3", and
 * twenty-seven of them add "(4 in Lair)", which is not stored: demgem has no concept
 * of a lair, and a GM raises the number on the row when the fight is in one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combatants', function (Blueprint $table) {
            $table->string('concentrating_on')->nullable()->after('conditions');
            $table->unsignedTinyInteger('death_save_successes')->default(0)->after('concentrating_on');
            $table->unsignedTinyInteger('death_save_failures')->default(0)->after('death_save_successes');
            $table->unsignedTinyInteger('legendary_actions_max')->nullable()->after('death_save_failures');
            $table->unsignedTinyInteger('legendary_actions_left')->nullable()->after('legendary_actions_max');
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->text('lair_action_note')->nullable()->after('round');
            $table->integer('lair_initiative')->nullable()->after('lair_action_note');
        });

        Schema::table('stat_blocks', function (Blueprint $table) {
            $table->unsignedTinyInteger('legendary_action_uses')->nullable()->after('legendary_actions');
        });
    }

    public function down(): void
    {
        Schema::table('combatants', function (Blueprint $table) {
            $table->dropColumn([
                'concentrating_on', 'death_save_successes', 'death_save_failures',
                'legendary_actions_max', 'legendary_actions_left',
            ]);
        });

        Schema::table('encounters', function (Blueprint $table) {
            $table->dropColumn(['lair_action_note', 'lair_initiative']);
        });

        Schema::table('stat_blocks', function (Blueprint $table) {
            $table->dropColumn('legendary_action_uses');
        });
    }
};
