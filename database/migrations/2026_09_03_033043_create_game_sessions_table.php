<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game sessions. The table cannot be called "sessions": the database session
 * driver already owns that name, created in the users table migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('title', 120)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 20)->default('planned');
            $table->string('visibility', 20)->default('players');
            $table->text('strong_start')->nullable();
            $table->text('live_notes')->nullable();
            $table->text('recap')->nullable();
            $table->timestamp('recap_published_at')->nullable();
            $table->text('dm_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Trashed rows keep their number, so restoring one never collides.
            $table->unique(['campaign_id', 'number']);
            $table->index(['campaign_id', 'status']);
            $table->index(['campaign_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
