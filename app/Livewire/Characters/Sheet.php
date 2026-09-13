<?php

namespace App\Livewire\Characters;

use App\Actions\Characters\AdjustHitPoints;
use App\Actions\Characters\LongRest;
use App\Actions\Characters\SaveSheet;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\CharacterSheet;
use App\Models\Entity;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Sheets\FifthEdition;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The SRD 5.2.1 sheet on a character's page. A ruleset module: it mounts only on a
 * campaign whose ruleset has a sheet, and a page on any other ruleset never asks
 * for it.
 *
 * Read by whoever may read the character; written by whoever may edit the
 * character, which is EntityPolicy::update(): a GM, or the character's player.
 * Editing is one form with every field, checked against the bounds. Damage,
 * healing and the long rest are three presses beside it, because a table does
 * those every hour and a form for each would be a chore.
 *
 * Everything derived is computed in the model on every read. The component never
 * stores a modifier.
 *
 * Nested and it writes, so it re-checks membership itself on every round trip.
 */
class Sheet extends Component
{
    use InteractsWithCampaign;

    public Entity $character;

    public bool $editing = false;

    /** @var array<string, int|string> */
    public array $scores = [];

    /** @var list<string> */
    public array $savingThrows = [];

    /** @var list<string> */
    public array $skills = [];

    /** @var list<string> */
    public array $expertise = [];

    public string $hpMax = '';

    public string $hpCurrent = '';

    public string $hpTemp = '0';

    public string $hitDie = '8';

    public string $hitDiceSpent = '0';

    /** @var array<int, array{total: int|string, used: int|string}> */
    public array $spellSlots = [];

    public string $spellcastingAbility = '';

    public string $armorClass = '';

    public string $speed = '';

    public string $damageAmount = '';

    public string $healAmount = '';

    public function mount(Campaign $campaign, Entity $character): void
    {
        $this->enterCampaign($campaign);

        abort_unless($campaign->ruleset->hasCharacterSheet() && $character->isCharacter(), 404);

        $this->authorize('view', $character);

        $this->character = $character;
    }

