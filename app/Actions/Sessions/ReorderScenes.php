<?php

namespace App\Actions\Sessions;

use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Support\Facades\DB;

class ReorderScenes
{
    /**
     * Moves one scene to a zero-based position and rewrites every position in the list.
     * Rewriting the lot keeps them contiguous whichever GM wins a simultaneous drag.
     */
    public function handle(GameSession $session, string $sceneId, int $position): void
    {
        DB::transaction(function () use ($session, $sceneId, $position): void {
            $ids = $session->scenes()->pluck('id')->all();
            $from = array_search($sceneId, $ids, true);

            if ($from === false) {
                return;
            }

            array_splice($ids, $from, 1);
            array_splice($ids, max(0, min($position, count($ids))), 0, [$sceneId]);

            foreach ($ids as $index => $id) {
                Scene::query()->whereKey($id)->update(['position' => $index]);
            }
        });
    }

    /**
     * One step up or down, for keyboard and tablet use.
     */
    public function move(GameSession $session, Scene $scene, int $offset): void
    {
        $this->handle($session, $scene->id, $scene->position + $offset);
    }
}
