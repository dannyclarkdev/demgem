<?php

namespace App\Actions\Entities;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateEntity
{
    public function __construct(
        private readonly GenerateSlug $generateSlug,
        private readonly SyncTags $syncTags,
    ) {}

    /**
     * Only keys present in $data change. A new name gets a new slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Entity $entity, User $actor, array $data): Entity
    {
        return DB::transaction(function () use ($entity, $actor, $data): Entity {
            $entity = Entity::query()->where('campaign_id', $entity->campaign_id)->lockForUpdate()->findOrFail($entity->id);

            if (array_key_exists('body', $data)) {
                $data['body'] = $data['body'] === '' ? null : $data['body'];

                if ($data['body'] !== ($entity->body === '' ? null : $entity->body)) {
                    $entity->bodyRevisions()->create([
                        'campaign_id' => $entity->campaign_id,
                        'body' => $entity->body,
                        'replaced_by' => $actor->id,
                        'replaced_by_name' => $actor->name,
                        'recorded_at' => now(),
                    ]);
                }
            }

            $attributes = collect($data)
                ->only([
                    'name', 'body', 'dm_notes', 'rewards', 'custom_fields', 'visibility', 'parent_id',
                    'is_pc', 'player_user_id', 'character_class', 'level', 'sheet_url',
                    'quest_status', 'giver_entity_id', 'happens_on',
                ])
                ->all();

            if (isset($attributes['name'])) {
                $attributes['name'] = trim($attributes['name']);

                if ($attributes['name'] !== $entity->name) {
                    $attributes['slug'] = $this->generateSlug->handle($entity->campaign_id, $attributes['name'], $entity->id);
                }
            }

            $entity->fill($attributes);
            $entity->updated_by = $actor->id;
            $entity->save();

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
