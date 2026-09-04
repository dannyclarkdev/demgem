<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A progress clock: a named dial cut into segments, filled a wedge at a time.
 *
 * It is a row rather than an entity on purpose. A clock has no body, no slug, no
 * wiki link, and nothing to search, so making it an entity type would buy an index
 * of the string "The ritual" and pay a nullable column on every entity in the
 * campaign. The entity types are for things a GM writes about; a clock is a number
 * a GM turns.
 *
 * segments and filled are counts, not a percentage. The whole point of a clock is
 * that the wedges are countable: "two more" is the sentence a GM says out loud, and
 * a percentage would round three of eight to 38 and lose the noun.
 *
 * There is no direction column. A countdown is this same row read the other way, and
 * the plus and the minus both work whichever way the GM reads the dial, so the name
 * carries the meaning the way it does on paper.
 *
 * There is no completed_at either. Complete is filled >= segments, and a derived fact
 * does not get a column. A secret earns its timestamp because its reveal is a moment
 * in a session's record; a clock fills and empties again all night.
 *
 * entity_id is what the clock is about, and it is nullable because most clocks are
 * about nothing in particular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('segments')->default(6);
            $table->unsignedSmallInteger('filled')->default(0);
            $table->boolean('player_visible')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            // The party's panel asks both questions at once; the GM's asks neither.
            $table->index(['campaign_id', 'player_visible']);
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clocks');
    }
};
