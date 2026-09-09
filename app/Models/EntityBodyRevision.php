<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\EntityBodyRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A displaced body. recorded_at is when it was replaced, not when it was written.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property string|null $body
 * @property int|null $replaced_by
 * @property string|null $replaced_by_name
 * @property Carbon $recorded_at
 * @property-read Entity $entity
 * @property-read Campaign $campaign
 */
#[Fillable(['campaign_id', 'entity_id', 'body', 'replaced_by', 'replaced_by_name', 'recorded_at'])]
class EntityBodyRevision extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<EntityBodyRevisionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
