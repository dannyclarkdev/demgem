<div>
    <x-ui.page-header :title="$table->name" eyebrow="Table" :description="$table->description">
        <x-ui.badge>{{ $table->dieLabel() }}</x-ui.badge>
        <x-ui.button :href="route('tables.index', $campaign)" variant="ghost" size="sm" icon="arrow-left">Tables</x-ui.button>
        <x-ui.button
            variant="ghost"
            size="sm"
            icon="trash"
            wire:click="delete"
            wire:confirm="Delete {{ $table->name }} and its rows? Any entry that nested it becomes plain text."
        >Delete</x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <x-ui.card title="Rows" :padding="false">
            <x-slot:header>
                <span class="text-xs text-ink-faint">
                    {{ $entries->count() }} {{ Str::plural('row', $entries->count()) }}, rolled on {{ $table->dieLabel() }}
                </span>
            </x-slot:header>

            @if ($entries->isEmpty())
                <p class="px-5 py-6 text-sm text-ink-faint">
                    Nothing here yet. Give each row a weight: a weight of 5 out of 100 is exactly rows 01&ndash;05 of a published d100 table.
                </p>
            @else
                <ul wire:sort="reorder" class="divide-y divide-line">
                    @foreach ($entries as $entry)
                        <li wire:key="entry-{{ $entry->id }}" wire:sort:item="{{ $entry->id }}" class="px-5 py-3">
                            @if ($editingId === $entry->id)
                                <form wire:submit="saveEntry" class="space-y-3">
                                    <x-ui.input label="Result" name="editingBody" wire:model="editingBody" />
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="w-24">
                                            <x-ui.input type="number" label="Weight" name="editingWeight" wire:model="editingWeight" min="1" />
                                        </div>
                                        <div class="min-w-44 flex-1">
                                            <x-ui.select label="Then roll" name="editingNestedTableId" wire:model="editingNestedTableId">
                                                <option value="">Nothing</option>
                                                @foreach ($nestOptions as $option)
                                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </div>
                                        <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelEdit">Cancel</x-ui.button>
                                    </div>
                                </form>
                            @else
                                <div class="flex flex-wrap items-start gap-3">
                                    <button type="button" wire:sort:handle class="mt-0.5 cursor-grab text-ink-faint hover:text-ink-muted" aria-label="Drag to reorder">
                                        <x-ui.icon name="grip" class="size-4" />
                                    </button>

                                    <span class="mt-0.5 min-w-14 shrink-0 text-right font-mono text-xs text-ink-faint">
                                        @if ($ranges[$entry->id]['from'] === $ranges[$entry->id]['to'])
                                            {{ $ranges[$entry->id]['from'] }}
                                        @else
                                            {{ $ranges[$entry->id]['from'] }}&ndash;{{ $ranges[$entry->id]['to'] }}
                                        @endif
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-ink">{{ $entry->body }}</p>
                                        @if ($entry->nestedTable)
                                            <p class="mt-0.5 text-xs text-ink-faint">then roll {{ $entry->nestedTable->name }}</p>
                                        @endif
                                    </div>

                                    <div wire:sort:ignore class="ml-auto flex shrink-0 items-center gap-0.5">
                                        <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $entry->id }}', -1)" :disabled="$loop->first" aria-label="Move up">
                                            <x-ui.icon name="arrow-up" class="size-4" />
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $entry->id }}', 1)" :disabled="$loop->last" aria-label="Move down">
                                            <x-ui.icon name="arrow-down" class="size-4" />
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="icon" wire:click="edit('{{ $entry->id }}')" aria-label="Edit row">
                                            <x-ui.icon name="edit" class="size-4" />
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="icon" wire:click="removeEntry('{{ $entry->id }}')" wire:confirm="Delete this row?" aria-label="Delete row">
                                            <x-ui.icon name="trash" class="size-4" />
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-slot:footer>
                <form wire:submit="addEntry" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-44 flex-1">
                        <x-ui.input label="Add a row" name="newBody" wire:model="newBody" placeholder="A caravan is late and nobody will say why" />
                    </div>
                    <div class="w-24">
                        <x-ui.input type="number" label="Weight" name="newWeight" wire:model="newWeight" min="1" />
                    </div>
                    <div class="min-w-40">
                        <x-ui.select label="Then roll" name="newNestedTableId" wire:model="newNestedTableId">
                            <option value="">Nothing</option>
                            @foreach ($nestOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <x-ui.button type="submit" icon="plus">Add</x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.card>

        <aside class="space-y-5">
            <x-ui.card title="Roll it">
                <livewire:random-tables.roller :campaign="$campaign" :wire:key="'roller-'.$table->id" />
            </x-ui.card>

            <x-ui.card title="Details">
                <form wire:submit="save" class="space-y-4">
                    <x-ui.input label="Name" name="name" wire:model="name" />
                    <x-ui.input label="Description" name="description" wire:model="description" hint="What this table is for." />
                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="secondary">Save</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <p class="text-xs text-ink-faint">
                Total weight {{ $totalWeight }}, so this rolls on {{ $table->dieLabel() }}. Give a row a weight of 5 to make it five numbers wide.
            </p>
        </aside>
    </div>
</div>
