<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quests stay the "quest" entity type. These three columns are type-specific in the
 * same way entities.is_pc and entities.player_user_id are already character-specific:
 * one table keeps wiki links, backlinks, search, tags, and visibility in one place.
 *
 * quest_status has no database default. A default would write "available" onto every
 * character and note as well, so the existing quests are backfilled here instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('quest_status', 20)->nullable()->after('is_pc');
            $table->foreignUlid('giver_entity_id')->nullable()->after('quest_status')
                ->constrained('entities')->nullOnDelete();
            $table->text('rewards')->nullable()->after('dm_notes');

            $table->index(['campaign_id', 'quest_status']);
        });

        DB::table('entities')->where('type', 'quest')->update(['quest_status' => 'available']);
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropForeign(['giver_entity_id']);
            $table->dropIndex(['campaign_id', 'quest_status']);
            $table->dropColumn(['quest_status', 'giver_entity_id', 'rewards']);
        });
    }
};
