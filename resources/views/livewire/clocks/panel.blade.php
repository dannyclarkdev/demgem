{{--
    The dials, drawn for whoever is looking.

    Its own poll, because a nested component does not re-render when its parent does
    and a dropped socket must not leave a table watching a clock that stopped.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s class="space-y-3">
    @if ($canManage)
        <form wire:submit="create" class="flex flex-wrap items-end gap-2">
            <div class="min-w-48 flex-1">
                <x-ui.input label="New clock" name="newName" wire:model="newName" placeholder="The ritual" />
            </div>
            <div class="w-28">
                <x-ui.select label="Segments" name="newSegments" wire:model="newSegments">
                    @foreach ($sizes as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.button type="submit" icon="plus" class="mb-1">Add</x-ui.button>
        </form>
    @endif

    @if ($clocks->isEmpty())
        <x-ui.empty-state
            icon="clock"
            title="{{ $canManage ? 'No clocks yet' : 'Nothing counting' }}"
            :description="$canManage
                ? 'A clock is anything that is coming: the ritual, the storm, the guard\'s patience. Name it, pick how many wedges it takes, and fill one when the world moves.'
                : 'When the GM starts one, it turns up here and ticks on its own.'"
        />
    @else
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach ($clocks as $clock)
                <li wire:key="clock-{{ $clock->id }}" class="flex items-center gap-4 rounded-lg border border-line bg-panel p-4">
                    <x-ui.clock :clock="$clock" :interactive="$canManage" :size="$canManage ? 88 : 72" />

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-ink">{{ $clock->name }}</p>

                        <p class="font-mono text-xs {{ $clock->isComplete() ? 'text-success' : 'text-ink-faint' }}">
                            {{ $clock->readout() }}
                            @if ($clock->isComplete())
                                &middot; full
                            @endif
                        </p>

                        {{-- Absent when this viewer may not see it, which is the whole
                             gate: the query never loaded it, so the name never reached
                             the page. --}}
                        @php($link = $clock->entity_id ? ($links[$clock->entity_id] ?? null) : null)
                        @if ($link)
                            <a href="{{ $link->url() }}" class="mt-1 inline-flex items-center gap-1 truncate text-xs text-ink-faint hover:text-ember">
                                <x-ui.icon :name="$link->type->icon()" class="size-3 shrink-0" />
                                <span class="truncate">{{ $link->name }}</span>
                            </a>
                        @endif

                        @if ($canManage)
                            <div class="mt-2 flex items-center gap-1">
                                <x-ui.button
                                    variant="secondary"
                                    size="icon"
                                    wire:click="tick('{{ $clock->id }}', -1)"
                                    :disabled="$clock->isEmpty()"
                                    aria-label="Take a wedge off {{ $clock->name }}"
                                >
                                    <x-ui.icon name="minus" class="size-4" />
                                </x-ui.button>
                                <x-ui.button
                                    variant="secondary"
                                    size="icon"
                                    wire:click="tick('{{ $clock->id }}', 1)"
                                    :disabled="$clock->isComplete()"
                                    aria-label="Fill a wedge of {{ $clock->name }}"
                                >
                                    <x-ui.icon name="plus" class="size-4" />
                                </x-ui.button>
                                {{-- The eye is what the party sees. Off by default for
                                     everything the GM makes, so a countdown they have
                                     not been told about is one they cannot see. --}}
                                <x-ui.button
                                    variant="ghost"
                                    size="icon"
                                    wire:click="toggleVisibility('{{ $clock->id }}')"
                                    :title="$clock->player_visible ? 'The party sees this clock' : 'Hidden from the party'"
                                    :aria-label="($clock->player_visible ? 'Hide ' : 'Show ').$clock->name.' on the player table'"
                                >
                                    <x-ui.icon
                                        :name="$clock->player_visible ? 'eye' : 'eye-off'"
                                        class="size-4 {{ $clock->player_visible ? 'text-ember' : '' }}"
                                    />
                                </x-ui.button>
                                <x-ui.button
                                    variant="ghost"
                                    size="icon"
                                    wire:click="delete('{{ $clock->id }}')"
                                    wire:confirm="Delete {{ $clock->name }}?"
                                    aria-label="Delete {{ $clock->name }}"
                                    class="ml-auto"
                                >
                                    <x-ui.icon name="trash" class="size-4" />
                                </x-ui.button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