    public function edit(): void
    {
        $this->authorize('update', $this->character);

        $sheet = $this->sheet();

        if ($sheet === null) {
            $this->scores = array_fill_keys(array_keys(FifthEdition::ABILITIES), 10);
            $this->savingThrows = [];
            $this->skills = [];
            $this->expertise = [];
            $this->hpMax = '';
            $this->hpCurrent = '';
            $this->hpTemp = '0';
            $this->hitDie = '8';
            $this->hitDiceSpent = '0';
            $this->spellcastingAbility = '';
            $this->armorClass = '';
            $this->speed = '';
            $slots = [];
        } else {
            $this->scores = [];

            foreach (array_keys(FifthEdition::ABILITIES) as $ability) {
                $this->scores[$ability] = $sheet->score($ability);
            }

            $this->savingThrows = $sheet->saving_throws;
            $this->skills = $sheet->skills;
            $this->expertise = $sheet->expertise;
            $this->hpMax = (string) $sheet->hp_max;
            $this->hpCurrent = (string) $sheet->hp_current;
            $this->hpTemp = (string) $sheet->hp_temp;
            $this->hitDie = (string) $sheet->hit_die;
            $this->hitDiceSpent = (string) $sheet->hit_dice_spent;
            $this->spellcastingAbility = $sheet->spellcasting_ability ?? '';
            $this->armorClass = (string) ($sheet->armor_class ?? '');
            $this->speed = (string) ($sheet->speed ?? '');
            $slots = $sheet->slots();
        }

        // Every level on the form, so a caster can add a slot at a level that had none.
        $this->spellSlots = [];

        foreach (range(1, FifthEdition::MAX_SLOT_LEVEL) as $level) {
            $this->spellSlots[$level] = $slots[$level] ?? ['total' => 0, 'used' => 0];
        }

        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    public function save(SaveSheet $saveSheet): void
    {
        $this->authorize('update', $this->character);

        $abilities = array_keys(FifthEdition::ABILITIES);
        $skills = array_keys(FifthEdition::SKILLS);

        $validated = $this->validate([
            'scores' => ['required', 'array:'.implode(',', $abilities)],
            'scores.*' => ['required', 'integer', 'min:'.FifthEdition::MIN_SCORE, 'max:'.FifthEdition::MAX_SCORE],
            'savingThrows' => ['array'],
            'savingThrows.*' => [Rule::in($abilities)],
            'skills' => ['array'],
            'skills.*' => [Rule::in($skills)],
            'expertise' => ['array'],
            'expertise.*' => [Rule::in($skills)],
            'hpMax' => ['required', 'integer', 'min:0', 'max:'.FifthEdition::MAX_HIT_POINTS],
            'hpCurrent' => ['required', 'integer', 'min:0', 'lte:hpMax'],
            'hpTemp' => ['required', 'integer', 'min:0', 'max:'.FifthEdition::MAX_HIT_POINTS],
            'hitDie' => ['required', 'integer', Rule::in(FifthEdition::HIT_DICE)],
            'hitDiceSpent' => ['required', 'integer', 'min:0', 'max:'.FifthEdition::MAX_LEVEL],
            'spellSlots' => ['array'],
            'spellSlots.*.total' => ['required', 'integer', 'min:0', 'max:'.FifthEdition::MAX_SLOTS],
            'spellSlots.*.used' => ['required', 'integer', 'min:0', 'max:'.FifthEdition::MAX_SLOTS],
            'spellcastingAbility' => ['nullable', Rule::in($abilities)],
            'armorClass' => ['nullable', 'integer', 'min:0', 'max:'.FifthEdition::MAX_ARMOR_CLASS],
            'speed' => ['nullable', 'integer', 'min:0', 'max:'.FifthEdition::MAX_SPEED],
        ], [
            'hpCurrent.lte' => 'Current hit points cannot be above the maximum.',
            'skills.*.in' => 'That is not a skill the rules name.',
        ]);

        $slots = [];

        foreach ($validated['spellSlots'] ?? [] as $level => $slot) {
            $total = (int) $slot['total'];

            if ($total > 0) {
                $slots[(int) $level] = ['total' => $total, 'used' => min($total, (int) $slot['used'])];
            }
        }

        $parts = [
            'saving_throws' => array_values(array_unique($validated['savingThrows'] ?? [])),
            'skills' => array_values(array_unique($validated['skills'] ?? [])),
            'expertise' => array_values(array_unique($validated['expertise'] ?? [])),
            'hp_max' => (int) $validated['hpMax'],
            'hp_current' => (int) $validated['hpCurrent'],
            'hp_temp' => (int) $validated['hpTemp'],
            'hit_die' => (int) $validated['hitDie'],
            'hit_dice_spent' => (int) $validated['hitDiceSpent'],
            'spell_slots' => $slots,
            'spellcasting_ability' => filled($validated['spellcastingAbility'] ?? null) ? $validated['spellcastingAbility'] : null,
            'armor_class' => filled($validated['armorClass'] ?? null) ? (int) $validated['armorClass'] : null,
            'speed' => filled($validated['speed'] ?? null) ? (int) $validated['speed'] : null,
        ];

        foreach (CharacterSheet::SCORE_COLUMNS as $ability => $column) {
            $parts[$column] = (int) $validated['scores'][$ability];
        }

        $saveSheet->handle($this->character, $parts);

        $this->editing = false;
    }

    public function damage(AdjustHitPoints $adjust): void
    {
        $this->authorize('update', $this->character);

        $sheet = $this->sheet();

        abort_if($sheet === null, 404);

        $validated = $this->validate(['damageAmount' => ['required', 'integer', 'min:1', 'max:'.FifthEdition::MAX_HIT_POINTS]]);

        $adjust->damage($sheet, (int) $validated['damageAmount']);

        $this->reset('damageAmount');
    }

    public function heal(AdjustHitPoints $adjust): void
    {
        $this->authorize('update', $this->character);

        $sheet = $this->sheet();

        abort_if($sheet === null, 404);

        $validated = $this->validate(['healAmount' => ['required', 'integer', 'min:1', 'max:'.FifthEdition::MAX_HIT_POINTS]]);

        $adjust->heal($sheet, (int) $validated['healAmount']);

        $this->reset('healAmount');
    }

    public function longRest(LongRest $longRest): void
    {
        $this->authorize('update', $this->character);

        $sheet = $this->sheet();

        abort_if($sheet === null, 404);

        $longRest->handle($sheet);
    }

    public function render(): View
    {
        $sheet = $this->sheet();

        return view('livewire.characters.sheet', [
            'sheet' => $sheet,
            'canWrite' => $this->user()->can('update', $this->character),
            'abilities' => FifthEdition::ABILITIES,
            'skillList' => FifthEdition::SKILLS,
            'hitDice' => FifthEdition::HIT_DICE,
            'slotLevels' => range(1, FifthEdition::MAX_SLOT_LEVEL),
            'attribution' => config('compendium.attribution'),
            // The party's pack, as the ledger page shows it. There is no second purse.
            'pack' => LedgerEntry::inventory(LedgerEntry::query()->items()->orderBy('created_at')->get()),
        ]);
    }

    private function sheet(): ?CharacterSheet
    {
        $sheet = CharacterSheet::query()->where('entity_id', $this->character->id)->first();

        $sheet?->setRelation('character', $this->character);

        return $sheet;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
