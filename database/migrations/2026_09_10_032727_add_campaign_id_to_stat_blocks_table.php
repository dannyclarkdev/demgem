<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A campaign's own creatures, in the same table as the shipped ones.
 *
 * This overturns the decision slice 15 recorded in .ai/rules/commands.md, and that rule
 * is rewritten in the same commit. The half of it that was right is still right: a row
 * with a null campaign_id is shipped reference data, is written only by
 * demgem:import-srd, and never leaves the install in an export.
 *
 * The alternative was a second table. It was rejected because combatants.stat_block_id
 * and entities.stat_block_id already point here with nullOnDelete, and a second table
 * would make both columns polymorphic or double them. Every screen, the tracker picker,
 * the budget and the API would carry that fork. One nullable column carries it instead.
 *
 * Two partial indexes, not one over three columns. Postgres treats NULLs as distinct in
 * a unique index, so (campaign_id, ruleset, slug) would happily let the loader write a
 * second shipped goblin. The shipped key therefore says `where campaign_id is null`,
 * and a campaign's own slugs are kept distinct by their own index.
 *
 * Both statements are raw because the schema builder writes no partial index. The
 * syntax is the same on PostgreSQL, which CI runs, and on SQLite, which the local suite
 * runs; .ai/rules/actions.md records what the difference between the two has already
 * cost this project twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stat_blocks', function (Blueprint $table) {
            $table->foreignUlid('campaign_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('stat_blocks', function (Blueprint $table) {
            $table->dropUnique(['ruleset', 'slug']);
        });

        DB::statement('create unique index stat_blocks_shipped_slug_unique on stat_blocks (ruleset, slug) where campaign_id is null');

        Schema::table('stat_blocks', function (Blueprint $table) {
            $table->unique(['campaign_id', 'slug']);
            $table->index(['campaign_id', 'cr_value']);
        });
    }

    public function down(): void
    {
        DB::statement('drop index stat_blocks_shipped_slug_unique');

        Schema::table('stat_blocks', function (Blueprint $table) {
            $table->dropUnique(['campaign_id', 'slug']);
            $table->dropIndex(['campaign_id', 'cr_value']);
            $table->dropConstrainedForeignId('campaign_id');
            $table->unique(['ruleset', 'slug']);
        });
    }
};
