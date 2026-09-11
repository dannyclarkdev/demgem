<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one unit the party ledger counts in. A label, never a rate: silver and copper
 * are the GM's arithmetic, and a system-agnostic core carries one unit and prints
 * what the table calls it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('currency', 12)->default('gp')->after('reminder_lead_hours');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
