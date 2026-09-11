<?php

namespace App\Models;

use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\DecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A choice the party made, and what came of it.
 *
 * Two halves on one row, because the consequence is written sessions later and the
 * choice is what it is a consequence of. player_visible decides whether the party
 * sees the row at all, read on the server under the viewer's own role. The session
 * link is gated separately, the clock's way: a revealed decision from a GM-only
 * session keeps its words and loses the link.
 *
 * Not a mention source, like a secret. The text renders through MarkdownRenderer so
 * a [[Name]] links when read, but a rename does not rewrite it.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $game_session_id
 * @property string $choice
 * @property string|null $consequence
 * @property bool $player_visible
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession|null $gameSession
 */
#[Fillable([
    'campaign_id', 'game_session_id', 'choice', 'consequence', 'player_visible', 'created_by',
])]
class Decision extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<DecisionFactory> */
    use HasFactory, HasUlids;

    public const MAX_LENGTH = 2000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'player_visible' => 'boolean',
        ];
    }

    /**
     * The session it was made in. Null for a choice made between games.
     *
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * The one filter for the party, in the query as .ai/rules/table.md asks. The row
     * is all this gates; the session link is loaded through its own scope.
     *
     * @param  Builder<Decision>  $query
     * @return Builder<Decision>
     */
    public function scopeVisibleTo(Builder $query, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        return $query->where($query->qualifyColumn('player_visible'), true);
    }

    /**
     * @param  Builder<Decision>  $query
     * @return Builder<Decision>
     */
    public function scopeMadeIn(Builder $query, GameSession $session): Builder
    {
        return $query->where($query->qualifyColumn('game_session_id'), $session->id);
    }

    public function hasConsequence(): bool
    {
        return filled($this->consequence);
    }
}
