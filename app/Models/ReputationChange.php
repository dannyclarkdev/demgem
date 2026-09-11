<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\ReputationChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One moment that changed how a faction feels about the party.
 *
 * The true standing is the sum of every row; the party's is the sum of the rows
 * the GM revealed. player_visible is that eye, and scopeVisibleTo() reads it in the
 * query, the clock's way. The session link is loaded through its own scope.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property string|null $game_session_id
 * @property int $delta
 * @property string|null $reason
 * @property bool $player_visible
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity $faction
 * @property-read GameSession|null $gameSession
 */
#[Fillable([
    'campaign_id', 'entity_id', 'game_session_id', 'delta', 'reason', 'player_visible', 'created_by',
])]
class ReputationChange extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<ReputationChangeFactory> */
    use HasFactory, HasUlids;

    public const MAX_REASON_LENGTH = 200;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'player_visible' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function faction(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @param  Builder<ReputationChange>  $query
     * @return Builder<ReputationChange>
     */
    public function scopeVisibleTo(Builder $query, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('player_visible'), true);
    }

    /**
     * @param  Builder<ReputationChange>  $query
     * @return Builder<ReputationChange>
     */
    public function scopeAbout(Builder $query, Entity $faction): Builder
    {
        return $query->where($query->qualifyColumn('entity_id'), $faction->id);
    }

    /**
     * "+2" or "−1".
     */
    public function signedDelta(): string
    {
        return ($this->delta < 0 ? '−' : '+').abs($this->delta);
    }
}
