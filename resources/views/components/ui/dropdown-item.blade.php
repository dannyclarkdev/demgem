@props(['href' => null, 'icon' => null, 'variant' => 'default'])
@php
    $classes = 'flex w-full items-center gap-2.5 px-3 py-1.5 text-left text-sm '.($variant === 'danger' ? 'text-danger hover:bg-danger/10' : 'text-ink-muted hover:bg-raised hover:text-ink');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="submit" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
