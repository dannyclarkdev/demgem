<?php

namespace App\Livewire\Compendium;

use App\Actions\Encounters\AddCombatants;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\StatBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * One creature, as the book prints it, with a way into the fight the GM is running.
 *
 * The stat block is resolved by slug inside the campaign's own ruleset, so a GM on one
 * ruleset cannot read another's data by guessing a URL.
 */
class Show extends Component
{
    use InteractsWithCampaign;

    public StatBlock $statBlock;

    public int $quantity = 1;

    public bool $rollHitPoints = false;

    public ?string $encounterId = null;

    public function mount(Campaign $campaign, string $statBlockSlug): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('viewCompendium', $campaign);

        $this->statBlock = StatBlock::query()
            ->forRuleset($campaign->ruleset->value)
            ->where('slug', $statBlockSlug)
            ->firstOrFail();

        $this->encounterId = $this->encounters()->first()?->id;
    }

    public function addToEncounter(AddCombatants $addCombatants): void
    {
        $this->authorize('viewCompendium', $this->campaign);

        $validated = $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.AddCombatants::MAX_QUANTITY],
            'encounterId' => ['required', 'string'],
        ]);

        $encounter = $this->encounters()
            ->where('id', $validated['encounterId'])
            ->firstOrFail();

        $this->authorize('update', $encounter);

        $added = $addCombatants->fromStatBlock(
            $encounter,
            $this->statBlock,
            $validated['quantity'],
            $this->rollHitPoints,
        );

        session()->flash('status', $added->count().' added to '.$encounter->name.'.');

        $this->redirect(route('encounters.show', [$this->campaign, $encounter->id]), navigate: true);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        // The prose carries emphasis the book prints ("_Melee Attack Roll:_"), so it
        // goes through the same renderer every other page uses. No wiki links: a stat
        // block is shipped data and names no entity in this campaign.
        $sections = [];

        foreach ($this->statBlock->sections() as $heading => $entries) {
            $sections[$heading] = array_map(fn (array $entry): array => [
                'name' => $entry['name'],
                'html' => $renderer->render($entry['text']),
            ], $entries);
        }

        return view('livewire.compendium.show', [
            'sections' => $sections,
            'encounters' => $this->encounters()->get(),
        ])->title($this->statBlock->name);
    }

    /**
     * @return Builder<Encounter>
     */
    private function encounters()
    {
        return Encounter::query()
            ->where('campaign_id', $this->campaign->id)
            ->orderByDesc('created_at');
    }
}
