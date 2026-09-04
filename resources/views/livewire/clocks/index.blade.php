{{--
    Every clock in the campaign. GM-only, so there is no role branch anywhere below:
    the route and the policy already answered that question.
--}}
<div
    @keydown.window="if ($event.key === 'n' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.newClock.focus() }"
>
    <x-ui.page-header
        title="Clocks"
        :eyebrow="$campaign->name"
        description="Anything that is coming: the ritual, the storm, the guard's patience. Fill a wedge when the world moves. GMs only, until you reveal one."
    />

    <x-ui.card class="mb-4">
        <form wire:submit="create" class="flex flex-wrap items-end gap-2">
            <div class="min-w-56 flex-1">
                <x-ui.input label="New clock" name="newName" wire:model="newName" placeholder="The ritual" x-ref="newClock" />
            </div>
            <div class="w-32">
                <x-ui.select label="Segments" name="newSegments" wire:model="newSegments">
                    @foreach ($sizes as $size)
                        <option value="{{ $size }}">{{ $size }} wedges</option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.button type="submit" icon="plus" class="mb-1">Create</x-ui.button>
        </form>
    </x-ui.card>

    @if ($clocks->isEmpty())
        <x-ui.empty-state
            icon="clock"
            title="No clocks yet"
            description="A clock is a process, not an outcome. Four wedges for something close, twelve for something the campaign is built around."
        />
    @else
        <x-ui.card :padding="false">
            <ul wire:sort="reorder" class="divide-y divide-line">
                @foreach ($clocks as $clock)
                    <li wire:key="clock-{{ $clock->id }}" wire:sort:item="{{ $clock->id }}" class="px-5 py-4">
                        @if ($editingId === $clock->id)
                            <form wire:submit="save" class="flex flex-wrap items-end gap-2">
                                <div class="min-w-48 flex-1">
                                    <x-ui.input label="Name" name="editingName" wire:model="editingName" />
                                </div>
                                <div class="w-32">
                                    <x-ui.select label="Segments" name="editingSegments" wire:model="editingSegments">
                                        @foreach ($sizes as $size)
                                            <option value="{{ $size }}">{{ $size }} wedges</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                                <x-ui.button type="submit" size="sm" class="mb-1">Save</x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelEdit" class="mb-1">Cancel</x-ui.button>
                            </form>
                            <p class="mt-2 text-xs text-ink-faint">A smaller dial brings the fill down with it. A bigger one leaves it where it is.</p>
                        @else
                            <div class="flex flex-wrap items-center gap-4">
                                <button type="button" wire:sort:handle class="-ml-1.5 inline-flex size-8 shrink-0 cursor-grab items-center justify-center text-ink-faint hover:text-ink-muted" aria-label="Drag to reorder">
                                    <x-ui.icon name="grip" class="size-4" />
                                </button>

                                <x-ui.clock :clock="$clock" interactive :size="72" />

                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-ink">{{ $clock->name }}</p>
                                    <p class="font-mono text-xs {{ $clock->isComplete() ? 'text-success' : 'text-ink-faint' }}">
                                        {{ $clock->readout() }}
                                        @if ($clock->isComplete())
                                            &middot; full
                                        @endif
                                    </p>
                                    @if ($clock->entity)
                                        <a href="{{ $clock->entity->url() }}" class="mt-1 inline-flex items-center gap-1 text-xs text-ink-faint hover:text-ember">
                                            <x-ui.icon :name="$clock->entity->type->icon()" class="size-3 shrink-0" />
                                            <span class="truncate">{{ $clock->entity->name }}</span>
                                        </a>
                                    @endif
                                </div>

                                <div wire:sort:ignore class="ml-auto flex shrink-0 items-center gap-0.5">
                                    <x-ui.button variant="secondary" size="icon" wire:click="tick('{{ $clock->id }}', -1)" :disabled="$clock->isEmpty()" aria-label="Take a wedge off {{ $clock->name }}">
                                        <x-ui.icon name="minus" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button variant="secondary" size="icon" wire:click="tick('{{ $clock->id }}', 1)" :disabled="$clock->isComplete()" aria-label="Fill a wedge of {{ $clock->name }}">
                                        <x-ui.icon name="plus" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $clock->id }}', -1)" :disabled="$loop->first" aria-label="Move up">
                                        <x-ui.icon name="arrow-up" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $clock->id }}', 1)" :disabled="$loop->last" aria-label="Move down">
                                        <x-ui.icon name="arrow-down" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button
                                        variant="ghost"
                                        size="icon"
                                        wire:click="toggleVisibility('{{ $clock->id }}')"
                                        :title="$clock->player_visible ? 'The party sees this clock' : 'Hidden from the party'"
                                        :aria-label="($clock->player_visible ? 'Hide ' : 'Show ').$clock->name.' on the player table'"
                                    >
                                        <x-ui.icon :name="$clock->player_visible ? 'eye' : 'eye-off'" class="size-4 {{ $clock->player_visible ? 'text-ember' : '' }}" />
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="icon" wire:click="edit('{{ $clock->id }}')" aria-label="Rename {{ $clock->name }}">
                                        <x-ui.icon name="edit" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="icon" wire:click="delete('{{ $clock->id }}')" wire:confirm="Delete {{ $clock->name }}?" aria-label="Delete {{ $clock->name }}">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </x-ui.button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
