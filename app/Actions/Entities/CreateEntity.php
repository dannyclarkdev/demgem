<?php

namespace App\Actions\Entities;

use App\Enums\EntityType;
use App\Enums\Visibility;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateEntity
{
    public function __construct(
        private readonly GenerateSlug $generateSlug,
        private readonly SyncTags $syncTags,
    ) {}

    /**
     * @param  array{
     *     type: EntityType,
     *     name: string,
     *     body?: string|null,
     *     dm_notes?: string|null,
     *     visibility?: Visibility,
     *     parent_id?: string|null,
     *     is_pc?: bool,
     *     player_user_id?: int|null,
     *     tags?: list<string>,
     *     viewer_ids?: list<int>
     * }  $data
     */
    public function handle(Campaign $campaign, User $actor, array $data): Entity
    {
        return DB::transaction(function () use ($campaign, $actor, $data): Entity {
            $entity = $campaign->entities()->create([
                'type' => $data['type'],
                'name' => trim($data['name']),
                'slug' => $this->generateSlug->handle($campaign->id, $data['name']),
                'body' => $data['body'] ?? null,
                'dm_notes' => $data['dm_notes'] ?? null,
                'visibility' => $data['visibility'] ?? Visibility::Dm,
                'parent_id' => $data['parent_id'] ?? null,
                'is_pc' => $data['is_pc'] ?? false,
                'player_user_id' => $data['player_user_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if (array_key_exists('tags', $data)) {
                $this->syncTags->handle($entity, $data['tags']);
            }

            if (array_key_exists('viewer_ids', $data)) {
                $entity->viewers()->sync($data['viewer_ids']);
            }

            return $entity;
        });
    }
}
