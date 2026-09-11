{{--
    The standing card, drawn for whoever is looking. The number a player reads is the
    sum of the rows the GM revealed; the query decided which rows those are.
--}}
<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-x-8 gap-y-3">
        <div>
            <p class="eyebrow">{{ $canManage ? 'As the party sees it' : 'Standing' }}</p>
            @if ($hasVisibleRows)
                <p class="mt-0.5 flex items-center gap-2">
                    <span class="font-display text-2xl font-semibold text-ink">{{ $noticed->signed() }}</span>
                    <x-ui.badge :variant="$noticed->badgeVariant()">{{ $noticed->label() }}</x-ui.badge>
                </p>
            @else
                <p class="mt-0.5 text-sm text-ink-faint">The party has no read on them yet.</p>
            @endif
        </div>

        @if ($truth !== null && ($truth->sum !== $noticed->sum || ! $hasVisibleRows))
            <div>
                <p class="eyebrow">In truth</p>
                <p class="mt-0.5 flex items-center gap-2">
                    <span class="font-display text-2xl font-semibold text-ink">{{ $truth->signed() }}</span>
                    <x-ui.badge :variant="$truth->badgeVariant()">{{ $truth->label() }}</x-ui.badge>
                </p>
            </div>
        @endif
    </div>

    @if ($canManage)
        <form wire:submit="adjust" class="grid gap-3 rounded-lg border border-line bg-panel p-4 sm:grid-cols-[7rem_minmax(0,1fr)]">
            <x-ui.select label="Moved by" name="newDelta" wire:model="newDelta">
                @foreach ($deltas as $delta)
                    <option value="{{ $delta }}">{{ $delta > 0 ? '+'.$delta : '−'.abs($delta) }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input label="Because" name="newReason" wire:model="newReason" placeholder="They returned the signet." />
            <div class="sm:col-span-2 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                <x-ui.select label="During" name="newSessionId" wire:model="newSessionId">
                    <option value="">Between sessions</option>
                    @foreach ($sessionOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->label() }}{{ $option->title ? ' · '.$option->title : '' }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit" icon="plus">Record it</x-ui.button>
            </div>
        </form>
    @endif

    @if ($changes->isEmpty())
        @if ($canManage)
            <p class="text-sm text-ink-faint">Nothing recorded yet. The first row is how they felt when the party walked in.</p>
        @endif
    @else
        <ol class="divide-y divide-line rounded-lg border border-line bg-panel">
            @foreach ($changes as $change)
                <li wire:key="reputation-{{ $change->id }}" class="flex items-start gap-3 px-4 py-3">
                    <span class="mt-0.5 w-9 shrink-0 text-right font-mono text-sm {{ $change->delta < 0 ? 'text-danger' : 'text-success' }}">{{ $change->signedDelta() }}</span>
                    <div class="min-w-0 flex-1">
                        @if ($canManage && $editingId === $change->id)
                            <form wire:submit="saveReason" class="flex flex-wrap items-end gap-2">
                                <div class="min-w-48 flex-1">
                                    <x-ui.input label="Because" name="editingReason" wire:model="editingReason" />
                                </div>
                                <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelEdit">Cancel</x-ui.button>
                            </form>
                        @else
                            <p class="text-sm text-ink">{{ filled($change->reason) ? $change->reason : 'No reason written.' }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint">
                                @php($link = $change->game_session_id ? ($sessionLinks[$change->game_session_id] ?? null) : null)
                                @if ($link)
                                    <a href="{{ $link->url() }}" class="inline-flex items-center gap-1 hover:text-ember">
                                        <x-ui.icon name="calendar" class="size-3 shrink-0" />
                                        <span>{{ $link->label() }}</span>
                                    </a>
                                @endif
                                <span>{{ $change->created_at?->diffForHumans() }}</span>
                            </p>
                        @endif
                    </div>
                    @if ($canManage && $editingId !== $change->id)
                        <div class="flex items-center gap-1">
                            <x-ui.button variant="ghost" size="icon" wire:click="edit('{{ $change->id }}')" aria-label="Edit the reason">
                                <x-ui.icon name="edit" class="size-4" />
                            </x-ui.button>
                            <x-ui.button
                                variant="ghost"
                                size="icon"
                                wire:click="toggleVisibility('{{ $change->id }}')"
                                :title="$change->player_visible ? 'The party has noticed this' : 'The party has not noticed this'"
                                :aria-label="$change->player_visible ? 'Hide this change from the party' : 'Show this change to the party'"
                            >
                                <x-ui.icon :name="$change->player_visible ? 'eye' : 'eye-off'" class="size-4 {{ $change->player_visible ? 'text-ember' : '' }}" />
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="icon" wire:click="delete('{{ $change->id }}')" wire:confirm="Delete this change?" aria-label="Delete this change">
                                <x-ui.icon name="trash" class="size-4" />
                            </x-ui.button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
