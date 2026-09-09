<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_body_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entity_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('replaced_by_name')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['entity_id', 'recorded_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_body_revisions');
    }
};
