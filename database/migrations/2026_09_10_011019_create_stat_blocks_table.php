<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shipped reference data. There is deliberately no campaign_id: one install has
 * one copy of the SRD, every campaign reads the same rows, and nothing in the
 * application writes them except demgem:import-srd.
 *
 * That also keeps the table out of the export. ExportCoverageTest keeps every table
 * with a campaign_id and asks where it is exported; a global table is not a campaign's
 * to carry, and a campaign that references one exports the reference, never the prose.
 *
 * cr and cr_value are both here because a challenge rating of "1/4" is a label a GM
 * reads and a number the list has to sort and filter by. Storing only the number loses
 * the fraction; storing only the label sorts 10 before 2.
 *
 * The prose columns are json because nothing queries them with like. The rule that put
 * entities.custom_fields in a text column exists because Scout's database engine has no
 * ilike for json, and the compendium is searched by name with a plain query instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stat_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('ruleset', 32);
            $table->string('slug', 160);
            $table->string('name', 160);
            $table->string('source', 64);
            $table->string('license', 32);

            // The line the book prints, kept verbatim for the page: "Large Swarm of
            // Tiny Beasts, Unaligned". size and creature_type are the same line read
            // for the filters, which is why a swarm is a Large Beast in those two.
            $table->string('type_line', 160)->nullable();
            $table->boolean('is_swarm')->default(false);
            $table->string('size', 32)->nullable();
            $table->string('creature_type', 48)->nullable();
            $table->string('subtype', 64)->nullable();
            $table->string('alignment', 64)->nullable();

            $table->unsignedSmallInteger('ac')->nullable();
            $table->smallInteger('initiative_bonus')->nullable();
            $table->unsignedInteger('hp')->nullable();
            $table->string('hit_dice', 48)->nullable();
            $table->string('speed', 160)->nullable();

            $table->json('ability_scores')->nullable();
            $table->string('skills', 255)->nullable();
            $table->string('senses', 255)->nullable();
            $table->string('languages', 255)->nullable();
            $table->string('gear', 255)->nullable();
            $table->string('resistances', 255)->nullable();
            $table->string('immunities', 255)->nullable();
            $table->string('vulnerabilities', 255)->nullable();

            $table->string('cr', 8)->nullable();
            $table->decimal('cr_value', 6, 3)->nullable();
            $table->unsignedInteger('xp')->nullable();
            $table->string('cr_note', 96)->nullable();

            $table->json('traits')->nullable();
            $table->json('actions')->nullable();
            $table->json('bonus_actions')->nullable();
            $table->json('reactions')->nullable();
            $table->json('legendary_actions')->nullable();

            $table->timestamps();

            $table->unique(['ruleset', 'slug']);
            $table->index(['ruleset', 'cr_value']);
            $table->index(['ruleset', 'creature_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_blocks');
    }
};
