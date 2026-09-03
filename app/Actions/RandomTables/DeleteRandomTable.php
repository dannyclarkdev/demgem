<?php

namespace App\Actions\RandomTables;

use App\Models\RandomTable;
use Illuminate\Support\Facades\DB;

class DeleteRandomTable
{
    /**
     * A hard delete. Entries go with it, and any entry elsewhere that nested this table
     * degrades to plain text, which is the whole reason nested_table_id is a real
     * foreign key with nullOnDelete rather than a soft-deleted reference.
     */
    public function handle(RandomTable $table): void
    {
        DB::transaction(function () use ($table): void {
            $table->nestedBy()->update(['nested_table_id' => null]);
            $table->entries()->delete();
            $table->delete();
        });
    }
}
