{{--
    A progress clock: a circle cut into countable wedges.

    No library. A clock is 360 degrees divided by the number of segments, and each
    wedge is one SVG arc. It is the same drawing on the Run screen, on the table
    screen, and on an entity page, so a GM learns one shape.

    Accessibility. The dial is role="img" with the readout as its label, and its
    wedges are pointer targets only. Every action it offers is also on a real button
    beside it, which is what a keyboard and a screen reader use. A path is not a
    button, and pretending otherwise with tabindex reads worse than this does.

    Clicking the last filled wedge empties it, so a GM who overshoots does not have
    to reach for the minus.
--}}
@props([
    'clock',
    'interactive' => false,
    'size' => 96,
    'action' => 'setTo',
])
@php
    $segments = max(2, (int) $clock->segments);
    $filled = max(0, min($segments, (int) $clock->filled));
    $complete = $filled >= $segments;

    $centre = 50.0;
    $radius = 46.0;
    $step = 360.0 / $segments;

    $wedges = [];

    for ($i = 0; $i < $segments; $i++) {
        $from = deg2rad(-90 + $i * $step);
        $to = deg2rad(-90 + ($i + 1) * $step);

        $wedges[] = [
            'on' => $i < $filled,
            // Clicking the wedge that is currently last takes it back off.
            'value' => ($i + 1) === $filled ? $i : $i + 1,
            'label' => ($i + 1).' of '.$segments,
            'path' => sprintf(
                'M %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f Z',
                $centre, $centre,
                $centre + $radius * cos($from), $centre + $radius * sin($from),
                $radius, $radius,
                $step > 180 ? 1 : 0,
                $centre + $radius * cos($to), $centre + $radius * sin($to),
            ),
        ];
    }
@endphp
<svg
    viewBox="0 0 100 100"
    role="img"
    aria-label="{{ $clock->name }}, {{ $filled }} of {{ $segments }}"
    style="width: {{ (int) $size }}px; height: {{ (int) $size }}px"
    {{ $attributes->merge(['class' => 'shrink-0 select-none']) }}
>
    @foreach ($wedges as $wedge)
        <path
            d="{{ $wedge['path'] }}"
            class="stroke-line transition-colors {{ $wedge['on'] ? ($complete ? 'fill-success' : 'fill-ember') : 'fill-raised' }} {{ $interactive ? 'cursor-pointer hover:opacity-80' : '' }}"
            stroke-width="1.5"
            @if ($interactive)
                wire:click="{{ $action }}('{{ $clock->id }}', {{ $wedge['value'] }})"
            @endif
        ></path>
    @endforeach
</svg>
