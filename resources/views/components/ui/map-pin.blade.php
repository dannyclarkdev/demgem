{{--
    The one new kit component of slice 6. A pin is drawn on the GM's map and on the
    party's, in three states, and inlining it three times is how the three drift.

    The teardrop points at its coordinate: the wrapper translates it up by its own
    height, so the tip sits on the point and the head sits above it.
--}}
@props(['label', 'hidden' => false, 'opensMap' => false])
<span {{ $attributes->merge(['class' => 'group/pin flex flex-col items-center']) }}>
    <span class="flex max-w-40 items-center gap-1 truncate rounded-full border px-2 py-1 text-xs font-medium whitespace-nowrap shadow-lg shadow-black/30 {{ $hidden ? 'border-dm/50 bg-panel text-dm' : 'border-ember/50 bg-panel text-ink' }}">
        @if ($hidden)
            <x-ui.icon name="eye-off" class="size-3 shrink-0" />
        @elseif ($opensMap)
            <x-ui.icon name="map" class="size-3 shrink-0 text-ember" />
        @else
            <x-ui.icon name="map-pin" class="size-3 shrink-0 text-ember" />
        @endif
        <span class="truncate">{{ $label }}</span>
    </span>
    <span class="-mt-px size-2 rotate-45 border-r border-b {{ $hidden ? 'border-dm/50 bg-panel' : 'border-ember/50 bg-panel' }}"></span>
</span>
