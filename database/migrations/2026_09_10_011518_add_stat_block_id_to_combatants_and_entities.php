<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a row was built from.
 *
 * The numbers still live on the combatant, copied at the moment it was added, exactly
 * as they were when a GM typed them. This is the link back to the book, so the tracker
 * can offer "read the stat block" and the entity page can say what an NPC fights as.
 *
 * nullOnDelete, like combatants.entity_id: reference data can be reloaded and a row
 * that vanishes from a later dataset must leave a fight intact rather than break it.
 *
 * On an entity this is a scalar, one-to-one with the row, so it is a column rather than
 * a child table. On the export it becomes a {ruleset, slug} reference: a global id means
 * nothing on the install that reads the file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combatants', function (Blueprint $table) {
            $table->foreignUlid('stat_block_id')->nullable()->after('entity_id')->constrained()->nullOnDelete();
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->foreignUlid('stat_block_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('combatants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stat_block_id');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stat_block_id');
        });
    }
};
