<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secrets and clues belong to the campaign. game_session_id is the session a secret
 * is prepared for; null means it sits in the pool and carries forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('revealed_at')->nullable();
            $table->foreignUlid('revealed_in_session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'revealed_at']);
            $table->index(['game_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
