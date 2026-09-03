<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The brainstorm's "custom key-value attributes", which no slice built until now.
 *
 * The column is text holding JSON, not a json column, because Scout's database engine
 * searches with "column ilike ?" and PostgreSQL has no ilike for json. A json column
 * would work on SQLite locally and fail in production, which is the exact class of
 * difference this project has already lost time to.
 *
 * It is named custom_fields, never attributes: $model->attributes is Eloquent's own
 * property, and a column of that name would shadow it inside every model method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->text('custom_fields')->nullable()->after('rewards');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
