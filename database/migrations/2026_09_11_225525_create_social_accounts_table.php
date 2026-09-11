<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An identity from another service, linked to one user.
 *
 * A person table, like users: no campaign_id, never exported. One row per provider
 * per user, and a provider's id names one user on this install. The name and the
 * avatar are what the provider gave at link time, shown on the profile card only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_id', 64);
            $table->string('name', 120)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
