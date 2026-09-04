<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\ClockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A progress clock. A name, a total, a count, and an eye.
 *
 * player_visible decides whether the party sees the dial at all, and it is read on
 * the server under the viewer's own role. The entity link is gated separately: see
 * scopeVisibleTo() for why a clock and a map pin answer that question differently.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $entity_id
 * @property string $name
 * @property int $segments
 * @property int $filled
 * @property bool $player_visible
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity|null $entity
 */
#[Fillable([
    'campaign_id', 'entity_id', 'name', 'segments', 'filled', 'player_visible', 'position',
])]
class Clock extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<ClockFactory> */
    use HasFactory, HasUlids;

    public const MAX_NAME_LENGTH = 120;

    /**
     * The sizes a GM can pick. Four is the smallest dial worth drawing, and twelve is
     * the largest anybody counts at a glance.
     *
     * @var list<int>
     */
    public const SIZES = [4, 6, 8, 12];

    public const DEFAULT_SEGMENTS = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segments' => 'integer',
            'filled' => 'integer',
            'player_visible' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * What the clock is about. Null for most of them.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * The one filter for the party's panel, in the query as .ai/rules/table.md asks.
     *
     * The row is all this gates. A clock's entity link is gated separately, by loading
     * that relation through Entity::visibleTo(), and the difference from a map pin is
     * deliberate: a pin *is* the link, so a pin whose target is hidden has nothing left
     * to show. A clock only mentions one. A GM who reveals "The Duke's suspicion" meant
     * the party to read those words, so the dial stays and the link goes.
     *
     * @param  Builder<Clock>  $query
     * @return Builder<Clock>
     */
    public function scopeVisibleTo(Builder $query, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('player_visible'), true);
    }

    /**
     * @param  Builder<Clock>  $query
     * @return Builder<Clock>
     */
    public function scopeAbout(Builder $query, Entity $entity): Builder
    {
        return $query->where($query->qualifyColumn('entity_id'), $entity->id);
    }

    public function isComplete(): bool
    {
        return $this->filled >= $this->segments;
    }

    public function isEmpty(): bool
    {
        return $this->filled <= 0;
    }

    public function remaining(): int
    {
        return max(0, $this->segments - $this->filled);
    }

    /**
     * "3 of 6", which is how a table says it.
     */
    public function readout(): string
    {
        return $this->filled.' of '.$this->segments;
    }
}
