{{--
    The one new kit component of slice 6. A pin is drawn on the GM's map and on the
    party's, in three states, and inlining it three times is how the three drift.

    The teardrop points at its coordinate: the wrapper translates it up by its own
    height, so the tip sits on the point and the head sits above it.

    It is 44px tall with 14px text, because a GM taps these mid-game on a tablet and
    the slice 2 rules apply to a pin exactly as they apply to a button. Pins keep
    their size on screen at every zoom, so a crowded map is a reason to zoom in
    rather than a reason to make them smaller.
--}}
@props(['label', 'hidden' => false, 'opensMap' => false, 'align' => 'center'])
<span {{ $attributes->merge(['class' => 'group/pin flex flex-col '.match ($align) {
    'left' => 'items-start',
    'right' => 'items-end',
    default => 'items-center',
}]) }}>
    <span class="flex h-11 max-w-44 items-center gap-1.5 truncate rounded-full border px-3 text-sm font-medium whitespace-nowrap shadow-lg shadow-black/30 {{ $hidden ? 'border-dm/50 bg-panel text-dm' : 'border-ember/50 bg-panel text-ink' }}">
        @if ($hidden)
            <x-ui.icon name="eye-off" class="size-4 shrink-0" />
        @elseif ($opensMap)
            <x-ui.icon name="map" class="size-4 shrink-0 text-ember" />
        @else
            <x-ui.icon name="map-pin" class="size-4 shrink-0 text-ember" />
        @endif
        <span class="truncate">{{ $label }}</span>
    </span>
    <span class="-mt-px {{ $align === 'left' ? 'ml-4' : ($align === 'right' ? 'mr-4' : '') }} size-2 rotate-45 border-r border-b {{ $hidden ? 'border-dm/50 bg-panel' : 'border-ember/50 bg-panel' }}"></span>
</span>
