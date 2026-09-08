<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\EntityType;
use App\Http\Resources\Api\V1\Concerns\ReadsTheViewerRole;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\QuestObjective;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An entity as the key holder's role may read it. The index carries the row and its
 * tags; the show route also sets parent, children, relations, and the quest's giver,
 * each already filtered by its own scope, and whenLoaded() puts them in.
 *
 * @mixin Entity
 */
class EntityResource extends JsonResource
{
    use ReadsTheViewerRole;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'body' => $this->body,
            'tags' => $this->tags->pluck('name')->values()->all(),
            'custom_fields' => $this->customFields(),
            'image_url' => $this->imageUrl(),
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->isCharacter()) {
            $data['character'] = [
                'is_pc' => $this->is_pc,
                'player_user_id' => $this->player_user_id,
                'class' => $this->character_class,
                'level' => $this->level,
                'sheet_url' => $this->sheet_url,
            ];
        }

        if ($this->isQuest()) {
            $data['quest'] = [
                'status' => $this->questStatus()?->value,
                'rewards' => $this->rewards,
                'giver' => $this->whenLoaded('giver', fn () => $this->giver === null ? null : self::link($this->giver)),
                'objectives' => $this->whenLoaded('objectives', fn () => $this->objectives
                    ->map(fn (QuestObjective $objective) => [
                        'id' => $objective->id,
                        'position' => $objective->position,
                        'body' => $objective->body,
                        'completed_at' => $objective->completed_at?->toIso8601String(),
                    ])->values()->all()),
            ];
        }

        if ($this->type === EntityType::Event) {
            $data['happens_on'] = $this->happens_on?->toArray();
        }

        $data['parent'] = $this->whenLoaded('parent', fn () => $this->parent === null ? null : self::link($this->parent));
        $data['children'] = $this->whenLoaded('children', fn () => $this->children
            ->map(fn (Entity $child) => self::link($child))->values()->all());
        $data['relations'] = $this->whenLoaded('relations', fn () => $this->relations
            ->map(fn (EntityRelation $relation) => [
                'id' => $relation->id,
                'label' => $relation->label,
                'reverse_label' => $relation->reverse_label,
                'target' => self::link($relation->target),
            ])->values()->all());
        $data['incoming_relations'] = $this->whenLoaded('incomingRelations', fn () => $this->incomingRelations
            ->map(fn (EntityRelation $relation) => [
                'id' => $relation->id,
                'label' => $relation->readsFromTargetAs(),
                'source' => self::link($relation->source),
            ])->values()->all());

        // The keys a player's page never renders. Absent, not null.
        if ($this->isDm()) {
            $data['visibility'] = $this->visibility->value;
            $data['dm_notes'] = $this->dm_notes;
        }

        return $data;
    }

    /**
     * Enough to name another entity and fetch it.
     *
     * @return array{id: string, type: string, name: string, slug: string}
     */
    public static function link(Entity $entity): array
    {
        return [
            'id' => $entity->id,
            'type' => $entity->type->value,
            'name' => $entity->name,
            'slug' => $entity->slug,
        ];
    }
}
