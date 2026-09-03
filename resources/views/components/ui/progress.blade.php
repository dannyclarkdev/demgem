{{-- Objective and checklist progress. With a max of 0 there is nothing to draw, so only the label shows. --}}
@props(['value' => 0, 'max' => 0, 'label' => null, 'showBar' => true])
@php
    $value = max(0, (int) $value);
    $max = max(0, (int) $max);
    $percent = $max > 0 ? (int) round(min($value, $max) / $max * 100) : 0;
    $complete = $max > 0 && $value >= $max;
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    @if ($showBar && $max > 0)
        <div
            class="h-1.5 min-w-16 flex-1 overflow-hidden rounded-full bg-raised"
            role="progressbar"
            aria-valuenow="{{ $value }}"
            aria-valuemin="0"
            aria-valuemax="{{ $max }}"
        >
            <div class="h-full rounded-full {{ $complete ? 'bg-success' : 'bg-ember' }}" style="width: {{ $percent }}%"></div>
        </div>
    @endif
    <span class="shrink-0 font-mono text-xs {{ $complete ? 'text-success' : 'text-ink-faint' }}">
        {{ $label ?? ($max > 0 ? $value.' of '.$max : 'None yet') }}
    </span>
</div>
