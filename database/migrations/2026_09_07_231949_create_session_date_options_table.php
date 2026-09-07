<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The poll. A planned session with no date carries candidate times, and picking one
 * writes scheduled_at and deletes them all. There is no polls table because a poll
 * is not a thing a GM navigates to: it is the state a session is in before Thursday
 * is chosen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_date_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['game_session_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_date_options');
    }
};
