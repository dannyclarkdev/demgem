<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\MapMarkerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One pin on one map.
 *
 * Its coordinates are percentages of the image, so the same pair of numbers means the
 * same place on a phone, on a projector, and after the GM replaces the picture with a
 * larger scan.
 *
 * Its label is copied at creation, the way a combatant's name is: a pin whose target
 * is deleted keeps its label and loses its link, rather than disappearing off the map
 * in the middle of a session.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property string|null $target_entity_id
 * @property string $label
 * @property float $x
 * @property float $y
 * @property bool $player_visible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity $map
 * @property-read Entity|null $target
 */
#[Fillable([
    'campaign_id', 'entity_id', 'target_entity_id', 'label', 'x', 'y', 'player_visible',
])]
class MapMarker extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<MapMarkerFactory> */
    use HasFactory, HasUlids;

    public const MAX_LABEL_LENGTH = 120;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'player_visible' => 'boolean',
        ];
    }

    /**
     * The map this pin is on.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * What the pin points at, when it still exists. A pin may point at nothing.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'target_entity_id');
    }

    /**
     * The one filter for a player's map, and it has two gates.
     *
     * The first is the GM's eye. The second is the target's own visibility: a GM who
     * revealed the pin for a GM-only NPC made a mistake, and the app should not turn
     * that into a leak. A pin with no target passes the second gate, because there is
     * nothing behind it to protect.
     *
     * Both gates are in the query, never in the Blade, so a hidden pin is not loaded
     * and therefore not in the HTML, the snapshot, or the DOM.
     *
     * @param  Builder<MapMarker>  $query
     * @return Builder<MapMarker>
     */
    public function scopeVisibleTo(Builder $query, User $user, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        return $query
            ->where($query->qualifyColumn('player_visible'), true)
            ->where(function (Builder $gate) use ($user, $role): void {
                $gate->whereNull($gate->qualifyColumn('target_entity_id'))
                    ->orWhereIn(
                        $gate->qualifyColumn('target_entity_id'),
                        Entity::query()->visibleTo($user, $role)->select('entities.id'),
                    );
            });
    }

    /**
     * Whether the party sees this pin at all, before the target's own rule applies.
     */
    public function isVisibleToPlayers(): bool
    {
        return $this->player_visible;
    }

    /**
     * A pin whose target is another map drills into it. That is the whole of nesting:
     * no parent column, and no second source of truth for which map holds which.
     */
    public function opensAMap(): bool
    {
        return $this->target?->isMap() ?? false;
    }
}
