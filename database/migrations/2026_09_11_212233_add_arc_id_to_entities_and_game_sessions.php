<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A story arc is an entity, and a quest or a session belongs to one.
 *
 * One column on each table rather than a pivot: a quest sits in one chapter, a
 * session is spent on one, and both are scalars one-to-one with their row. That is
 * the models rule about a list and a scalar, read the scalar way.
 *
 * Both are nullOnDelete. Deleting the arc unchapters the quests and the sessions;
 * it does not take them with it, because the arc was a grouping and not the thing.
 *
 * entities.arc_id is set on a quest only. It is prohibited on an arc, so no chain of
 * arcs forms: nesting arcs is a slice of its own and parent_id is there for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->foreignUlid('arc_id')->nullable()->after('giver_entity_id')->constrained('entities')->nullOnDelete();
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreignUlid('arc_id')->nullable()->after('in_game_end_day')->constrained('entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('arc_id');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('arc_id');
        });
    }
};
