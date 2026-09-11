<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A decision the party made, and what came of it.
 *
 * A table, not columns on the session: a session holds many of them, each with its
 * own eye, and the consequence is written in sessions later. That is the shape the
 * models rule gives a child table, and it is the clock's shape exactly: a row the GM
 * writes, a player_visible switch, and a link gated separately from the row.
 *
 * game_session_id is where it happened, nullable because a choice made between
 * games is still a choice, and nullOnDelete because the choice outlives the row for
 * the night it was made on.
 *
 * choice and consequence render through MarkdownRenderer, so a [[Name]] links when
 * read. They are not mention sources, like a secret: a rename does not rewrite them
 * and they do not appear in backlinks. That is a known limit, not an oversight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->text('choice');
            $table->text('consequence')->nullable();
            $table->boolean('player_visible')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The log reads a campaign oldest first; the session page asks for its own.
            $table->index(['campaign_id', 'created_at']);
            $table->index('game_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
