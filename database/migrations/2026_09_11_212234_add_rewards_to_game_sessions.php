<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the party earned in a session: the XP the GM handed out, and the milestone
 * they reached. Two nullable scalars, one row each, so two columns. A table that
 * levels by milestone leaves XP blank and the other way round.
 *
 * The reward log is a query over sessions in order, not a table: the session already
 * carries the recap, and the number belongs beside the story it came from. A player
 * reads a session's reward when they read the session; neither is a DM field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedInteger('xp_awarded')->nullable()->after('arc_id');
            $table->string('milestone', 120)->nullable()->after('xp_awarded');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['xp_awarded', 'milestone']);
        });
    }
};
