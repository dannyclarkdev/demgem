<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use Database\Factories\CombatantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $id
 * @property string $campaign_id
 * @property string $encounter_id
 * @property string|null $entity_id
 * @property string $name
 * @property int|null $initiative
 * @property int|null $initiative_bonus
 * @property int|null $hp
 * @property int|null $max_hp
 * @property int|null $ac
 * @property list<string>|null $conditions
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Encounter $encounter
 * @property-read Entity|null $entity
 */
#[Fillable([
    'campaign_id', 'encounter_id', 'entity_id', 'name', 'initiative',
    'initiative_bonus', 'hp', 'max_hp', 'ac', 'conditions', 'position',
])]
class Combatant extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<CombatantFactory> */
    use HasFactory, HasUlids;

    public const MAX_CONDITIONS = 12;

    public const MAX_CONDITION_LENGTH = 40;

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
     * @return list<string>
     */
    public function conditionList(): array
    {
        return $this->conditions ?? [];
    }
}
