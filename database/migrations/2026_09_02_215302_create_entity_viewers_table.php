<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_viewers', function (Blueprint $table) {
            $table->foreignUlid('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['entity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_viewers');
    }
};
