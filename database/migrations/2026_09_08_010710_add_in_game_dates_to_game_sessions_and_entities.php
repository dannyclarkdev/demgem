<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day in the world, on a session and on an event.
 *
 * Every date is three integers, year, month and day, never a day number and never
 * a real-world timestamp. A day number only means something under one shape of the
 * calendar, and the GM edits the shape. Three columns sort correctly on their own,
 * so the timeline is an order by, and they keep their meaning when a month is
 * renamed or given a day.
 *
 * The three are nullable as a group: GameDateCast reads them as one value and writes
 * all three or none. A session has a start and an end; an event has one day. Both
 * are scalars on their row, which is where a scalar goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedInteger('in_game_start_year')->nullable()->after('scheduled_at');
            $table->unsignedTinyInteger('in_game_start_month')->nullable()->after('in_game_start_year');
            $table->unsignedSmallInteger('in_game_start_day')->nullable()->after('in_game_start_month');
            $table->unsignedInteger('in_game_end_year')->nullable()->after('in_game_start_day');
            $table->unsignedTinyInteger('in_game_end_month')->nullable()->after('in_game_end_year');
            $table->unsignedSmallInteger('in_game_end_day')->nullable()->after('in_game_end_month');

            // The timeline and the month grid both ask "what starts in this month".
            $table->index(['campaign_id', 'in_game_start_year', 'in_game_start_month'], 'game_sessions_in_game_start_index');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->unsignedInteger('happens_year')->nullable()->after('giver_entity_id');
            $table->unsignedTinyInteger('happens_month')->nullable()->after('happens_year');
            $table->unsignedSmallInteger('happens_day')->nullable()->after('happens_month');

            $table->index(['campaign_id', 'happens_year', 'happens_month'], 'entities_happens_index');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropIndex('game_sessions_in_game_start_index');
            $table->dropColumn([
                'in_game_start_year', 'in_game_start_month', 'in_game_start_day',
                'in_game_end_year', 'in_game_end_month', 'in_game_end_day',
            ]);
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropIndex('entities_happens_index');
            $table->dropColumn(['happens_year', 'happens_month', 'happens_day']);
        });
    }
};
