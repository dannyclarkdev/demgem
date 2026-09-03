<?php

namespace App\Livewire\RandomTables;

use App\Actions\RandomTables\DeleteRandomTable;
use App\Actions\Support\ReorderPositions;
use App\Livewire\Concerns\InteractsWithCampaign;
use App\Models\Campaign;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Editing one table. Resolved in mount() after enterCampaign(), and keyed by ULID:
 * a table is a GM tool, not lore, so it is not worth the slug machinery.
 */
class Show extends Component
{
    use InteractsWithCampaign;

    public RandomTable $table;

    public string $name = '';

    public string $description = '';

    public string $newBody = '';

    public int $newWeight = 1;

    public string $newNestedTableId = '';

    public ?string $editingId = null;

    public string $editingBody = '';

    public int $editingWeight = 1;

    public string $editingNestedTableId = '';

    public function mount(Campaign $campaign, string $tableId): void
    {
        $this->enterCampaign($campaign);

        $found = RandomTable::query()->whereKey($tableId)->first();

        abort_if($found === null || ! $this->user()->can('view', $found), 404);

        $this->table = $found;
        $this->name = $found->name;
        $this->description = $found->description ?? '';
    }

    public function save(): void
    {
        $this->authorize('update', $this->table);

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('random_tables', 'name')
                    ->where('campaign_id', $this->campaign->id)
                    ->ignore($this->table->id),
            ],
            'description' => ['nullable', 'string', 'max:240'],
        ]);

        $this->table->update([
            'name' => trim($validated['name']),
            'description' => trim($validated['description'] ?? '') !== '' ? trim($validated['description']) : null,
        ]);

        session()->flash('status', 'Table saved.');
    }

    public function addEntry(): void
    {
        $this->authorize('update', $this->table);

        $validated = $this->validate([
            'newBody' => ['required', 'string', 'max:300'],
            'newWeight' => ['required', 'integer', 'min:1', 'max:'.RandomTableEntry::MAX_WEIGHT],
            'newNestedTableId' => ['nullable', ...$this->nestedRules()],
        ]);

        $this->table->entries()->create([
            'campaign_id' => $this->table->campaign_id,
            'body' => trim($validated['newBody']),
            'weight' => $validated['newWeight'],
            'nested_table_id' => $validated['newNestedTableId'] !== '' ? $validated['newNestedTableId'] : null,
            'position' => $this->nextPosition(),
        ]);

        $this->reset(['newBody', 'newNestedTableId']);
        $this->newWeight = 1;
    }

    public function edit(string $entryId): void
    {
        $this->authorize('update', $this->table);

        $entry = $this->entry($entryId);

        $this->editingId = $entry->id;
        $this->editingBody = $entry->body;
        $this->editingWeight = $entry->weight;
        $this->editingNestedTableId = $entry->nested_table_id ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editingBody', 'editingNestedTableId']);
        $this->editingWeight = 1;
    }

    public function saveEntry(): void
    {
        $this->authorize('update', $this->table);

        $entry = $this->entry((string) $this->editingId);

        $validated = $this->validate([
            'editingBody' => ['required', 'string', 'max:300'],
            'editingWeight' => ['required', 'integer', 'min:1', 'max:'.RandomTableEntry::MAX_WEIGHT],
            'editingNestedTableId' => ['nullable', ...$this->nestedRules()],
        ]);

        $entry->update([
            'body' => trim($validated['editingBody']),
            'weight' => $validated['editingWeight'],
            'nested_table_id' => $validated['editingNestedTableId'] !== '' ? $validated['editingNestedTableId'] : null,
        ]);

        $this->cancelEdit();
    }

    public function removeEntry(string $entryId): void
    {
        $this->authorize('update', $this->table);

        $this->entry($entryId)->delete();

        if ($this->editingId === $entryId) {
            $this->cancelEdit();
        }
    }

    public function reorder(string $entryId, int $position, ReorderPositions $reorder): void
    {
        $this->authorize('update', $this->table);

        $reorder->handle($this->table->entries()->getQuery(), $entryId, $position);
    }

    public function move(string $entryId, int $offset, ReorderPositions $reorder): void
    {
        $this->authorize('update', $this->table);

        $entry = $this->entry($entryId);

        $reorder->move($this->table->entries()->getQuery(), $entry->id, $entry->position, $offset);
    }

    public function delete(DeleteRandomTable $deleteRandomTable): void
    {
        $this->authorize('delete', $this->table);

        $deleteRandomTable->handle($this->table);

        $this->redirectRoute('tables.index', $this->campaign);
    }

    public function render(): View
    {
        $this->table->refresh();

        return view('livewire.random-tables.show', [
            'entries' => $this->table->entries()->with('nestedTable')->get(),
            'ranges' => $this->table->ranges(),
            'totalWeight' => $this->table->totalWeight(),
            'nestOptions' => $this->nestOptions(),
        ])->title($this->table->name);
    }

    /**
     * Every other table in the campaign. A table nesting itself is rejected here, which
     * is the mistake a GM actually makes; a longer A to B to A loop is caught at roll
     * time by the visited set.
     *
     * @return list<mixed>
     */
    private function nestedRules(): array
    {
        return [
            Rule::exists('random_tables', 'id')->where('campaign_id', $this->campaign->id),
            Rule::notIn([$this->table->id]),
        ];
    }

    /**
     * @return Collection<int, RandomTable>
     */
    private function nestOptions(): Collection
    {
        return RandomTable::query()->whereKeyNot($this->table->id)->orderBy('name')->get(['id', 'name']);
    }

    private function nextPosition(): int
    {
        $max = $this->table->entries()->max('position');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    private function entry(string $entryId): RandomTableEntry
    {
        return $this->table->entries()->whereKey($entryId)->firstOrFail();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
