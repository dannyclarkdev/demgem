{{-- The lair action on the wall: the count and the word, never the GM's note. --}}
<li class="flex flex-wrap items-center gap-5 border-l-4 border-dm bg-dm/5 px-6 py-3">
    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-lg border border-dm/40 font-mono text-xl font-semibold tabular-nums text-dm">
        {{ $encounter->lairInitiative() }}
    </span>
    <p class="flex items-center gap-2 text-2xl font-medium text-dm">
        <x-ui.icon name="flag" class="size-5" />
        Lair action
    </p>
</li>
