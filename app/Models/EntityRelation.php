<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\EntityRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entity to another, with a label from the first one's side.
 *
 * A relationship is a link, so it inherits the map pin's rule: the party sees it
 * when the GM revealed it and the thing on the other end passes Entity::visibleTo().
 * It adds one gate the pin does not need, on the source, because the incoming list
 * on the target's page is built from rows whose source is somebody else.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property string $target_entity_id
 * @property string $label
 * @property string|null $reverse_label
 * @property bool $player_visible
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity $source
 * @property-read Entity $target
 */
#[Fillable([
    'campaign_id', 'entity_id', 'target_entity_id', 'label', 'reverse_label', 'player_visible', 'position',
])]
class EntityRelation extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<EntityRelationFactory> */
    use HasFactory, HasUlids;

    public const MAX_LABEL_LENGTH = 60;

    protected function casts(): array
    {
        return [
            'player_visible' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'target_entity_id');
    }

    /**
     * Both gates, in the query. A GM sees every row; the party sees a revealed row
     * whose two ends they may both see, whichever end they are standing at.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        $visibleEntities = Entity::query()->visibleTo($user, $role)->select('entities.id');

        return $query
            ->where($query->qualifyColumn('player_visible'), true)
            ->whereIn($query->qualifyColumn('entity_id'), $visibleEntities)
            ->whereIn($query->qualifyColumn('target_entity_id'), clone $visibleEntities);
    }

    /**
     * How the row reads from the target's page: the reverse label when there is
     * one, and otherwise the source named first with the label after it.
     */
    public function readsFromTargetAs(): string
    {
        return $this->reverse_label ?? $this->source->name.' · '.$this->label;
    }
}
