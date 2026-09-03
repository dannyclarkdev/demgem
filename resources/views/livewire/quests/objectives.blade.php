<div>
    @if ($objectives->isEmpty() && ! $canManage)
        <p class="text-sm text-ink-faint">No objectives yet.</p>
    @else
        @if ($objectives->isNotEmpty())
            <x-ui.progress :value="$progress['done']" :max="$progress['total']" class="mb-3" />

            <ul @if ($canManage) wire:sort="reorder" @endif class="divide-y divide-line">
                @foreach ($objectives as $objective)
                    <li wire:key="objective-{{ $objective->id }}" wire:sort:item="{{ $objective->id }}" class="py-2.5 first:pt-0">
                        @if ($editingId === $objective->id)
                            <form wire:submit="save" class="flex items-end gap-2">
                                <div class="flex-1">
                                    <x-ui.input label="Objective" name="editingBody" wire:model="editingBody" />
                                </div>
                                <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelEdit">Cancel</x-ui.button>
                            </form>
                        @else
                            <div class="flex flex-wrap items-start gap-2.5">
                                @if ($canManage)
                                    <button type="button" wire:sort:handle class="mt-1 cursor-grab text-ink-faint hover:text-ink-muted" aria-label="Drag to reorder">
                                        <x-ui.icon name="grip" class="size-4" />
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    @if ($canManage) wire:click="toggle('{{ $objective->id }}')" @else disabled @endif
                                    class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded border {{ $objective->isComplete() ? 'border-success bg-success/15 text-success' : 'border-line-strong text-transparent' }} {{ $canManage ? 'hover:border-ember' : 'cursor-default' }}"
                                    aria-label="{{ $objective->isComplete() ? 'Reopen this objective' : 'Mark this objective done' }}"
                                    aria-pressed="{{ $objective->isComplete() ? 'true' : 'false' }}"
                                >
                                    <x-ui.icon name="check" class="size-3.5" />
                                </button>

                                <div class="min-w-0 flex-1">
                                    <p class="prose-entity text-[15px] {{ $objective->isComplete() ? 'text-ink-faint line-through decoration-ink-faint/60' : '' }}">
                                        {!! $bodyHtml[$objective->id] !!}
                                    </p>
                                    @if (isset($sessionLabels[$objective->id]))
                                        <p class="mt-0.5 text-xs text-ink-faint">Finished in {{ $sessionLabels[$objective->id] }}</p>
                                    @endif
                                </div>

                                @if ($canManage && ! $compact)
                                    <div wire:sort:ignore class="ml-auto flex shrink-0 items-center gap-0.5">
                                        <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $objective->id }}', -1)" :disabled="$loop->first" aria-label="Move up">
                                            <x-ui.icon name="arrow-up" class="size-4" />
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $objective->id }}', 1)" :disabled="$loop->last" aria-label="Move down">
                                            <x-ui.icon name="arrow-down" class="size-4" />
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="icon" wire:click="edit('{{ $objective->id }}')" aria-label="Edit objective">
                                            <x-ui.icon name="edit" class="size-4" />
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="icon" wire:click="remove('{{ $objective->id }}')" wire:confirm="Delete this objective?" aria-label="Delete objective">
                                            <x-ui.icon name="trash" class="size-4" />
                                        </x-ui.button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canManage && ! $compact)
            <form wire:submit="add" class="mt-3 flex items-end gap-2 {{ $objectives->isNotEmpty() ? 'border-t border-line pt-3' : '' }}">
                <div class="flex-1">
                    <x-ui.input
                        label="{{ $objectives->isEmpty() ? 'Add the first objective' : 'Add an objective' }}"
                        name="newBody"
                        wire:model="newBody"
                        placeholder="Find who paid the toll guard"
                    />
                </div>
                <x-ui.button type="submit" icon="plus">Add</x-ui.button>
            </form>
        @endif
    @endif
</div>
