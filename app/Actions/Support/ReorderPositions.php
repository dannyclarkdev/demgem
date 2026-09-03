<?php

namespace App\Actions\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one reorder path. Scenes, quest objectives, combatants, and random table
 * entries all keep a contiguous zero-based `position`, and all four move rows
 * through here so the behaviour cannot drift between them.
 */
class ReorderPositions
{
    /**
     * Moves one row to a zero-based position and rewrites every position in the list.
     * Rewriting the lot keeps them contiguous whichever GM wins a simultaneous drag.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $ordered  Already sorted by position.
     */
    public function handle(Builder $ordered, string $id, int $position): void
    {
        DB::transaction(function () use ($ordered, $id, $position): void {
            $model = $ordered->getModel();
            $ids = $ordered->pluck($model->getKeyName())->all();
            $from = array_search($id, $ids, true);

            if ($from === false) {
                return;
            }

            array_splice($ids, $from, 1);
            array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

            foreach ($ids as $index => $rowId) {
                $model->newQuery()->whereKey($rowId)->update(['position' => $index]);
            }
        });
    }

    /**
     * One step up or down, for keyboard and tablet use.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $ordered  Already sorted by position.
     */
    public function move(Builder $ordered, string $id, int $currentPosition, int $offset): void
    {
        $this->handle($ordered, $id, $currentPosition + $offset);
    }
}
