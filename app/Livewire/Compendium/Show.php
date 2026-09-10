<?php

namespace App\Livewire\Compendium;

use App\Actions\Compendium\CopyStatBlock;
use App\Actions\Compendium\DeleteStatBlock;
use App\Actions\Encounters\AddCombatants;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Models\Campaign;
use App\Models\Encounter;
use App\Models\StatBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
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
            ->forCampaign($campaign)
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

    /**
     * A creature into this campaign, so the GM can change it.
     *
     * From a shipped one this is the fastest route to a homebrew ogre, which is an
     * ogre. From the campaign's own it is the plain duplicate a GM reaches for when two
     * monsters are nearly the same. Both land on the editor, because the reason to make
     * a copy is to change it.
     */
    public function copyToCampaign(CopyStatBlock $copy): void
    {
        $this->authorize('create', [StatBlock::class, $this->campaign]);

        $made = $copy->handle($this->campaign, $this->statBlock);

        session()->flash('status', $made->name.' is yours now. Change whatever you like.');

        $this->redirect(route('compendium.edit', [$this->campaign, $made->slug]), navigate: true);
    }

    /**
     * A creature the campaign wrote, gone. A fight already running keeps every number
     * it copied and loses only the way back to the prose.
     */
    public function deleteStatBlock(DeleteStatBlock $delete): void
    {
        $this->authorize('delete', $this->statBlock);

        $name = $this->statBlock->name;

        $delete->handle($this->statBlock);

        session()->flash('status', $name.' deleted. Any fight it is in keeps its numbers.');

        $this->redirect(route('compendium.index', $this->campaign), navigate: true);
    }

    public function render(MarkdownRenderer $renderer): View
    {
        // The prose carries emphasis the book prints ("_Melee Attack Roll:_"), so it
        // goes through the same renderer every other page uses. No wiki links: neither
        // a shipped creature nor a GM's own names an entity in this campaign, and a
        // stat block that linked into the wiki would be a second kind of page.
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
            'isOwn' => ! $this->statBlock->isShipped(),
            'canEdit' => Gate::allows('update', $this->statBlock),
            'canCopy' => Gate::allows('create', [StatBlock::class, $this->campaign]),
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
