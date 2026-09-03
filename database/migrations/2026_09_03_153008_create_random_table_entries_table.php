<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weights, not dice ranges. A weight of 5 in a total of 100 is rows 01 to 05, and the
 * screen shows that derived range beside every row, so a GM transcribing a published
 * d100 table watches it line up as they type. The table's roll is always d{sum of
 * weights}, which is why random_tables carries no "dice" column: it would be a second
 * source of truth for a number the weights already imply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('random_table_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('random_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->string('body', 300);
            $table->foreignUlid('nested_table_id')->nullable()
                ->constrained('random_tables')->nullOnDelete();
            $table->timestamps();

            $table->index(['random_table_id', 'position']);
            $table->index('nested_table_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('random_table_entries');
    }
};
