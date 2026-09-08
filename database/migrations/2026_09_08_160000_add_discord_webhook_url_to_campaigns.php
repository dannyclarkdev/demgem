<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Discord webhook per campaign. It is a credential: anyone holding it can post
 * to the channel, so the model encrypts it at rest and the export never names it.
 * Text rather than a string column because the ciphertext outgrows the URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->text('discord_webhook_url')->nullable()->after('reminder_lead_hours');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('discord_webhook_url');
        });
    }
};
