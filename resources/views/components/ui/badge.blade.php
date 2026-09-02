@props(['variant' => 'neutral', 'icon' => null])
@php
    $variants = [
        'neutral' => 'border-line text-ink-muted',
        'accent' => 'border-ember/30 bg-ember/10 text-ember',
        'dm' => 'border-dm/30 bg-dm/10 text-dm',
        'danger' => 'border-danger/30 bg-danger/10 text-danger',
        'success' => 'border-success/30 bg-success/10 text-success',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium '.$variants[$variant]]) }}>
    @if ($icon)<x-ui.icon :name="$icon" class="size-3" />@endif
    {{ $slot }}
</span>
