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
    'active_combatant_id', 'created_by',
])]
class Encounter extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<EncounterFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EncounterStatus::class,
            'round' => 'integer',
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

    public function url(): string
    {
        return route('encounters.show', [$this->campaign_id, $this->id]);
    }
}
