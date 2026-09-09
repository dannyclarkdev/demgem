<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 120);
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_templates');
    }
};
