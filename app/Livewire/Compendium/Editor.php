<?php

namespace App\Livewire\Compendium;

use App\Actions\Compendium\CreateStatBlock;
use App\Actions\Compendium\UpdateStatBlock;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\StatBlock;
use App\Support\Encounters\Budget;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A creature the GM writes, and the same form to edit it again.
 *
 * Every field but the name is optional, and that is the point rather than an oversight.
 * A system-agnostic campaign writing "Harbour thug" with two traits and no armour class
 * is doing the intended thing; the tracker adds it by name with nothing to copy, which
 * is exactly what a typed row already does.
 *
 * Two shapes are derived rather than asked for, because both are arithmetic a GM would
 * only get wrong:
 *
 *   - An ability modifier is the score. Asking for both invites a stat block whose
 *     numbers disagree with each other.
 *   - The challenge rating's number comes from its label, so "1/4" sorts and filters
 *     without a GM typing 0.25 into a second box.
 *
 * XP is suggested from the rating and then left alone. It is a field because a GM's own
 * creature is worth what they say it is worth, and because the encounter budget reads
 * this column directly.
 */
class Editor extends Component
{
    use InteractsWithCampaign;

    public ?StatBlock $statBlock = null;

    public string $name = '';

    public string $typeLine = '';

    public string $size = '';

    public string $creatureType = '';

    public string $subtype = '';

    public string $alignment = '';

    public bool $isSwarm = false;

    public ?int $ac = null;

    public ?int $initiativeBonus = null;

    public ?int $hp = null;

    public string $hitDice = '';

    public string $speed = '';

    public string $skills = '';

    public string $senses = '';

    public string $languages = '';

    public string $gear = '';

    public string $resistances = '';

    public string $immunities = '';

    public string $vulnerabilities = '';

    public string $cr = '';

    public ?int $xp = null;

    public string $crNote = '';

    public ?int $legendaryActionUses = null;

    /** @var array<string, array{score: int|null, save: string}> */
    public array $abilities = [];

    /** @var array<string, list<array{name: string, text: string}>> */
    public array $sections = [];

    public const ABILITIES = ['str', 'dex', 'con', 'int', 'wis', 'cha'];

    public const MAX_SECTION_ENTRIES = 40;

    public function mount(Campaign $campaign, ?string $statBlockSlug = null): void
    {
        $this->enterCampaign($campaign);

        $this->abilities = collect(self::ABILITIES)
            ->mapWithKeys(fn (string $ability) => [$ability => ['score' => null, 'save' => '']])
            ->all();

        $this->sections = collect(array_keys(StatBlock::SECTIONS))
            ->mapWithKeys(fn (string $section) => [$section => []])
            ->all();

        if ($statBlockSlug === null) {
            $this->authorize('create', [StatBlock::class, $campaign]);

            return;
        }

        $statBlock = StatBlock::query()
            ->forCampaign($campaign)
            ->where('slug', $statBlockSlug)
            ->firstOrFail();

        $this->authorize('update', $statBlock);

        $this->statBlock = $statBlock;
        $this->fillFrom($statBlock);
    }

    /**
     * The rating's number follows its label, so a GM types "1/4" once.
     */
    public function updatedCr(): void
    {
        $value = $this->challengeValue();

        if ($value !== null && $this->xp === null) {
            $this->xp = Budget::xpForChallenge($value);
        }
    }

    public function addSectionEntry(string $section): void
    {
        if (! array_key_exists($section, $this->sections)) {
            return;
        }

        if (count($this->sections[$section]) >= self::MAX_SECTION_ENTRIES) {
            return;
        }

        $this->sections[$section][] = ['name' => '', 'text' => ''];
    }

    public function removeSectionEntry(string $section, int $index): void
    {
        if (! isset($this->sections[$section][$index])) {
            return;
        }

        unset($this->sections[$section][$index]);

        $this->sections[$section] = array_values($this->sections[$section]);
    }

