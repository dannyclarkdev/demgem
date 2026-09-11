<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a faction feels about the party, as a log of the moments that changed it.
 *
 * A table, not a column on the faction: the standing is a sum of remembered
 * moments, each with a reason and a session, and half the point is that the party
 * has noticed some of them and not others. player_visible is that eye, gated in the
 * query the clock's way. The true standing is the sum of every row; the party's is
 * the sum of the revealed ones. Both are computed on every read and never stored.
 *
 * delta is never zero. A change of nothing is not a change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reputation_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->smallInteger('delta');
            $table->string('reason', 200)->nullable();
            $table->boolean('player_visible')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'created_at']);
            $table->index('game_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reputation_changes');
    }
};
