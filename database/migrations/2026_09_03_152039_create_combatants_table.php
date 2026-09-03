<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row in the turn order. position is the authoritative order; initiative is a
 * number the GM writes down, and "Sort by initiative" rewrites positions from it.
 * Sorting by initiative at read time would make a drag mean something only inside a
 * tie, which no GM will predict.
 *
 * name, hp, max_hp, and ac are copied onto the row rather than read through entity_id,
 * so a combatant whose NPC is deleted mid-campaign still renders completely. Entities
 * soft-delete, so nullOnDelete never fires here either; the relation excludes trashed
 * rows on its own and the link degrades to a plain name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combatants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->smallInteger('initiative')->nullable();
            $table->smallInteger('initiative_bonus')->nullable();
            // Signed, so the systems that track negatives have room when a ruleset lands.
            $table->integer('hp')->nullable();
            $table->unsignedInteger('max_hp')->nullable();
            $table->unsignedSmallInteger('ac')->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['encounter_id', 'position']);
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combatants');
    }
};
