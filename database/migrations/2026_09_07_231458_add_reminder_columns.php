<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders. The lead time is per campaign and null means off, which is the
 * default because the mailer is `log` until a self-hoster changes it, and a
 * reminder that goes to a log file is worse than none: the GM believes it went.
 *
 * The switch is per member per campaign, theirs to set. The stamp on the session
 * is the whole idempotency story: written when the reminder is queued, cleared by
 * the observer when the date moves, and never touched anywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_lead_hours')->nullable()->after('session_length_minutes');
        });

        Schema::table('campaign_members', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(true)->after('role');
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('campaign_members', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('reminder_lead_hours');
        });
    }
};
