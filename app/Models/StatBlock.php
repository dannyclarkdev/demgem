<?php

namespace App\Models;

use Database\Factories\StatBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One creature from a ruleset's reference document.
 *
 * These rows belong to the install rather than to a campaign, and the application only
 * ever reads them: demgem:import-srd is the single writer, and there is no form, no
 * endpoint and no policy that changes one.
 *
 * The prose here is licensed material, so it stays behind the compendium's gates and
 * never reaches a campaign row. A combatant added from a stat block copies the numbers
 * and keeps a reference; see AddCombatants::fromStatBlock().
 *
 * @property string $id
 * @property string $ruleset
 * @property string $slug
 * @property string $name
 * @property string $source
 * @property string $license
 * @property string|null $type_line
 * @property bool $is_swarm
 * @property string|null $size
 * @property string|null $creature_type
 * @property string|null $subtype
 * @property string|null $alignment
 * @property int|null $ac
 * @property int|null $initiative_bonus
 * @property int|null $hp
 * @property string|null $hit_dice
 * @property string|null $speed
 * @property array<string, array{score: int, mod: string, save: string}>|null $ability_scores
 * @property string|null $skills
 * @property string|null $senses
 * @property string|null $languages
 * @property string|null $gear
 * @property string|null $resistances
 * @property string|null $immunities
 * @property string|null $vulnerabilities
 * @property string|null $cr
 * @property float|null $cr_value
 * @property int|null $xp
 * @property string|null $cr_note
 * @property list<array{name: string|null, text: string}>|null $traits
 * @property list<array{name: string|null, text: string}>|null $actions
 * @property list<array{name: string|null, text: string}>|null $bonus_actions
 * @property list<array{name: string|null, text: string}>|null $reactions
 * @property list<array{name: string|null, text: string}>|null $legendary_actions
 */
#[Fillable([
    'ruleset', 'slug', 'name', 'source', 'license', 'type_line', 'is_swarm', 'size',
    'creature_type', 'subtype', 'alignment', 'ac', 'initiative_bonus', 'hp', 'hit_dice',
    'speed', 'ability_scores', 'skills', 'senses', 'languages', 'gear', 'resistances',
    'immunities', 'vulnerabilities', 'cr', 'cr_value', 'xp', 'cr_note', 'traits',
    'actions', 'bonus_actions', 'reactions', 'legendary_actions',
])]
class StatBlock extends Model
{
    /** @use HasFactory<StatBlockFactory> */
    use HasFactory, HasUlids;

    /**
     * The sections a stat block prints below its numbers, in the order the book prints
     * them. The show page and the API resource both read this, so a new section is one
     * column and one entry rather than two lists that drift apart.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'traits' => 'Traits',
        'actions' => 'Actions',
        'bonus_actions' => 'Bonus Actions',
        'reactions' => 'Reactions',
        'legendary_actions' => 'Legendary Actions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_swarm' => 'boolean',
            'ac' => 'integer',
            'initiative_bonus' => 'integer',
            'hp' => 'integer',
            'cr_value' => 'float',
            'xp' => 'integer',
            'ability_scores' => 'array',
            'traits' => 'array',
            'actions' => 'array',
            'bonus_actions' => 'array',
            'reactions' => 'array',
            'legendary_actions' => 'array',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForRuleset(Builder $query, string $ruleset): Builder
    {
        return $query->where('ruleset', $ruleset);
    }

    /**
     * Name only, the same plain query the entity index uses. Scout is not involved:
     * this is a fixed local dataset browsed with filters, and an index would add a
     * rebuild step to the loader for nothing.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeMatchingName(Builder $query, string $search): Builder
    {
        $search = mb_strtolower(trim($search));

        return $query->when(
            $search !== '',
            fn (Builder $q): Builder => $q->whereRaw('lower(name) like ?', ['%'.$search.'%'])
        );
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInChallengeRange(Builder $query, ?float $min, ?float $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q): Builder => $q->where('cr_value', '>=', $min))
            ->when($max !== null, fn (Builder $q): Builder => $q->where('cr_value', '<=', $max));
    }

    /**
     * Weakest first, then alphabetical, which is the order a GM building a fight reads.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInReadingOrder(Builder $query): Builder
    {
        return $query->orderBy('cr_value')->orderBy('name');
    }

    /**
     * The sections that have entries, keyed by their heading.
     *
     * @return array<string, list<array{name: string|null, text: string}>>
     */
    public function sections(): array
    {
        $sections = [];

        foreach (self::SECTIONS as $column => $heading) {
            $entries = $this->{$column};

            if (is_array($entries) && $entries !== []) {
                $sections[$heading] = $entries;
            }
        }

        return $sections;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
