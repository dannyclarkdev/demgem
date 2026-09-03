<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One fight. Encounters hard-delete and cascade their combatants: every other object
 * in the app soft-deletes, but an encounter is the one thing with no life after the
 * fight. Nothing links to it, no player sees it, and there is no restore UI.
 *
 * game_session_id is nullable so a GM can build an encounter before the group picks a
 * date, and so a one-off fight needs no session at all. Sessions soft-delete, so
 * nullOnDelete never fires and the link survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 20)->default('planning');
            $table->unsignedInteger('round')->default(0);

            // Deliberately not a foreign key. combatants.encounter_id already points here,
            // so a constraint back to combatants would be circular and need a deferred
            // constraint to create in either order. RemoveCombatant clears this column,
            // and NextTurn treats an id that no longer resolves as "start from the top".
            $table->ulid('active_combatant_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('game_session_id');
            $table->index('active_combatant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
