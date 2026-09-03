<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The character record: the two facts every system agrees a character has, plus the
 * sheet the player actually plays from. Type-specific columns on entities, like
 * is_pc, player_user_id, and the three quest columns before them.
 *
 * The column is character_class, not class: "class" is reserved in enough dialects to
 * be a nuisance and reads badly beside PHP's keyword in every method that touches it.
 *
 * These three are not DM fields. EntityPolicy::update() already lets a player edit
 * their own PC, and nothing about a class or a level is GM business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('character_class', 60)->nullable()->after('is_pc');
            $table->unsignedSmallInteger('level')->nullable()->after('character_class');
            $table->string('sheet_url', 2048)->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['character_class', 'level', 'sheet_url']);
        });
    }
};
