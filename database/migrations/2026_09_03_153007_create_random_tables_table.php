<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom random tables: rumours, names, weather, loot.
 *
 * campaign_id is NOT nullable in this slice. The brainstorm sketch makes it nullable
 * so built-in global tables can live here, and those are P2. A nullable campaign_id
 * would be filtered out silently by BelongsToCampaign's global scope, so shipping the
 * column before the feature ships a trap. It arrives with the generators, and the
 * scope changes in the same commit.
 *
 * These hard-delete, which is the exception the app makes only here and for
 * encounters. random_table_entries.nested_table_id is a real foreign key with
 * nullOnDelete, so a hard delete degrades a nesting entry to plain text with no code.
 * A soft delete would leave it pointing at a trashed table and add a fourth outcome
 * every roll has to handle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('random_tables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 240)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A GM says "roll the rumour table" and means one table.
            $table->unique(['campaign_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('random_tables');
    }
};
