<?php

namespace App\Livewire\Encounters;

use App\Actions\Encounters\AddCombatants;
use App\Actions\Encounters\ApplyDamage;
use App\Actions\Encounters\NextTurn;
use App\Actions\Encounters\RemoveCombatant;
use App\Actions\Encounters\ReorderCombatants;
use App\Actions\Encounters\RollInitiative;
use App\Actions\Encounters\SetConditions;
use App\Actions\Encounters\SetPlayerVisibility;
use App\Actions\Encounters\SortByInitiative;
use App\Enums\PrepRole;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\Entity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    public ?string $editingConditionsFor = null;

    public string $newCondition = '';

    /** Damage box per combatant, keyed by id. Bound with .blur, never .live. */
    public string $damage = '';

    public ?string $damageFor = null;

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

        $applyDamage->handle($this->combatant($combatantId), $direction * abs($amount));

        $this->closeDamage();
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

        $combatants = $this->encounter->combatants()->with('entity')->get();

        return view('livewire.encounters.tracker', [
            'combatants' => $combatants,
            'activeId' => $this->encounter->active_combatant_id,
            'party' => $this->party(),
            'prepped' => $this->preppedMonsters(),
            'commonConditions' => self::COMMON_CONDITIONS,
            'pollSeconds' => self::POLL_SECONDS,
        ]);
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
