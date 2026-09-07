<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member said before a session and whether they were there after it. Two
 * scalars about one pair, so two columns on one row rather than two tables.
 *
 * Keyed on user_id like dice_rolls: a member who leaves and comes back keeps their
 * history, and the export names them the same way either way. Both columns are
 * nullable because a GM can mark somebody present who never answered, and a
 * session that has not been played has no attendance yet. A row with both null is
 * deleted rather than kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rsvp', 10)->nullable();
            $table->boolean('attended')->nullable();
            $table->timestamps();

            $table->unique(['game_session_id', 'user_id']);
            $table->index(['campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_rsvps');
    }
};
