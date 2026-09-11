<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The party's purse and pack, as a list of movements.
 *
 * One row per event: coin in or out, or an item picked up or spent. The balance is
 * a sum and the inventory is a group-by, computed on every read and never stored,
 * because a stored total is the second source of truth that drifts.
 *
 * One table for both kinds rather than two. The page is one list in time order,
 * and "found forty gold and a signet in the chest" is two rows of one event. kind
 * says which columns a row uses: amount for coin, item_name and quantity for an item.
 *
 * Nothing here is gated. The purse is the party's; a member who could not read it
 * would have no reason to write to it. The session link is loaded through its own
 * scope, the clock's way.
 *
 * Rows are never edited. A wrong row is deleted and written again, the way a ledger
 * on paper is corrected, so there is no update action and no history to keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('game_session_id')->nullable()->constrained('game_sessions')->nullOnDelete();
            $table->string('kind', 8);
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('item_name', 120)->nullable();
            $table->integer('quantity')->nullable();
            $table->foreignUlid('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
            $table->index('game_session_id');
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
