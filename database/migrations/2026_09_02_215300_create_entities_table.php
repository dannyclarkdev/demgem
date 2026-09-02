<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->ulid('id');
            $table->primary('id');
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('body')->nullable();
            $table->text('dm_notes')->nullable();
            $table->string('visibility', 20)->default('dm');
            $table->foreignUlid('parent_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->boolean('is_pc')->default(false);
            $table->foreignId('player_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campaign_id', 'slug']);
            $table->index(['campaign_id', 'type']);
            $table->index(['campaign_id', 'name']);
            $table->index('parent_id');
            $table->index('player_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
