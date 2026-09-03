{{--
    Read from four feet away, and read by the whole table at once.

    Everything here was decided on the server under this viewer's own role: a hidden
    combatant is not in $combatants at all, and hit points reach the page only for a
    GM. The poll is the same sixty-second backstop the tracker keeps, for the same
    reasons: a socket drops and a laptop sleeps.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s class="text-base">
    @if ($encounter === null)
        <p class="px-5 py-6 text-ink-faint">That fight is over.</p>
    @else
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line px-5 py-3">
            <x-ui.badge :variant="$encounter->status->badgeVariant()" :icon="$encounter->status->icon()">{{ $encounter->status->label() }}</x-ui.badge>

            @if ($encounter->round > 0)
                <span class="font-display text-xl font-semibold text-ink">Round {{ $encounter->round }}</span>
            @endif

            @if ($activeName)
                <span class="text-ink-muted">&middot; <span class="font-medium text-ink">{{ $activeName }}</span> is up</span>
            @elseif ($hasHiddenTurn)
                <span class="text-ink-muted">&middot; something you cannot see is taking its turn</span>
            @endif
        </div>

        @if ($combatants->isEmpty())
            <p class="px-5 py-6 text-ink-faint">
                {{ $isDm ? 'Nobody in the fight yet.' : 'The GM has not put anything on the table yet.' }}
            </p>
        @else
            <ol class="divide-y divide-line">
                @foreach ($combatants as $combatant)
                    @php
                        $word = $combatant->healthWord();
                        $wordVariant = match ($word) {
                            'Unhurt' => 'success',
                            'Badly hurt', 'Down' => 'danger',
                            default => 'neutral',
                        };
                    @endphp
                    <li
                        wire:key="fight-row-{{ $combatant->id }}"
                        class="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-3 {{ $combatant->id === $activeId ? 'border-l-2 border-ember bg-ember/5' : '' }} {{ $combatant->isDown() ? 'opacity-60' : '' }}"
                    >
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border border-line-strong bg-canvas font-mono text-sm font-semibold tabular-nums text-ink-muted">
                            {{ $loop->iteration }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium text-ink">
                                <span class="truncate">{{ $combatant->name }}</span>
                                @if (in_array($combatant->id, $yours, true))
                                    <x-ui.badge variant="accent">You</x-ui.badge>
                                @endif
                                @if ($isDm && ! $combatant->isVisibleToPlayers())
                                    <x-ui.badge variant="dm" icon="eye-off">Hidden</x-ui.badge>
                                @endif
                            </p>
                            @if ($combatant->conditionList() !== [])
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($combatant->conditionList() as $condition)
                                        <x-ui.badge variant="danger">{{ $condition }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- A word for the party, the number for the GM. "The ogre has 43
                             left" changes how a table plays; "badly hurt" does not. --}}
                        @if ($isDm)
                            @if ($combatant->hp !== null)
                                <span class="shrink-0 font-mono text-sm tabular-nums {{ $combatant->isDown() ? 'text-danger' : 'text-ink' }}">
                                    {{ $combatant->hp }}@if ($combatant->max_hp)<span class="text-ink-faint">/{{ $combatant->max_hp }}</span>@endif
                                </span>
                            @endif
                        @elseif ($word)
                            <x-ui.badge :variant="$wordVariant" class="shrink-0">{{ $word }}</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</div>
