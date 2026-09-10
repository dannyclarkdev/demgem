<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\CombatantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row in the turn order. Its name and stats are copied from the entity when it is
 * added, so a deleted NPC still leaves a complete row.
 *
 * Conditions are free text with a suggested list in the UI. The tracker is
 * system-light by design, and a fixed condition list is a ruleset decision.
 *
 * player_visible decides what the party sees of the row on /table, and healthWord()
 * decides how much of the number they get. Both answers are read on the server under
 * the viewer's own role, never sent over the wire. Death saves are the one exception,
 * and deathSavesVisibleToPlayers() is where the argument for it lives.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $encounter_id
 * @property string|null $entity_id
 * @property string|null $stat_block_id
 * @property string $name
 * @property int|null $initiative
 * @property int|null $initiative_bonus
 * @property int|null $hp
 * @property int|null $max_hp
 * @property int|null $ac
 * @property list<string>|null $conditions
 * @property string|null $concentrating_on
 * @property int $death_save_successes
 * @property int $death_save_failures
 * @property int|null $legendary_actions_max
 * @property int|null $legendary_actions_left
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Encounter $encounter
 * @property-read Entity|null $entity
 * @property-read StatBlock|null $statBlock
 */
#[Fillable([
    'campaign_id', 'encounter_id', 'entity_id', 'stat_block_id', 'name', 'initiative',
    'initiative_bonus', 'hp', 'max_hp', 'ac', 'conditions', 'concentrating_on',
    'death_save_successes', 'death_save_failures', 'legendary_actions_max',
    'legendary_actions_left', 'position', 'player_visible',
])]
class Combatant extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<CombatantFactory> */
    use HasFactory, HasUlids;

    public const MAX_CONDITIONS = 12;

    public const MAX_CONDITION_LENGTH = 40;

    /** Three of either ends the question, which is the whole mechanic. */
    public const DEATH_SAVES = 3;

    public const MAX_CONCENTRATION_LENGTH = 80;

    public const MAX_LEGENDARY_ACTIONS = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initiative' => 'integer',
            'initiative_bonus' => 'integer',
            'hp' => 'integer',
            'max_hp' => 'integer',
            'ac' => 'integer',
            'position' => 'integer',
            'conditions' => 'array',
            'death_save_successes' => 'integer',
            'death_save_failures' => 'integer',
            'legendary_actions_max' => 'integer',
            'legendary_actions_left' => 'integer',
            'player_visible' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * The creature from the compendium this row was built from, when it was.
     *
     * The numbers on the row are the ones that were copied at the time, so this is only
     * the way back to the prose. A dataset that no longer names the creature leaves the
     * fight intact and the link null.
     *
     * @return BelongsTo<StatBlock, $this>
     */
    public function statBlock(): BelongsTo
    {
        return $this->belongsTo(StatBlock::class);
    }

    /**
     * The NPC or PC this row was built from, when it still exists.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * A PC is a combatant linked to a player character. Roll initiative skips them,
     * because their players roll their own.
     */
    public function isPlayerCharacter(): bool
    {
        return $this->entity->is_pc ?? false;
    }

    public function isDown(): bool
    {
        return $this->hp !== null && $this->hp <= 0;
    }

    /**
     * Whether the party sees this row at all. The GM decides, one eye toggle per row.
     */
    public function isVisibleToPlayers(): bool
    {
        return $this->player_visible;
    }

    /**
     * The one filter for the player table view, the way Entity::visibleTo() is the one
     * filter for everything a player reads.
     *
     * @param  Builder<Combatant>  $query
     * @return Builder<Combatant>
     */
    public function scopeVisibleToPlayers(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('player_visible'), true);
    }

    /**
     * Health as a word, because "the ogre has 43 left" changes how a table plays.
     *
     * Null when the GM tracks no hit points for this row, and null again when they
     * track a current value with no maximum: there is no fraction to read, and a
     * guess would be a lie. Falling to zero is the exception, because that needs no
     * maximum and the whole table can see it happen anyway.
     */
    public function healthWord(): ?string
    {
        if ($this->hp === null) {
            return null;
        }

        if ($this->hp <= 0) {
            return 'Down';
        }

        $max = $this->max_hp;

        if ($max === null || $max <= 0) {
            return null;
        }

        return match (true) {
            $this->hp >= $max => 'Unhurt',
            $this->hp * 4 <= $max => 'Badly hurt',
            default => 'Hurt',
        };
    }

    /**
     * @return list<string>
     */
    public function conditionList(): array
    {
        return $this->conditions ?? [];
    }

    /**
     * Whether this row is holding an effect that damage can break.
     *
     * Null in concentrating_on is the whole answer, so there is no boolean beside it
     * that can come to disagree the first time somebody edits one and not the other.
     */
    public function isConcentrating(): bool
    {
        return filled($this->concentrating_on);
    }

    /**
     * Whether death saves apply to this row at all. Only a row on nought has them, and
     * only while it is on nought: healing above zero is what clears the marks.
     */
    public function isDying(): bool
    {
        return $this->isDown() && ! $this->isStable() && ! $this->isDeadOnSaves();
    }

    public function isStable(): bool
    {
        return $this->death_save_successes >= self::DEATH_SAVES;
    }

    public function isDeadOnSaves(): bool
    {
        return $this->death_save_failures >= self::DEATH_SAVES;
    }

    public function hasDeathSaves(): bool
    {
        return $this->death_save_successes > 0 || $this->death_save_failures > 0;
    }

    /**
     * Whether the party's screen carries this row's death saves.
     *
     * This is a deliberate exception to the rule that a player gets a word and never a
     * number, and the reason is what happens at a real table. Hit points are the GM's
     * information: a player who knows the ogre has 43 left plays differently, which is
     * the whole argument for healthWord(). Death saves are the opposite. A dying
     * character's rolls happen in the open, the table counts them out loud, and the
     * tension of the third one is the point of the mechanic. Hiding them would protect
     * nothing, because the party already knows.
     *
     * The gate itself does not move. A row the GM has not revealed carries nothing,
     * exactly as its hit points do not, so this asks player_visible first.
     */
    public function deathSavesVisibleToPlayers(): bool
    {
        return $this->player_visible && $this->isDown();
    }

    /**
     * Whether this creature has legendary actions to spend. Null is a creature that
     * never had any; zero left is one that has spent them this round.
     */
    public function hasLegendaryActions(): bool
    {
        return $this->legendary_actions_max !== null && $this->legendary_actions_max > 0;
    }
}
