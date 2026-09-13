<?php

namespace App\Models;

use App\Casts\GameDateCast;
use App\Enums\CampaignRole;
use App\Models\Concerns\BelongsToCampaign;
use App\Support\Reckoning\GameDate;
use App\Support\Reckoning\Reckoning;
use Database\Factories\DowntimeActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing a character did between sessions, and what it cost in days.
 *
 * Gated by the character's own gate and nothing else: scopeVisibleTo() is a whereIn
 * over Entity::visibleTo(), so a GM-only character's month is gone with the
 * character, and a PC's is read by everyone who may read the PC. The session link
 * is loaded separately through GameSession::visibleTo(), the clock's way.
 *
 * The days are summed on every read, per character and for the campaign, and the
 * end date is computed from the start and the days through the calendar. Neither is
 * stored, for the ledger's reason: a stored total is the second source of truth
 * that drifts.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property string|null $game_session_id
 * @property string $activity
 * @property int $days
 * @property string|null $notes
 * @property GameDate|null $starts_on
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity $character
 * @property-read GameSession|null $gameSession
 */
#[Fillable([
    'campaign_id', 'entity_id', 'game_session_id', 'activity', 'days', 'notes', 'starts_on', 'created_by',
])]
class DowntimeActivity extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<DowntimeActivityFactory> */
    use HasFactory, HasUlids;

    public const MAX_ACTIVITY_LENGTH = 120;

    public const MAX_NOTES_LENGTH = 2000;

    public const MAX_DAYS = 999;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'starts_on' => GameDateCast::class.':starts',
        ];
    }

    /**
     * The character who spent the days.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * The session it happened around. Null for downtime with no session near it.
     *
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * The one filter for a player: the rows of the characters they may see. In the
     * query, as .ai/rules/table.md asks, so a hidden character's row never loads.
     *
     * @param  Builder<DowntimeActivity>  $query
     * @return Builder<DowntimeActivity>
     */
    public function scopeVisibleTo(Builder $query, User $user, CampaignRole $role): Builder
    {
        if ($role->isDm()) {
            return $query;
        }

        return $query->whereIn(
            $query->qualifyColumn('entity_id'),
            Entity::query()->visibleTo($user, $role)->select('entities.id'),
        );
    }

    /**
     * @param  Builder<DowntimeActivity>  $query
     * @return Builder<DowntimeActivity>
     */
    public function scopeForCharacter(Builder $query, Entity $character): Builder
    {
        return $query->where($query->qualifyColumn('entity_id'), $character->id);
    }

    /**
     * @param  Builder<DowntimeActivity>  $query
     * @return Builder<DowntimeActivity>
     */
    public function scopeAround(Builder $query, GameSession $session): Builder
    {
        return $query->where($query->qualifyColumn('game_session_id'), $session->id);
    }

    /**
     * The last day the activity covers: the start plus the days less one, or the
     * start itself for a thing that cost no whole day. Null without a start.
     */
    public function endsOn(Reckoning $reckoning): ?GameDate
    {
        if ($this->starts_on === null) {
            return null;
        }

        return $this->days > 0 ? $reckoning->add($this->starts_on, $this->days - 1) : $this->starts_on;
    }

    /**
     * "18 Harvest 1042 AR to 2 Frost 1042 AR", or the one day. Null without a start.
     */
    public function range(Reckoning $reckoning): ?string
    {
        if ($this->starts_on === null) {
            return null;
        }

        return $reckoning->formatRange($this->starts_on, $this->endsOn($reckoning));
    }

    /**
     * "5 days", "1 day". The sentence the page and the vault both print.
     */
    public static function dayCount(int $days): string
    {
        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    public function hasNotes(): bool
    {
        return filled($this->notes);
    }
}
