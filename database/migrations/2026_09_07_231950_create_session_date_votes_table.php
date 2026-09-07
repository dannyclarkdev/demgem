<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A row means "I can make this". No column for the answer, because a member who
 * cannot make a time does not tick it, and a member who has not looked has not
 * looked. The option cascades, so picking a date takes the votes with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_date_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('session_date_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['session_date_option_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_date_votes');
    }
};
