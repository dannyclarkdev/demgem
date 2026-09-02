<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('source');
            $table->string('source_field', 20);
            $table->foreignUlid('target_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('target_name', 120);
            $table->string('target_type', 20)->nullable();

            $table->index(['campaign_id', 'target_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
