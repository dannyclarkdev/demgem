<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dice log. GM-only in this slice: a player rolling in the app is worth nothing
 * until other people see it, and the shared log needs Reverb. user_id is already the
 * right shape for that, so nothing here changes when it arrives.
 *
 * game_session_id ties a roll to the night it was made. Sessions soft-delete, so
 * nullOnDelete never fires and the link survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dice_rolls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('formula', 60);
            $table->string('label', 60)->nullable();
            $table->integer('total');
            $table->json('detail');
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dice_rolls');
    }
};
