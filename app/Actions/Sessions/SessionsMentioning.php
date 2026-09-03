<?php

namespace App\Actions\Sessions;

use App\Enums\CampaignRole;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Mention;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The sessions that mention one entity, filtered by what the viewer may see.
 *
 * Read the player branch twice before changing it. A player may only reach a session
 * through a published recap on a session they can see, and only through a mention whose
 * source field is the recap. Scene notes, strong starts, live notes, and GM notes are
 * invisible to them, so a scene mention must never put a session in a player's list.
 */
class SessionsMentioning
{
    /**
     * @return Collection<int, GameSession>
     */
    public function handle(Entity $entity, CampaignRole $role): Collection
    {
        $direct = Mention::query()
            ->where('target_entity_id', $entity->id)
            ->where('source_type', 'game_session')
            ->when(! $role->isDm(), fn (Builder $query) => $query->where('source_field', 'recap'))
            ->pluck('source_id');

        // Scene notes are GM-only, so a player never reaches a session through one.
        $viaScenes = new Collection;

        if ($role->isDm()) {
            $sceneIds = Mention::query()
                ->where('target_entity_id', $entity->id)
                ->where('source_type', 'scene')
                ->pluck('source_id');

            $viaScenes = Scene::query()->whereKey($sceneIds)->pluck('game_session_id');
        }

        $ids = $direct->merge($viaScenes)->unique()->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return GameSession::query()
            ->whereKey($ids)
            ->visibleTo($role)
            ->when(! $role->isDm(), fn (Builder $query) => $query->whereNotNull('recap_published_at'))
            ->orderByDesc('number')
            ->get();
    }
}
