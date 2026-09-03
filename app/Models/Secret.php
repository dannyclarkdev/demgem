<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * One secret or clue. It belongs to the campaign, not to a session: game_session_id
 * says which session it is prepared for, and null means it sits in the pool.
 *
 * An unrevealed secret is still on the table, so carry-forward is a query, not a copy.
 *
 * Secrets are GM-only, revealed or not. They are never indexed as mention sources,
 * because they move between sessions and the rows would need re-scoping on every move.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $game_session_id
 * @property string $body
 * @property int $position
 * @property Carbon|null $revealed_at
 * @property string|null $revealed_in_session_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession|null $gameSession
 * @property-read GameSession|null $revealedInSession
 */
#[Fillable([
    'campaign_id', 'game_session_id', 'body', 'position',
    'revealed_at', 'revealed_in_session_id', 'created_by',
])]
class Secret extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<SecretFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'revealed_at' => 'datetime',
        ];
    }

    /**
     * The session this secret is prepared for.
     *
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * The session it actually came out in.
     *
     * @return BelongsTo<GameSession, $this>
     */
    public function revealedInSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'revealed_in_session_id');
    }

    /**
     * @param  Builder<Secret>  $query
     * @return Builder<Secret>
     */
    public function scopeUnrevealed(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('revealed_at'));
    }

    /**
     * @param  Builder<Secret>  $query
     * @return Builder<Secret>
     */
    public function scopeRevealed(Builder $query): Builder
    {
        return $query->whereNotNull($query->qualifyColumn('revealed_at'));
    }

    /**
     * Unrevealed secrets waiting from the pool or from an earlier session.
     *
     * Earlier means a lower session number. A secret prepped for session 6 must not
     * surface as "carried over" while the GM runs session 5.
     *
     * @param  Builder<Secret>  $query
     * @return Builder<Secret>
     */
    public function scopeCarriedInto(Builder $query, GameSession $session): Builder
    {
        return $query->unrevealed()->where(function (Builder $q) use ($session): void {
            $q->whereNull($q->qualifyColumn('game_session_id'))
                ->orWhereIn($q->qualifyColumn('game_session_id'), function (QueryBuilder $sub) use ($session): void {
                    $sub->select('id')
                        ->from('game_sessions')
                        ->where('campaign_id', $session->campaign_id)
                        ->where('number', '<', $session->number)
                        ->whereNull('deleted_at');
                });
        });
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }
}