    public function save(CreateStatBlock $create, UpdateStatBlock $update): void
    {
        $this->validate();

        if ($this->statBlock === null) {
            $this->authorize('create', [StatBlock::class, $this->campaign]);

            $statBlock = $create->handle($this->campaign, $this->fields());
        } else {
            $this->authorize('update', $this->statBlock);

            $statBlock = $update->handle($this->statBlock, $this->fields());
        }

        session()->flash('status', $statBlock->name.' saved.');

        $this->redirect(route('compendium.show', [$this->campaign, $statBlock->slug]), navigate: true);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'typeLine' => ['nullable', 'string', 'max:160'],
            'size' => ['nullable', 'string', 'max:32'],
            'creatureType' => ['nullable', 'string', 'max:48'],
            'subtype' => ['nullable', 'string', 'max:64'],
            'alignment' => ['nullable', 'string', 'max:64'],
            'ac' => ['nullable', 'integer', 'min:0', 'max:99'],
            'initiativeBonus' => ['nullable', 'integer', 'min:-20', 'max:20'],
            'hp' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'hitDice' => ['nullable', 'string', 'max:64'],
            'speed' => ['nullable', 'string', 'max:160'],
            'skills' => ['nullable', 'string', 'max:255'],
            'senses' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'string', 'max:255'],
            'gear' => ['nullable', 'string', 'max:255'],
            'resistances' => ['nullable', 'string', 'max:255'],
            'immunities' => ['nullable', 'string', 'max:255'],
            'vulnerabilities' => ['nullable', 'string', 'max:255'],
            'cr' => ['nullable', 'string', 'max:16'],
            'xp' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'crNote' => ['nullable', 'string', 'max:64'],
            'legendaryActionUses' => ['nullable', 'integer', 'min:0', 'max:'.Combatant::MAX_LEGENDARY_ACTIONS],
            'abilities.*.score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'abilities.*.save' => ['nullable', 'string', 'max:8'],
            'sections.*.*.name' => ['nullable', 'string', 'max:120'],
            'sections.*.*.text' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function render(): View
    {
        return view('livewire.compendium.editor', [
            'abilityKeys' => self::ABILITIES,
            'sectionHeadings' => StatBlock::SECTIONS,
        ])->title($this->statBlock === null ? 'New creature' : 'Edit '.$this->statBlock->name);
    }

    /**
     * What the actions write. Empty strings become null, so a field a GM cleared reads
     * as absent rather than as an empty line on the page.
     *
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        return [
            'name' => trim($this->name),
            'type_line' => $this->nullable($this->typeLine),
            'size' => $this->nullable($this->size),
            'creature_type' => $this->nullable($this->creatureType),
            'subtype' => $this->nullable($this->subtype),
            'alignment' => $this->nullable($this->alignment),
            'is_swarm' => $this->isSwarm,
            'ac' => $this->ac,
            'initiative_bonus' => $this->initiativeBonus,
            'hp' => $this->hp,
            'hit_dice' => $this->nullable($this->hitDice),
            'speed' => $this->nullable($this->speed),
            'ability_scores' => $this->abilityScores(),
            'skills' => $this->nullable($this->skills),
            'senses' => $this->nullable($this->senses),
            'languages' => $this->nullable($this->languages),
            'gear' => $this->nullable($this->gear),
            'resistances' => $this->nullable($this->resistances),
            'immunities' => $this->nullable($this->immunities),
            'vulnerabilities' => $this->nullable($this->vulnerabilities),
            'cr' => $this->nullable($this->cr),
            'cr_value' => $this->challengeValue(),
            'xp' => $this->xp,
            'cr_note' => $this->nullable($this->crNote),
            'legendary_action_uses' => $this->legendaryActionUses,
            ...$this->sectionFields(),
        ];
    }

    /**
     * Six scores, the modifier derived from each, and the save defaulting to it.
     *
     * Null when the GM filled none, so a creature with no numbers prints no table
     * rather than a table of dashes.
     *
     * @return array<string, array{score: int, mod: string, save: string}>|null
     */
    private function abilityScores(): ?array
    {
        $scores = [];

        foreach (self::ABILITIES as $ability) {
            $score = $this->abilities[$ability]['score'] ?? null;

            if ($score === null) {
                continue;
            }

            $modifier = (int) floor(($score - 10) / 2);
            $printed = ($modifier >= 0 ? '+' : '').$modifier;
            $save = trim((string) ($this->abilities[$ability]['save'] ?? ''));

            $scores[$ability] = [
                'score' => $score,
                'mod' => $printed,
                'save' => $save === '' ? $printed : $save,
            ];
        }

        return $scores === [] ? null : $scores;
    }

