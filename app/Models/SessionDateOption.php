<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\SessionDateOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One candidate time on a dateless session.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $game_session_id
 * @property Carbon $starts_at
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession $gameSession
 * @property-read Collection<int, SessionDateVote> $votes
 */
#[Fillable(['campaign_id', 'game_session_id', 'starts_at', 'position'])]
class SessionDateOption extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<SessionDateOptionFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @return HasMany<SessionDateVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(SessionDateVote::class);
    }
}
