<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ReadsTheViewerRole;
use App\Markdown\Secrets\SecretBlocks;
use App\Models\Entity;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A session as the key holder's role may read it. The schedule and the published recap
 * are the whole of what a player gets, which is what the session page gives them.
 *
 * @mixin GameSession
 */
class SessionResource extends JsonResource
{
    use ReadsTheViewerRole;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role();

        $data = [
            'id' => $this->id,
            'number' => $this->number,
            'label' => $this->label(),
            'title' => $this->title,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'in_game_start' => $this->in_game_start?->toArray(),
            'in_game_end' => $this->in_game_end?->toArray(),
            'arc_id' => $this->arc_id,
            'xp_awarded' => $this->xp_awarded,
            'milestone' => $this->milestone,
            'recap' => $this->isRecapVisibleTo($role) ? ($role->isDm() ? $this->recap : SecretBlocks::strip($this->recap)) : null,
            'recap_published_at' => $this->hasPublishedRecap() ? $this->recap_published_at?->toIso8601String() : null,
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // GameSession::dmOnlyFields() and everything prepared for the night. Absent for
        // a player, not null.
        if ($role->isDm()) {
            $data['visibility'] = $this->visibility->value;
            $data['strong_start'] = $this->strong_start;
            $data['live_notes'] = $this->live_notes;
            $data['dm_notes'] = $this->dm_notes;
            $data['scenes'] = $this->whenLoaded('scenes', fn () => $this->scenes
                ->map(fn (Scene $scene) => [
                    'id' => $scene->id,
                    'position' => $scene->position,
                    'title' => $scene->title,
                    'notes' => $scene->notes,
                ])->values()->all());
            $data['secrets'] = $this->whenLoaded('secrets', fn () => $this->secrets
                ->map(fn (Secret $secret) => [
                    'id' => $secret->id,
                    'position' => $secret->position,
                    'body' => $secret->body,
                    'revealed_at' => $secret->revealed_at?->toIso8601String(),
                    'revealed_in_session_id' => $secret->revealed_in_session_id,
                ])->values()->all());
            $data['prepped'] = $this->whenLoaded('entities', fn () => $this->entities
                ->map(fn (Entity $entity) => [
                    ...EntityResource::link($entity),
                    'role' => $entity->pivot?->getAttribute('role'),
                ])->values()->all());
        }

        return $data;
    }
}
