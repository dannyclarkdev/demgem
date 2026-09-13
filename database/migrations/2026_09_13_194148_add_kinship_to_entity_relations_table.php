<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a relationship means when it is family. One nullable column, because a
 * kinship is one fact about the row and most rows have none: "employer of" is a
 * relationship and not a branch. The tree is drawn from the rows that carry one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->string('kinship', 8)->nullable()->after('reverse_label');
        });
    }

    public function down(): void
    {
        Schema::table('entity_relations', function (Blueprint $table) {
            $table->dropColumn('kinship');
        });
    }
};
