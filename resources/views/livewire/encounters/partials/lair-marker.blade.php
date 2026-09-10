{{--
    The lair action in the turn order.

    It is not a combatant row and deliberately has no row of its own in the database:
    it has no hit points, cannot be hit, cannot be targeted, and a fake combatant would
    reach RollInitiative, ApplyDamage and the export.

    $showNote is what decides whether the GM's words render. The party gets the marker
    and the count, the same way they get "something you cannot see is taking its turn".
--}}
<li class="flex flex-wrap items-center gap-3 border-l-2 border-dm bg-dm/5 px-5 py-2">
    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border border-dm/40 font-mono text-sm font-semibold tabular-nums text-dm">
        {{ $encounter->lairInitiative() }}
    </span>
    <div class="min-w-0 flex-1">
        <p class="flex items-center gap-1.5 text-sm font-medium text-dm">
            <x-ui.icon name="flag" class="size-3.5" />
            Lair action
        </p>
        @if ($showNote)
            <p class="mt-0.5 text-sm text-ink-muted">{{ $encounter->lair_action_note }}</p>
        @endif
    </div>
</li>
