<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A typed link between two entities, with the label written from entity_id's side
 * and an optional word for the other side. Directed, because "employer of" is not
 * symmetric.
 *
 * Both ends cascade, unlike a map pin's target: a pin with nothing behind it is
 * still a point on a map, and a relationship with nothing on the other end is
 * nothing. No unique index on the pair, because "employer of" and "hunted by" can
 * both be true of the same two people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_relations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('target_entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('reverse_label', 60)->nullable();
            $table->boolean('player_visible')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['entity_id', 'player_visible']);
            $table->index(['target_entity_id', 'player_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_relations');
    }
};
