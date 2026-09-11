<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The generator set a table was copied from, or null for one the GM wrote.
 *
 * The slice 3 migration recorded why the built-in tables do not live here with a
 * null campaign_id: the campaign scope would drop them silently. So a set is copied
 * into the campaign, the copies are ordinary rows the GM may edit, and this column
 * is the one fact that survives the copy: which set it came from, so the tables
 * index can say the set is in and the round trip can carry it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('random_tables', function (Blueprint $table) {
            $table->string('generator_key', 40)->nullable()->after('description');
            $table->index(['campaign_id', 'generator_key']);
        });
    }

    public function down(): void
    {
        Schema::table('random_tables', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'generator_key']);
            $table->dropColumn('generator_key');
        });
    }
};
