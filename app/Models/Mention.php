<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Models\Concerns\BelongsToCampaign;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One [[wiki link]] found in a source field. Unresolved links keep target_entity_id null
 * and resolve later when an entity with that name is created.
 *
 * @property int $id
 * @property string $campaign_id
 * @property string $source_type
 * @property string $source_id
 * @property string $source_field
 * @property string|null $target_entity_id
 * @property string $target_name
 * @property string|null $target_type
 * @property-read Entity|null $target
 */
#[Fillable(['campaign_id', 'source_type', 'source_id', 'source_field', 'target_entity_id', 'target_name', 'target_type'])]
class Mention extends Model
{
    use BelongsToCampaign;

    public $timestamps = false;

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'target_entity_id');
    }

    public function targetType(): ?EntityType
    {
        return $this->target_type !== null ? EntityType::tryFrom($this->target_type) : null;
    }
}
