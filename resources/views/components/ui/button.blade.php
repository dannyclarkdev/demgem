@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-medium whitespace-nowrap select-none disabled:pointer-events-none disabled:opacity-50';
    $sizes = [
        'sm' => 'h-8 px-3 text-sm',
        'md' => 'h-9 px-4 text-sm',
        'lg' => 'h-11 px-5 text-base',
        'icon' => 'size-9',
    ];
    $variants = [
        'primary' => 'bg-ember text-on-ember shadow-[0_1px_0_0_rgb(0_0_0/0.25)] hover:bg-ember-strong',
        'secondary' => 'border border-line-strong bg-raised text-ink hover:border-ink-faint',
        'ghost' => 'text-ink-muted hover:bg-raised hover:text-ink',
        'danger' => 'border border-danger/40 text-danger hover:bg-danger/10',
    ];
    $classes = $base.' '.$sizes[$size].' '.$variants[$variant];
    $iconClass = $size === 'sm' ? 'size-3.5' : 'size-4';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </button>
@endif