    /**
     * @return array<string, list<array{name: string|null, text: string}>|null>
     */
    private function sectionFields(): array
    {
        $fields = [];

        foreach ($this->sections as $section => $entries) {
            $clean = [];

            foreach ($entries as $entry) {
                $text = trim($entry['text']);

                if ($text === '') {
                    continue;
                }

                $name = trim($entry['name']);

                $clean[] = ['name' => $name === '' ? null : $name, 'text' => $text];
            }

            $fields[$section] = $clean === [] ? null : $clean;
        }

        return $fields;
    }

    /**
     * "1/4" is a label a GM reads and 0.25 is the number the list sorts and filters by.
     * Only the label is typed.
     */
    private function challengeValue(): ?float
    {
        $cr = trim($this->cr);

        if ($cr === '') {
            return null;
        }

        if (preg_match('#^(\d+)\s*/\s*(\d+)$#', $cr, $matches) === 1 && (int) $matches[2] !== 0) {
            return (int) $matches[1] / (int) $matches[2];
        }

        return is_numeric($cr) ? (float) $cr : null;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function fillFrom(StatBlock $statBlock): void
    {
        $this->name = $statBlock->name;
        $this->typeLine = $statBlock->type_line ?? '';
        $this->size = $statBlock->size ?? '';
        $this->creatureType = $statBlock->creature_type ?? '';
        $this->subtype = $statBlock->subtype ?? '';
        $this->alignment = $statBlock->alignment ?? '';
        $this->isSwarm = $statBlock->is_swarm;
        $this->ac = $statBlock->ac;
        $this->initiativeBonus = $statBlock->initiative_bonus;
        $this->hp = $statBlock->hp;
        $this->hitDice = $statBlock->hit_dice ?? '';
        $this->speed = $statBlock->speed ?? '';
        $this->skills = $statBlock->skills ?? '';
        $this->senses = $statBlock->senses ?? '';
        $this->languages = $statBlock->languages ?? '';
        $this->gear = $statBlock->gear ?? '';
        $this->resistances = $statBlock->resistances ?? '';
        $this->immunities = $statBlock->immunities ?? '';
        $this->vulnerabilities = $statBlock->vulnerabilities ?? '';
        $this->cr = $statBlock->cr ?? '';
        $this->xp = $statBlock->xp;
        $this->crNote = $statBlock->cr_note ?? '';
        $this->legendaryActionUses = $statBlock->legendary_action_uses;

        foreach (self::ABILITIES as $ability) {
            $values = $statBlock->ability_scores[$ability] ?? null;

            $this->abilities[$ability] = [
                'score' => $values['score'] ?? null,
                'save' => $values['save'] ?? '',
            ];
        }

        foreach (array_keys(StatBlock::SECTIONS) as $section) {
            /** @var list<array{name: string|null, text: string}> $entries */
            $entries = $statBlock->{$section} ?? [];

            $this->sections[$section] = array_map(
                fn (array $entry): array => ['name' => $entry['name'] ?? '', 'text' => $entry['text']],
                $entries,
            );
        }
    }
}
