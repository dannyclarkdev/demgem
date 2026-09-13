<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SRD 5.2.1 character sheet: a ruleset module, one row per character, in its
 * own table rather than as columns on entities, so one ruleset's shape never
 * reaches every campaign's core table.
 *
 * Only what a player decides is stored: the six scores, which saves and skills
 * carry proficiency and expertise, hit points, the hit die and how many are spent,
 * spell slots, armour class and speed. Modifiers, the proficiency bonus, every save
 * and skill bonus, passive perception and initiative are computed on every read.
 *
 * The three lists and the slots are JSON on the row, the calendar's recorded
 * exception: one configuration read whole, replaced whole on save, never queried
 * by row, never gated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_sheets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->unsignedSmallInteger('strength')->default(10);
            $table->unsignedSmallInteger('dexterity')->default(10);
            $table->unsignedSmallInteger('constitution')->default(10);
            $table->unsignedSmallInteger('intelligence')->default(10);
            $table->unsignedSmallInteger('wisdom')->default(10);
            $table->unsignedSmallInteger('charisma')->default(10);
            $table->text('saving_throws');
            $table->text('skills');
            $table->text('expertise');
            $table->unsignedSmallInteger('hp_max')->default(0);
            $table->unsignedSmallInteger('hp_current')->default(0);
            $table->unsignedSmallInteger('hp_temp')->default(0);
            $table->unsignedSmallInteger('hit_die')->default(8);
            $table->unsignedSmallInteger('hit_dice_spent')->default(0);
            $table->text('spell_slots');
            $table->string('spellcasting_ability', 3)->nullable();
            $table->unsignedSmallInteger('armor_class')->nullable();
            $table->unsignedSmallInteger('speed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_sheets');
    }
};
