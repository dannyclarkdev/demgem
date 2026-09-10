<?php

namespace App\Livewire\Encounters;

use App\Actions\Encounters\AddCombatants;
use App\Actions\Encounters\ApplyDamage;
use App\Actions\Encounters\DuplicateEncounter;
use App\Actions\Encounters\NextTurn;
use App\Actions\Encounters\RecordDeathSave;
use App\Actions\Encounters\RemoveCombatant;
use App\Actions\Encounters\ReorderCombatants;
use App\Actions\Encounters\RollInitiative;
use App\Actions\Encounters\SetConcentration;
use App\Actions\Encounters\SetConditions;
use App\Actions\Encounters\SetLairAction;
use App\Actions\Encounters\SetPlayerVisibility;
use App\Actions\Encounters\SortByInitiative;
use App\Actions\Encounters\SpendLegendaryAction;
use App\Enums\PrepRole;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use App\Models\StatBlock;
use App\Support\Encounters\Budget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The turn order at the table, read from four feet away.
 *
 * Every change to a fight arrives over the campaign's presence channel and lands
 * here, so a GM advancing the turn moves every screen at the table at once.
 *
 * The poll stays as a backstop at sixty seconds, because a socket drops, a laptop
 * sleeps, and a GM who missed a round would rather wait than refresh. Its three
 * original rules still hold:
 *
 *   1. Nothing is live-bound. Every edit is an explicit action, so neither a poll
 *      nor a broadcast can clobber a value the GM is still typing.
 *   2. .visible, so a backgrounded tab stops.
 *   3. One eager-loaded query per render, asserted in a test.
 *
 * Nested, so it embeds on the Run screen and on its own page, and it calls
 * enterCampaign() in its own mount because the hydrate hook runs per component.
 */
class Tracker extends Component
{
    use InteractsWithCampaign;

    public Encounter $encounter;

    public const POLL_SECONDS = 60;

    public string $newName = '';

    public int $newQuantity = 1;

    public ?int $newHp = null;

    public ?int $newAc = null;

    public ?int $newInitiativeBonus = null;

    /**
     * The compendium picker. It queries only once the GM types, so a fight that never
     * touches it costs the render exactly what it did before the compendium existed.
     *
     * It reads the campaign's whole book, which since slice 17 means the creatures the
     * GM wrote as well as the shipped ones, own first.
     */
    public string $compendiumSearch = '';

    public bool $rollHitPoints = false;

    public ?string $editingConditionsFor = null;

    public string $newCondition = '';

    /** Damage box per combatant, keyed by id. Bound with .blur, never .live. */
    public string $damage = '';

    public ?string $damageFor = null;

    /**
     * The concentration save the last damage asked for, and who owes it.
     *
     * Held for one render rather than stored: it is a prompt, not a fact about the
     * fight, and a GM who has rolled it wants it gone rather than kept.
     */
    public ?int $concentrationDc = null;

    public ?string $concentrationDcFor = null;

    /** The per-row rules panel: what it is holding, and how many actions it gets. */
    public ?string $editingRulesFor = null;

    public string $newConcentration = '';

    public ?int $newLegendaryMax = null;

    public bool $editingLair = false;

    public string $lairNote = '';

    public ?int $lairInitiative = null;

    public const COMMON_CONDITIONS = [
        'Blinded', 'Charmed', 'Concentrating', 'Deafened', 'Frightened', 'Grappled',
        'Invisible', 'Paralysed', 'Poisoned', 'Prone', 'Restrained', 'Stunned', 'Unconscious',
    ];

    public function mount(Campaign $campaign, Encounter $encounter): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('update', $encounter);

