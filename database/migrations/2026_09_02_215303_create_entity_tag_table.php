<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_tag', function (Blueprint $table) {
            $table->foreignUlid('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['entity_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_tag');
    }
};
