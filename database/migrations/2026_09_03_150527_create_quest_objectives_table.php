<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The steps a quest is made of. Slice 1 named this as the one place a type needs a
 * child table, so entity_id points at the quest entity.
 *
 * completed_in_session_id ties the quest loop to the session loop, the same way
 * secrets.revealed_in_session_id does. It is only set when the tick comes from the
 * Run screen, because that is the only place with a session in hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_objectives', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('body', 200);
            $table->timestamp('completed_at')->nullable();
            // Sessions soft-delete, so nullOnDelete never fires here. An objective keeps
            // its session link through a soft delete and every read filters trashed rows.
            $table->foreignUlid('completed_in_session_id')->nullable()
                ->constrained('game_sessions')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'position']);
            $table->index(['campaign_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_objectives');
    }
};
