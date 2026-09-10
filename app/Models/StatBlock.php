<?php

namespace App\Models;

use Database\Factories\StatBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One creature: either from a ruleset's reference document, or written by a GM.
 *
 * campaign_id is the whole difference. Null is shipped reference data — it belongs to
 * the install, every campaign on that ruleset reads the same rows, demgem:import-srd is
 * its only writer, and it never leaves in an export. Set is one campaign's own creature,
 * which its GMs write, which no other campaign can read, and which exports in full.
 *
 * The prose of a shipped row is licensed material, so it stays behind the compendium's
 * gates and never reaches a campaign row through a combatant. A GM's own prose is their
 * own writing and travels with their campaign. See AddCombatants::fromStatBlock(), which
 * copies numbers and never text either way.
 *
 * @property string $id
 * @property string|null $campaign_id
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
 * @property int|null $legendary_action_uses
 * @property-read Campaign|null $campaign
 */
#[Fillable([
    'campaign_id', 'ruleset', 'slug', 'name', 'source', 'license', 'type_line', 'is_swarm', 'size',
    'creature_type', 'subtype', 'alignment', 'ac', 'initiative_bonus', 'hp', 'hit_dice',
    'speed', 'ability_scores', 'skills', 'senses', 'languages', 'gear', 'resistances',
    'immunities', 'vulnerabilities', 'cr', 'cr_value', 'xp', 'cr_note', 'traits',
    'actions', 'bonus_actions', 'reactions', 'legendary_actions',
    'legendary_action_uses',
])]
class StatBlock extends Model
{
    /** @use HasFactory<StatBlockFactory> */
    use HasFactory, HasUlids;

    /**
     * What a GM's own creature is licensed as: nothing public, just their campaign.
     *
     * A copy of a shipped creature keeps the source and licence it came from instead,
     * so the CC BY attribution follows the words rather than being lost in the copy.
     */
    public const OWN_LICENSE = 'Campaign content';

    /**
     * The columns a GM's form may set. ruleset, slug, campaign_id, source and license
     * are decided by the action rather than typed, so none of them is here.
     *
     * @var list<string>
     */
    public const WRITABLE = [
        'name', 'type_line', 'is_swarm', 'size', 'creature_type', 'subtype', 'alignment',
        'ac', 'initiative_bonus', 'hp', 'hit_dice', 'speed', 'ability_scores', 'skills',
        'senses', 'languages', 'gear', 'resistances', 'immunities', 'vulnerabilities',
        'cr', 'cr_value', 'xp', 'cr_note', 'traits', 'actions', 'bonus_actions',
        'reactions', 'legendary_actions', 'legendary_action_uses',
    ];

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
     * The campaign that wrote this creature, when a GM did.
     *
     * Deliberately not the BelongsToCampaign trait: that adds a global scope keyed to
     * the campaign being viewed, and it would hide every shipped row, which belongs to
     * no campaign at all. The scopes below say which rows a viewer gets instead.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Whether this row came out of the shipped dataset rather than out of a GM.
     */
    public function isShipped(): bool
    {
        return $this->campaign_id === null;
    }

    public function isOwnedBy(Campaign $campaign): bool
    {
        return $this->campaign_id === $campaign->id;
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
     * Reference data, and nothing a GM wrote. demgem:import-srd reads and writes through
     * this so a campaign's own creature can never be updated, orphaned or counted by the
     * loader.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeShipped(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('campaign_id'));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOwnedBy(Builder $query, Campaign $campaign): Builder
    {
        return $query->where($query->qualifyColumn('campaign_id'), $campaign->id);
    }

    /**
     * Everything this campaign can look up: its own creatures always, plus the shipped
     * set when its ruleset has one.
     *
     * A system-agnostic campaign therefore gets a compendium holding exactly what its
     * GM wrote, which is why CampaignPolicy::viewCompendium() no longer asks about the
     * ruleset. The condition is one grouped where, so a caller can add filters after it
     * without the OR swallowing them.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCampaign(Builder $query, Campaign $campaign): Builder
    {
        return $query->where(function (Builder $scoped) use ($campaign): void {
            $scoped->where($scoped->qualifyColumn('campaign_id'), $campaign->id);

            if ($campaign->ruleset->hasCompendium()) {
                $scoped->orWhere(function (Builder $shipped) use ($campaign): void {
                    $shipped->whereNull($shipped->qualifyColumn('campaign_id'))
                        ->where($shipped->qualifyColumn('ruleset'), $campaign->ruleset->value);
                });
            }
        });
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
     * The campaign's own first, then the shipped ones, each half weakest first.
     *
     * A GM who wrote their own goblin meant that one, so it comes first wherever both
     * halves can match: the index, and the tracker's picker.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOwnFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when campaign_id is null then 1 else 0 end')
            ->orderBy('cr_value')
            ->orderBy('name');
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
