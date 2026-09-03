<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A roll behind the screen. The log is shared from this slice on, and a GM still has
 * to roll for things the party must not read a result for.
 *
 * It defaults to false, so every roll is shared unless the GM asks otherwise, and
 * RollDice refuses to set it for anyone but a GM: a private player roll is a roll
 * they did not make.
 *
 * No new index. The log always filters by campaign and orders by time, which the
 * (campaign_id, created_at) index from slice 3 already serves; private only narrows
 * the rows that index already found.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dice_rolls', function (Blueprint $table) {
            $table->boolean('private')->default(false)->after('detail');
        });
    }

    public function down(): void
    {
        Schema::table('dice_rolls', function (Blueprint $table) {
            $table->dropColumn('private');
        });
    }
};
