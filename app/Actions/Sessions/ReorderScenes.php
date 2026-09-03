<?php

namespace App\Actions\Sessions;

use App\Actions\Support\ReorderPositions;
use App\Models\GameSession;
use App\Models\Scene;

class ReorderScenes
{
    public function __construct(private readonly ReorderPositions $reorder) {}

    /**
     * Moves one scene to a zero-based position and rewrites every position in the list.
     * Rewriting the lot keeps them contiguous whichever GM wins a simultaneous drag.
     */
    public function handle(GameSession $session, string $sceneId, int $position): void
    {
        $this->reorder->handle($session->scenes()->getQuery(), $sceneId, $position);
    }

    /**
     * One step up or down, for keyboard and tablet use.
     */
    public function move(GameSession $session, Scene $scene, int $offset): void
    {
        $this->handle($session, $scene->id, $scene->position + $offset);
    }
}
