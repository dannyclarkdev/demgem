<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entities a GM prepped for a session, bucketed by PrepRole.
 *
 * This is the one campaign-scoped table without a campaign_id. Both sides already
 * carry one, and the only way to write a row is a campaign-scoped, visibility-filtered
 * picker. Adding the column here would duplicate the invariant, not enforce it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_session_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedInteger('position')->default(0);

            // A double-clicked picker must not prep the same NPC twice.
            $table->unique(['game_session_id', 'entity_id', 'role']);
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_entities');
    }
};
