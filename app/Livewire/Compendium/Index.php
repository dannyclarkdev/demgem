<?php

namespace App\Livewire\Compendium;

use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\StatBlock;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every creature in the campaign's ruleset, weakest first.
 *
 * GM-only, and 404 for a system-agnostic campaign: both halves are in
 * CampaignPolicy::viewCompendium(), so neither is a nav condition or an @if.
 *
 * The filters are in the query rather than in the Blade, which is the same rule the
 * table screens are written to. A row a viewer may not have is never loaded.
 */
class Index extends Component
{
    use InteractsWithCampaign, WithPagination;

    public const PER_PAGE = 24;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $creatureType = '';

    #[Url(except: '')]
    public string $challenge = '';

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewCompendium', $campaign);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCreatureType(): void
    {
        $this->resetPage();
    }

    public function updatedChallenge(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'creatureType', 'challenge']);
        $this->resetPage();
    }

    /**
     * The bands a GM actually thinks in when building a fight, rather than 34 exact
     * ratings in a dropdown.
     *
     * @return array<string, array{label: string, min: float|null, max: float|null}>
     */
    public function challengeBands(): array
    {
        return [
            'trivial' => ['label' => 'CR 0 to 1/2', 'min' => 0.0, 'max' => 0.5],
            'low' => ['label' => 'CR 1 to 4', 'min' => 1.0, 'max' => 4.0],
            'mid' => ['label' => 'CR 5 to 10', 'min' => 5.0, 'max' => 10.0],
            'high' => ['label' => 'CR 11 to 16', 'min' => 11.0, 'max' => 16.0],
            'deadly' => ['label' => 'CR 17 and up', 'min' => 17.0, 'max' => null],
        ];
    }

    public function render(): View
    {
        $band = $this->challengeBands()[$this->challenge] ?? null;

        $statBlocks = StatBlock::query()
            ->forRuleset($this->campaign->ruleset->value)
            ->matchingName($this->search)
            ->when(
                $this->creatureType !== '',
                fn ($query) => $query->where('creature_type', $this->creatureType)
            )
            ->inChallengeRange($band['min'] ?? null, $band['max'] ?? null)
            ->inReadingOrder()
            ->paginate(self::PER_PAGE);

        return view('livewire.compendium.index', [
            'statBlocks' => $statBlocks,
            'creatureTypes' => $this->creatureTypes(),
        ])->title('Compendium');
    }

    /**
     * @return list<string>
     */
    private function creatureTypes(): array
    {
        return StatBlock::query()
            ->forRuleset($this->campaign->ruleset->value)
            ->whereNotNull('creature_type')
            ->distinct()
            ->orderBy('creature_type')
            ->pluck('creature_type')
            ->all();
    }
}
