<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a character did between sessions: a list, many rows per character, each
 * with a cost in days, so a child table by the models rule. The total is a sum
 * over the rows on every read and is never stored.
 *
 * No visibility column. A row is visible to whoever may see the character, and
 * DowntimeActivity::scopeVisibleTo() is a whereIn over Entity::visibleTo(). The
 * start date is three integer columns read as one GameDate, the calendar rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downtime_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->string('activity', 120);
            $table->unsignedSmallInteger('days')->default(0);
            $table->text('notes')->nullable();
            $table->integer('starts_year')->nullable();
            $table->integer('starts_month')->nullable();
            $table->integer('starts_day')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtime_activities');
    }
};
