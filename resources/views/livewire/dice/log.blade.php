{{--
    The shared log. What is in $rolls was decided in the query under this viewer, so
    there is nothing here to hide and no @if that could forget to.

    A roll normally arrives over the socket. The poll is the same sixty-second backstop
    the tracker and the fight keep, for a dropped socket and a slept laptop.

    Prose here is 14px or more, because a player reads this from across a table. The
    face pips stay at 11px: they are numerals in boxes, the same size the tracker uses
    for an initiative, and they are read as a group rather than as words.
--}}
{{-- h-full: the caller decides how tall the log is. The Run screen drawer gives it
     the rest of the panel; /table gives it a fixed box beside the party. --}}
<div wire:poll.visible.{{ $pollSeconds }}s class="flex h-full min-h-0 flex-col text-base">
    <div class="min-h-0 flex-1 overflow-y-auto">
        @if ($rolls->isEmpty())
            <p class="p-4 text-sm text-ink-faint">Nothing rolled yet. Try 4d6kh3.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($rolls as $roll)
                    <li wire:key="roll-{{ $roll->id }}" class="flex items-start gap-3 px-4 py-3">
                        <span class="min-w-11 shrink-0 text-right font-display text-2xl font-semibold tabular-nums text-ink">{{ $roll->total }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-medium text-ink">{{ $roll->user->name }}</span>
                                <span class="font-mono text-sm text-ink-muted">{{ $roll->formula }}</span>
                                @if ($roll->private)
                                    <x-ui.badge variant="dm" icon="eye-off">Behind the screen</x-ui.badge>
                                @endif
                            </p>
                            <p class="mt-0.5 flex flex-wrap gap-1">
                                @foreach ($roll->faces() as $face)
                                    <span class="inline-flex size-5 items-center justify-center rounded border font-mono text-[11px] {{ $face['dropped'] ? 'border-line text-ink-faint line-through' : 'border-line-strong text-ink-muted' }}">{{ $face['face'] }}</span>
                                @endforeach
                                @if ($roll->detail['modifier'] !== 0)
                                    <span class="font-mono text-[11px] text-ink-faint">{{ $roll->detail['modifier'] > 0 ? '+' : '' }}{{ $roll->detail['modifier'] }}</span>
                                @endif
                            </p>
                            @if ($roll->label)
                                <p class="mt-0.5 truncate text-sm text-ink-faint">{{ $roll->label }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-sm text-ink-faint">{{ $roll->created_at?->setTimezone($campaign->timezone)->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($yourRolls)
        <div class="shrink-0 border-t border-line px-4 py-2 text-right">
            <x-ui.button variant="ghost" size="sm" wire:click="clearLog" wire:confirm="Clear your own rolls from the log?">Clear my rolls</x-ui.button>
        </div>
    @endif
</div>
