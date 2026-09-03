<div
    @keydown.window="if ($event.key === 'n' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.newTable.focus() }"
>
    <x-ui.page-header title="Tables" :eyebrow="$campaign->name" description="Weighted tables you roll at the table. Nest one inside another to build a result out of parts. GMs only." />

    <x-ui.card class="mb-4">
        <form wire:submit="create" class="flex flex-wrap items-end gap-2">
            <div class="min-w-56 flex-1">
                <x-ui.input label="New table" name="newName" wire:model="newName" placeholder="Tavern rumours" x-ref="newTable" />
            </div>
            <x-ui.button type="submit" icon="plus">Create</x-ui.button>
        </form>
    </x-ui.card>

    @if ($tables->isEmpty())
        <x-ui.empty-state
            title="No tables yet"
            description="Transcribe a published d100 table by giving each row a weight, or write five rumours and roll a d5."
            icon="list"
        />
    @else
        <x-ui.card :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($tables as $table)
                    <li wire:key="table-{{ $table->id }}" class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <a href="{{ $table->url() }}" class="flex min-w-0 flex-1 items-center gap-3 hover:text-ember">
                            <x-ui.icon name="list" class="size-4 shrink-0 text-ink-faint" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $table->name }}</span>
                                <span class="block truncate text-xs text-ink-faint">
                                    {{ $table->entries->count() }} {{ Str::plural('row', $table->entries->count()) }}
                                    @if ($table->description)
                                        &middot; {{ $table->description }}
                                    @endif
                                </span>
                            </span>
                        </a>
                        <span class="shrink-0 font-mono text-xs text-ink-faint">{{ $table->dieLabel() }}</span>
                        <x-ui.button
                            variant="ghost"
                            size="icon"
                            wire:click="delete('{{ $table->id }}')"
                            wire:confirm="Delete {{ $table->name }} and its {{ $table->entries->count() }} {{ Str::plural('row', $table->entries->count()) }}? Any entry that nested it becomes plain text."
                            aria-label="Delete table"
                        >
                            <x-ui.icon name="trash" class="size-4" />
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
