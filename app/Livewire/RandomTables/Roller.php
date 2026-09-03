<?php

namespace App\Livewire\RandomTables;

use App\Actions\RandomTables\RollRandomTable;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Markdown\MarkdownRenderer;
use App\Markdown\WikiLink\WikiLinkRenderer;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Roll a table from the Run screen drawer. Nested, so a roll re-renders this and not
 * the screen behind it, and it calls enterCampaign() in its own mount.
 *
 * Results are not persisted. dice_rolls is for dice; a table result is prose the GM
 * either uses or discards, and anything worth keeping goes into the live notes. The
 * last ten stay in component state so the drawer is useful while it is open.
 */
class Roller extends Component
{
    use InteractsWithCampaign;

    public const HISTORY_LIMIT = 10;

    /**
     * The rolled chains, newest first. Each is a list of lines already rendered to text.
     *
     * @var list<array{table: string, roll: int|null, body: string, note: string|null}>
     */
    public array $history = [];

    public function mount(Campaign $campaign): void
    {
        $this->enterCampaign($campaign);
        $this->authorize('useGmTools', $campaign);
    }

    public function roll(string $tableId, RollRandomTable $rollRandomTable, MarkdownRenderer $renderer): void
    {
        $this->authorize('useGmTools', $this->campaign);

        $table = RandomTable::query()->whereKey($tableId)->firstOrFail();

        $this->authorize('view', $table);

        $wikiLinks = WikiLinkRenderer::for($this->campaign, $this->user(), $this->role());
        $lines = [];

        foreach ($rollRandomTable->handle($table) as $result) {
            $lines[] = [
                'table' => $result['table'],
                'roll' => $result['roll'],
                'body' => $result['entry'] === null ? '' : $renderer->renderInline($result['entry']->body, $wikiLinks),
                'note' => $result['note'],
            ];
        }

        $this->history = array_slice([...$lines, ...$this->history], 0, self::HISTORY_LIMIT * 2);
    }

    public function clearHistory(): void
    {
        $this->history = [];
    }

    public function render(): View
    {
        return view('livewire.random-tables.roller', [
            'tables' => $this->tables(),
        ]);
    }

    /**
     * @return Collection<int, RandomTable>
     */
    private function tables(): Collection
    {
        return RandomTable::query()->with('entries')->orderBy('name')->get();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
