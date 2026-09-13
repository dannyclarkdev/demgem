<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the television at the end of the table shows. A campaign has one screen with
 * one thing on it, so by the models rule the state is two scalars on the row and not
 * a table: the kind of thing (ScreenFocus) and, for a handout or a map, which page.
 *
 * nullOnDelete covers the hard delete only. An entity soft-deletes, and DeleteEntity
 * clears both columns itself, the way it unfiles an arc's quests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('screen_focus', 16)->nullable()->after('currency');
            $table->foreignUlid('screen_entity_id')->nullable()->after('screen_focus')
                ->constrained('entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('screen_entity_id');
            $table->dropColumn('screen_focus');
        });
    }
};
