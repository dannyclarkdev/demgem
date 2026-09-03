<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pin on a map. Many rows per map, individually placed and individually revealed,
 * which is what .ai/rules/models.md means by "a list gets a child table".
 *
 * entity_id is the map. target_entity_id is what the pin points at, and it is
 * nullable because "here be dragons" is a real annotation with nothing behind it.
 * Two foreign keys to entities on one row, and no cycle between tables.
 *
 * label is copied at creation rather than read through the target, for the reason
 * combatants.name was: a pin whose target is deleted keeps its label and loses its
 * link, instead of vanishing off the map mid-session.
 *
 * x and y are percentages of the image, never pixels. A pixel coordinate breaks the
 * day the GM replaces a 2000px export with a 6000px one, and there is no migration
 * that can fix it afterwards. decimal(6,3) is exact on PostgreSQL and on SQLite, and
 * 0.001% is a sixteenth of a pixel on a 6000px map.
 *
 * There is no position column. Pins have coordinates, not an order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_markers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('target_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('label', 120);
            $table->decimal('x', 6, 3);
            $table->decimal('y', 6, 3);
            $table->boolean('player_visible')->default(false);
            $table->timestamps();

            // The player's map asks both questions at once; the GM's asks neither.
            $table->index(['entity_id', 'player_visible']);
            $table->index('target_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_markers');
    }
};
