<?php

namespace App\Livewire\Table;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\Combatant;
use App\Models\Encounter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The fight, as the party sees it.
 *
 * One component, two audiences, and the difference is decided here on the server
 * under the viewer's own role. A player gets the rows the GM revealed, in turn order,
 * with health as a word. A GM gets every row and the numbers they are tracking,
 * because a co-GM watching from a second device is still a GM.
 *
 * Nothing a player may not see is ever put in the render: no hidden row, no hit
 * points, no armour class, no initiative. The filter is in the query, so absent from
 * the HTML means absent from everything the request produced.
 *
 * It holds the encounter's id rather than the encounter, and reads the row each
 * render. A player leaves this page open for a whole game, and the GM may delete the
 * fight underneath them; a model property would fail to hydrate on the next round
 * trip, where an id renders "that fight is over" instead. One row per render is a
 * fixed cost. (A model property would not leak the GM's name for the fight either:
 * Livewire stores a model as a class and a key, not as its attributes.)
 *
 * Nested, so it calls enterCampaign() in its own mount: the hydrate hook runs per
 * component, and a member removed mid-fight must stop seeing the fight.
 */
class Fight extends Component
{
    use InteractsWithCampaign;

    public const POLL_SECONDS = 60;

    public string $encounterId = '';

    public function mount(Campaign $campaign, string $encounterId): void
    {
        $this->enterCampaign($campaign);

        $encounter = $this->encounter($encounterId);

        abort_if($encounter === null, 404);

        $this->authorize('viewTable', $encounter);

        $this->encounterId = $encounter->id;
    }

    /**
     * Somebody changed a fight. The re-render is the whole listener, and nothing is
     * read from the payload beyond "which fight".
     *
     * @param  array{encounterId?: string}  $event
     */
    #[On('echo-presence:campaign.{campaign.id},.encounter.changed')]
    public function encounterChanged(array $event): void
    {
        if (($event['encounterId'] ?? null) !== $this->encounterId) {
            $this->skipRender();
        }
    }

    public function render(): View
    {
        $encounter = $this->encounter($this->encounterId);

        if ($encounter === null) {
            // The GM deleted the fight while the party was watching it. The page above
            // drops the panel on its own next render; this one refuses to guess.
            return view('livewire.table.fight', [
                'encounter' => null,
                'combatants' => new Collection,
                'activeId' => null,
                'activeName' => null,
                'hasHiddenTurn' => false,
                'isDm' => $this->isDm(),
                'yours' => [],
                'pollSeconds' => self::POLL_SECONDS,
            ]);
        }

        $combatants = $this->combatants($encounter);
        $activeId = $encounter->active_combatant_id;

        return view('livewire.table.fight', [
            'encounter' => $encounter,
            'combatants' => $combatants,
            'activeId' => $activeId,
            // Named only when this viewer may see the row. A hidden combatant taking
            // its turn is a fact the party gets; its name is not.
            'activeName' => $combatants->firstWhere('id', $activeId)?->name,
            'hasHiddenTurn' => $activeId !== null && $combatants->firstWhere('id', $activeId) === null,
            'isDm' => $this->isDm(),
            'yours' => $this->yourRows($combatants),
            'pollSeconds' => self::POLL_SECONDS,
        ]);
    }

    /**
     * One eager-loaded query, whatever the fight holds. The filter is in the query and
     * not in the template, so a hidden row never reaches the render at all.
     *
     * @return Collection<int, Combatant>
     */
    private function combatants(Encounter $encounter): Collection
    {
        $query = $encounter->combatants()->with('entity');

        if (! $this->isDm()) {
            $query->visibleToPlayers();
        }

        return $query->get();
    }

    /**
     * Which rows are this viewer's own character, so a player can find themselves in
     * the order. Read from the entity already loaded, so it costs no extra query.
     *
     * @param  Collection<int, Combatant>  $combatants
     * @return list<string>
     */
    private function yourRows(Collection $combatants): array
    {
        /** @var User $user */
        $user = auth()->user();

        return $combatants
            ->filter(fn (Combatant $combatant) => $combatant->entity?->player_user_id === $user->id)
            ->pluck('id')
            ->values()
            ->all();
    }

    private function encounter(string $encounterId): ?Encounter
    {
        return Encounter::query()->whereKey($encounterId)->first();
    }
}
