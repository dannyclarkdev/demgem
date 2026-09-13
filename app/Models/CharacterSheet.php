<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampaign;
use App\Support\Sheets\FifthEdition;
use Database\Factories\CharacterSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One character's SRD 5.2.1 sheet: what the player decided. Everything the rules
 * decide is a method here that reads the scores and the character's level, and
 * none of it is stored, for the ledger's reason.
 *
 * No gate of its own. The sheet is read by whoever may read the character, and
 * written by whoever may edit the character: EntityPolicy::update(), a GM or the
 * character's player.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $entity_id
 * @property int $strength
 * @property int $dexterity
 * @property int $constitution
 * @property int $intelligence
 * @property int $wisdom
 * @property int $charisma
 * @property list<string> $saving_throws
 * @property list<string> $skills
 * @property list<string> $expertise
 * @property int $hp_max
 * @property int $hp_current
 * @property int $hp_temp
 * @property int $hit_die
 * @property int $hit_dice_spent
 * @property array<int|string, array{total: int, used: int}> $spell_slots JSON hands the levels back as strings.
 * @property string|null $spellcasting_ability
 * @property int|null $armor_class
 * @property int|null $speed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Entity $character
 */
#[Fillable([
    'campaign_id', 'entity_id', 'strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma',
    'saving_throws', 'skills', 'expertise', 'hp_max', 'hp_current', 'hp_temp', 'hit_die', 'hit_dice_spent',
    'spell_slots', 'spellcasting_ability', 'armor_class', 'speed',
])]
class CharacterSheet extends Model
{
    use BelongsToCampaign;

    /** @use HasFactory<CharacterSheetFactory> */
    use HasFactory, HasUlids;

    /** @var array<string, string> Ability key to the column that holds its score. */
    public const SCORE_COLUMNS = [
        'str' => 'strength',
        'dex' => 'dexterity',
        'con' => 'constitution',
        'int' => 'intelligence',
        'wis' => 'wisdom',
        'cha' => 'charisma',
    ];

    /**
     * The column defaults, for an instance that has not been read back from the row.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'saving_throws' => '[]',
        'skills' => '[]',
        'expertise' => '[]',
        'spell_slots' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strength' => 'integer',
            'dexterity' => 'integer',
            'constitution' => 'integer',
            'intelligence' => 'integer',
            'wisdom' => 'integer',
            'charisma' => 'integer',
            'saving_throws' => 'array',
            'skills' => 'array',
            'expertise' => 'array',
            'hp_max' => 'integer',
            'hp_current' => 'integer',
            'hp_temp' => 'integer',
            'hit_die' => 'integer',
            'hit_dice_spent' => 'integer',
            'spell_slots' => 'array',
            'armor_class' => 'integer',
            'speed' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * The character's own level. A character with none yet computes at level one.
     */
    public function level(): int
    {
        return max(1, $this->character->level ?? 1);
    }

    public function score(string $ability): int
    {
        return (int) $this->{self::SCORE_COLUMNS[$ability]};
    }

    public function modifier(string $ability): int
    {
        return FifthEdition::modifier($this->score($ability));
    }

    public function proficiencyBonus(): int
    {
        return FifthEdition::proficiencyBonus($this->level());
    }

    public function hasSaveProficiency(string $ability): bool
    {
        return in_array($ability, $this->saving_throws, true);
    }

    public function saveBonus(string $ability): int
    {
        return FifthEdition::saveBonus($this->score($ability), $this->hasSaveProficiency($ability), $this->level());
    }

    public function hasSkillProficiency(string $skill): bool
    {
        return in_array($skill, $this->skills, true);
    }

    public function hasExpertise(string $skill): bool
    {
        return in_array($skill, $this->expertise, true);
    }

    public function skillBonus(string $skill): int
    {
        return FifthEdition::skillBonus(
            $this->score(FifthEdition::SKILLS[$skill]['ability']),
            $this->hasSkillProficiency($skill),
            $this->hasExpertise($skill),
            $this->level(),
        );
    }

    public function passivePerception(): int
    {
        return 10 + $this->skillBonus('perception');
    }

    public function initiative(): int
    {
        return $this->modifier('dex');
    }

    /**
     * "5d8": the level's worth of the one die size, less what is spent when asked.
     */
    public function hitDiceLeft(): int
    {
        return max(0, $this->level() - $this->hit_dice_spent);
    }

    public function hitDiceLabel(): string
    {
        return $this->level().'d'.$this->hit_die;
    }

    /**
     * The slots, level by level, only the levels with a slot. Keys are ints whatever
     * JSON handed back.
     *
     * @return array<int, array{total: int, used: int}>
     */
    public function slots(): array
    {
        $slots = [];

        foreach ($this->spell_slots as $level => $slot) {
            $total = $slot['total'];

            if ($total > 0) {
                $slots[(int) $level] = ['total' => $total, 'used' => min($total, $slot['used'])];
            }
        }

        ksort($slots);

        return $slots;
    }

    public function hasSlots(): bool
    {
        return $this->slots() !== [];
    }
}
