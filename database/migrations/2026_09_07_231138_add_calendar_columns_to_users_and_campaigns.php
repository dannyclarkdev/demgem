<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The calendar feed. A token per user, minted the first time they ask for the link
 * and nullable until then, so nobody carries a credential they never wanted. A
 * length per campaign, because a calendar event needs an end and a session has
 * never had one: a table plays for about as long every week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 40)->nullable()->unique()->after('remember_token');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('session_length_minutes')->default(240)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('session_length_minutes');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
