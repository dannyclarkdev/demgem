<?php

namespace App\Models;

use App\Enums\EncounterStatus;
use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\EncounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One fight, with its turn order. GM-only in this slice: the player view needs Reverb
 * and arrives with combatants.player_visible in P2.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $game_session_id
 * @property string $name
 * @property EncounterStatus $status
 * @property int $round
 * @property string|null $lair_action_note
 * @property int|null $lair_initiative
 * @property string|null $active_combatant_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read GameSession|null $gameSession
 * @property-read Collection<int, Combatant> $combatants
 */
#[Fillable([
    'campaign_id', 'game_session_id', 'name', 'status', 'round',
    'lair_action_note', 'lair_initiative', 'active_combatant_id', 'created_by',
])]
class Encounter extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<EncounterFactory> */
    use HasFactory, HasUlids;

    /**
     * Where a lair action goes in the order when the GM does not say. Twenty is the
     * count the rules put it on, and a GM who wants it elsewhere types a number.
     */
    public const DEFAULT_LAIR_INITIATIVE = 20;

    public const MAX_LAIR_NOTE_LENGTH = 2000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EncounterStatus::class,
            'round' => 'integer',
            'lair_initiative' => 'integer',
        ];
    }

    /**
     * The turn order. position decides it, never initiative.
     *
     * @return HasMany<Combatant, $this>
     */
    public function combatants(): HasMany
    {
        return $this->hasMany(Combatant::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * Whoever is up. Null before the first turn, and null again if that combatant was
     * removed, because active_combatant_id carries no foreign key to clean it up.
     */
    public function activeCombatant(): ?Combatant
    {
        if ($this->active_combatant_id === null) {
            return null;
        }

        return $this->combatants->firstWhere('id', $this->active_combatant_id)
            ?? Combatant::query()->whereKey($this->active_combatant_id)->first();
    }

    public function isActive(): bool
    {
        return $this->status === EncounterStatus::Active;
    }

    /**
     * @param  Builder<Encounter>  $query
     * @return Builder<Encounter>
     */
    public function scopeForSession(Builder $query, GameSession $session): Builder
    {
        return $query->where($query->qualifyColumn('game_session_id'), $session->id);
    }

    /**
     * Whether this fight has a lair action at all. The note is the switch: a lair
     * action with nothing written on it is nothing to show.
     *
     * Lair actions are the GM's own words rather than dataset content. The 2024
     * document does not print one in a shape the tracker could use, and a half-parsed
     * rule is worse than a reminder written by the person running the fight.
     */
    public function hasLairAction(): bool
    {
        return filled($this->lair_action_note);
    }

    public function lairInitiative(): int
    {
        return $this->lair_initiative ?? self::DEFAULT_LAIR_INITIATIVE;
    }

    /**
     * Which row the lair marker is rendered above, as an index into the turn order.
     *
     * The order is by position and not by initiative, so the marker cannot simply be
     * sorted in. It goes above the first combatant whose initiative is below the lair's
     * count, which is where it would fall if the two were sorted together. A fight
     * nobody has rolled for puts it at the top; a fight where everybody beat it puts it
     * at the end.
     *
     * Null when there is no lair action to place.
     *
     * @param  Collection<int, Combatant>  $combatants
     */
    public function lairMarkerIndex(Collection $combatants): ?int
    {
        if (! $this->hasLairAction()) {
            return null;
        }

        $count = $this->lairInitiative();

        foreach ($combatants->values() as $index => $combatant) {
            if (($combatant->initiative ?? PHP_INT_MIN) < $count) {
                return $index;
            }
        }

        return $combatants->count();
    }

    public function url(): string
    {
        return route('encounters.show', [$this->campaign_id, $this->id]);
    }
}