        $this->encounter = $encounter;
    }

    public function addCombatant(AddCombatants $addCombatants): void
    {
        $this->authorize('update', $this->encounter);

        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:120'],
            'newQuantity' => ['required', 'integer', 'min:1', 'max:'.AddCombatants::MAX_QUANTITY],
            'newHp' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'newAc' => ['nullable', 'integer', 'min:0', 'max:60'],
            'newInitiativeBonus' => ['nullable', 'integer', 'min:-20', 'max:20'],
        ]);

        $addCombatants->handle(
            $this->encounter,
            $validated['newName'],
            $validated['newQuantity'],
            null,
            $validated['newHp'],
            $validated['newAc'],
            $validated['newInitiativeBonus'],
        );

        $this->reset(['newName', 'newQuantity', 'newHp', 'newAc', 'newInitiativeBonus']);
        $this->newQuantity = 1;
    }

    /**
     * A creature from the compendium, with the book's numbers already on it.
     *
     * The stat block is resolved inside the campaign's own ruleset, so an id from
     * another ruleset is a 404 rather than a row.
     */
    public function addFromCompendium(string $statBlockId, AddCombatants $addCombatants): void
    {
        $this->authorize('update', $this->encounter);
        $this->authorize('viewCompendium', $this->campaign);

        $statBlock = StatBlock::query()
            ->forCampaign($this->campaign)
            ->whereKey($statBlockId)
            ->firstOrFail();

        $quantity = max(1, min($this->newQuantity, AddCombatants::MAX_QUANTITY));

        $addCombatants->fromStatBlock($this->encounter, $statBlock, $quantity, $this->rollHitPoints);

        $this->reset(['compendiumSearch', 'newQuantity']);
        $this->newQuantity = 1;
    }

    public function addEntity(string $entityId, AddCombatants $addCombatants): void
    {
        $this->authorize('update', $this->encounter);

        $entity = Entity::query()->whereKey($entityId)->firstOrFail();

        $addCombatants->handle($this->encounter, $entity->name, 1, $entity);
    }

    public function addParty(AddCombatants $addCombatants): void
    {
        $this->authorize('update', $this->encounter);

        $addCombatants->fromEntities($this->encounter, $this->party());
    }

    public function rollInitiative(RollInitiative $rollInitiative, SortByInitiative $sortByInitiative): void
    {
        $this->authorize('update', $this->encounter);

        $rollInitiative->handle($this->encounter);
        $sortByInitiative->handle($this->encounter);
    }

    public function sortByInitiative(SortByInitiative $sortByInitiative): void
    {
        $this->authorize('update', $this->encounter);

        $sortByInitiative->handle($this->encounter);
    }

    public function setInitiative(string $combatantId, ?int $initiative): void
    {
        $this->authorize('update', $this->encounter);

        $this->combatant($combatantId)->update([
            'initiative' => $initiative === null ? null : max(-99, min(999, $initiative)),
        ]);
    }

    public function nextTurn(NextTurn $nextTurn): void
    {
        $this->authorize('update', $this->encounter);

        $nextTurn->handle($this->encounter);
    }

    public function endEncounter(NextTurn $nextTurn): void
    {
        $this->authorize('update', $this->encounter);

        $nextTurn->end($this->encounter);
    }

    public function reopenEncounter(NextTurn $nextTurn): void
    {
        $this->authorize('update', $this->encounter);

        $nextTurn->reopen($this->encounter);
    }

    public function resetEncounter(NextTurn $nextTurn): void
    {
        $this->authorize('update', $this->encounter);

        $nextTurn->reset($this->encounter);
    }

    public function openDamage(string $combatantId): void
    {
        $this->damageFor = $combatantId;
        $this->damage = '';
    }

    public function closeDamage(): void
    {
        $this->damageFor = null;
        $this->damage = '';
    }

    public function applyDamage(string $combatantId, int $direction, ApplyDamage $applyDamage): void
    {
        $this->authorize('update', $this->encounter);

        $amount = (int) trim($this->damage);

        if ($amount === 0) {
            return;
        }

        $combatant = $this->combatant($combatantId);
        $dc = $applyDamage->handle($combatant, $direction * abs($amount));

        $this->closeDamage();

        // The prompt, not a fact about the fight. The GM rolls it from the drawer that
        // is already beside them and dismisses it.
        $this->concentrationDc = $dc;
        $this->concentrationDcFor = $dc === null ? null : $combatant->id;
    }

    public function dismissConcentrationSave(): void
    {
        $this->concentrationDc = null;
        $this->concentrationDcFor = null;
    }

    /**
     * The per-row rules panel: what this creature is holding, and how many legendary
     * actions it gets. Two fields rather than two panels, because a GM opening either
     * one is looking at the same row for the same reason.
     */
    public function openRules(string $combatantId): void
    {
        $combatant = $this->combatant($combatantId);

        $this->editingRulesFor = $combatantId;
        $this->newConcentration = $combatant->concentrating_on ?? '';
        $this->newLegendaryMax = $combatant->legendary_actions_max;
    }

    public function closeRules(): void
    {
        $this->editingRulesFor = null;
        $this->newConcentration = '';
        $this->newLegendaryMax = null;
    }

    public function saveRules(string $combatantId, SetConcentration $setConcentration, SpendLegendaryAction $legendary): void
    {
        $this->authorize('update', $this->encounter);

        $validated = $this->validate([
            'newConcentration' => ['nullable', 'string', 'max:'.Combatant::MAX_CONCENTRATION_LENGTH],
            'newLegendaryMax' => ['nullable', 'integer', 'min:0', 'max:'.Combatant::MAX_LEGENDARY_ACTIONS],
        ]);

        $combatant = $this->combatant($combatantId);

        $setConcentration->handle($combatant, $validated['newConcentration']);

        if ($validated['newLegendaryMax'] !== $combatant->legendary_actions_max) {
            $legendary->setMaximum($combatant->refresh(), $validated['newLegendaryMax']);
        }

        $this->closeRules();
    }

    public function clearConcentration(string $combatantId, SetConcentration $setConcentration): void
    {
        $this->authorize('update', $this->encounter);

        $setConcentration->clear($this->combatant($combatantId));
    }

    public function spendLegendaryAction(string $combatantId, SpendLegendaryAction $legendary): void
    {
        $this->authorize('update', $this->encounter);

        $legendary->spend($this->combatant($combatantId));
    }

    public function deathSaveSuccess(string $combatantId, RecordDeathSave $deathSaves): void
    {
        $this->authorize('update', $this->encounter);

        $deathSaves->success($this->combatant($combatantId));
    }

    public function deathSaveFailure(string $combatantId, RecordDeathSave $deathSaves): void
    {
        $this->authorize('update', $this->encounter);

        $deathSaves->failure($this->combatant($combatantId));
    }

    public function clearDeathSaves(string $combatantId, RecordDeathSave $deathSaves): void
    {
        $this->authorize('update', $this->encounter);

        $deathSaves->clear($this->combatant($combatantId));
    }

    public function openLair(): void
    {
        $this->editingLair = true;
        $this->lairNote = $this->encounter->lair_action_note ?? '';
        $this->lairInitiative = $this->encounter->lair_initiative ?? Encounter::DEFAULT_LAIR_INITIATIVE;
    }

    public function closeLair(): void
    {
        $this->editingLair = false;
        $this->lairNote = '';
        $this->lairInitiative = null;
    }

    public function saveLair(SetLairAction $setLairAction): void
    {
        $this->authorize('update', $this->encounter);

        $validated = $this->validate([
            'lairNote' => ['nullable', 'string', 'max:'.Encounter::MAX_LAIR_NOTE_LENGTH],
            'lairInitiative' => ['nullable', 'integer', 'min:-99', 'max:999'],
        ]);

        $setLairAction->handle($this->encounter, $validated['lairNote'], $validated['lairInitiative']);

        $this->closeLair();
    }

    /**
     * The same fight again, ready to run. Lands the GM on the copy, because the reason
     * to make one is to look at it.
     */
    public function duplicate(DuplicateEncounter $duplicate): void
    {
        $this->authorize('update', $this->encounter);
        $this->authorize('create', [Encounter::class, $this->campaign]);

        $copy = $duplicate->handle($this->encounter);

        $this->redirect($copy->url(), navigate: true);
    }

    public function openConditions(string $combatantId): void
    {
        $this->editingConditionsFor = $combatantId;
        $this->newCondition = '';
    }

    public function closeConditions(): void
    {
        $this->editingConditionsFor = null;
        $this->newCondition = '';
    }

    public function addCondition(string $combatantId, SetConditions $setConditions): void
    {
        $this->authorize('update', $this->encounter);

        $validated = $this->validate([
            'newCondition' => ['required', 'string', 'max:'.Combatant::MAX_CONDITION_LENGTH],
        ]);

        $setConditions->add($this->combatant($combatantId), $validated['newCondition']);

        $this->newCondition = '';
    }

    public function removeCondition(string $combatantId, string $condition, SetConditions $setConditions): void
    {
        $this->authorize('update', $this->encounter);

        $setConditions->remove($this->combatant($combatantId), $condition);
    }

    public function removeCombatant(string $combatantId, RemoveCombatant $removeCombatant): void
    {
        $this->authorize('update', $this->encounter);

        $removeCombatant->handle($this->combatant($combatantId));
    }

    /**
     * Shows or hides one row on the party's screens. GM only, like everything else
     * here: the tracker authorizes update() on the encounter for every call.
     */
    public function toggleVisibility(string $combatantId, SetPlayerVisibility $setPlayerVisibility): void
    {
        $this->authorize('update', $this->encounter);

        $setPlayerVisibility->toggle($this->combatant($combatantId));
    }

    /**
     * Drag and drop. Livewire hands us the item id and its new zero-based position.
     */
    public function reorder(string $combatantId, int $position, ReorderCombatants $reorder): void
    {
        $this->authorize('update', $this->encounter);

        $reorder->handle($this->encounter, $combatantId, $position);
    }

    public function move(string $combatantId, int $offset, ReorderCombatants $reorder): void
    {
        $this->authorize('update', $this->encounter);

        $reorder->move($this->encounter, $this->combatant($combatantId), $offset);
    }

    /**
     * Somebody else changed this fight.
     *
     * The re-render is the whole point of the listener: it runs on the server under
     * this viewer's own role, so every visibility rule applies exactly as it does on
     * a normal request, and the broadcast never has to carry anything worth hiding.
     *
     * @param  array{encounterId?: string}  $event
     */
    #[On('echo-presence:campaign.{campaign.id},.encounter.changed')]
    public function encounterChanged(array $event): void
    {
        if (($event['encounterId'] ?? null) !== $this->encounter->id) {
            $this->skipRender();
        }
    }

    public function render(): View
    {
        $this->encounter->refresh();

        $combatants = $this->encounter->combatants()->with(['entity', 'statBlock'])->get();
        $party = $this->party();
        $budget = Budget::forParty($this->partyLevels($combatants, $party));
        $spent = $this->spent($combatants);

        return view('livewire.encounters.tracker', [
            'combatants' => $combatants,
            'activeId' => $this->encounter->active_combatant_id,
            'party' => $party,
            'prepped' => $this->preppedMonsters(),
            'hasCompendium' => Gate::allows('viewCompendium', $this->campaign),
            'compendiumResults' => $this->compendiumResults(),
            'commonConditions' => self::COMMON_CONDITIONS,
            'pollSeconds' => self::POLL_SECONDS,
            'budget' => $budget,
            'spent' => $spent,
            'unpriced' => $this->unpriced($combatants),
            'difficulty' => $budget->difficultyFor($spent),
            'lairIndex' => $this->encounter->lairMarkerIndex($combatants),
            'deathSaves' => Combatant::DEATH_SAVES,
        ]);
    }

    /**
     * Whose levels the budget is built from.
     *
     * The characters in this fight, when there are any: that is the party that is
     * actually here, and a GM running a splinter group gets the right number. A fight
     * with nobody in it yet falls back to the campaign's own party, so the read-out
     * says something while the GM is still building.
     *
     * @param  Collection<int, Combatant>  $combatants
     * @param  Collection<int, Entity>  $party
     * @return list<int|null>
     */
    private function partyLevels(Collection $combatants, Collection $party): array
    {
        $inTheFight = $combatants
            ->filter(fn (Combatant $combatant) => $combatant->isPlayerCharacter())
            ->map(fn (Combatant $combatant) => $combatant->entity?->level)
            ->values();

        if ($inTheFight->isNotEmpty()) {
            return $inTheFight->all();
        }

        return $party->map(fn (Entity $entity) => $entity->level)->values()->all();
    }

    /**
     * What the fight costs: the XP of every creature in it the compendium can price.
     *
     * A row the GM typed by hand has no XP to read, and guessing one from its hit
     * points would be a number with nothing behind it. Those rows are counted instead,
     * and the read-out says how many it could not price.
     *
     * @param  Collection<int, Combatant>  $combatants
     */
    private function spent(Collection $combatants): int
    {
        return (int) $combatants
            ->reject(fn (Combatant $combatant) => $combatant->isPlayerCharacter())
            ->sum(fn (Combatant $combatant) => $combatant->statBlock->xp ?? 0);
    }

    /**
     * @param  Collection<int, Combatant>  $combatants
     */
    private function unpriced(Collection $combatants): int
    {
        return $combatants
            ->reject(fn (Combatant $combatant) => $combatant->isPlayerCharacter())
            ->filter(fn (Combatant $combatant) => $combatant->statBlock?->xp === null)
            ->count();
    }

    /**
     * What the GM typed, or nothing at all.
     *
     * An empty box runs no query: the picker is for finding one creature by name, not
     * for paging through the book, and that is what the compendium screen is for.
     *
     * @return Collection<int, StatBlock>
     */
    private function compendiumResults(): Collection
    {
        if (trim($this->compendiumSearch) === '' || ! Gate::allows('viewCompendium', $this->campaign)) {
            return new Collection;
        }

        return StatBlock::query()
            ->forCampaign($this->campaign)
            ->matchingName($this->compendiumSearch)
            ->ownFirst()
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, Entity>
     */
    private function party(): Collection
    {
        return Entity::query()->where('is_pc', true)->orderBy('name')->get();
    }

    /**
     * The Monsters bucket of the session this encounter belongs to, so a prepped fight
     * is one click per monster rather than one form per monster.
     *
     * @return Collection<int, Entity>
     */
    private function preppedMonsters(): Collection
    {
        $session = $this->encounter->gameSession;

        return $session === null
            ? new Collection
            : $session->prepped(PrepRole::Monster)->get();
    }

    private function combatant(string $combatantId): Combatant
    {
        return $this->encounter->combatants()->whereKey($combatantId)->firstOrFail();
    }
}
