@props(['variant' => 'info'])
@php
    $variants = [
        'info' => ['border-line-strong bg-raised text-ink', 'info'],
        'success' => ['border-success/30 bg-success/10 text-success', 'check'],
        'danger' => ['border-danger/30 bg-danger/10 text-danger', 'alert'],
    ];
    [$classes, $icon] = $variants[$variant];
@endphp
<div role="alert" {{ $attributes->merge(['class' => 'flex items-start gap-2.5 rounded-md border px-3.5 py-2.5 text-sm '.$classes]) }}>
    <x-ui.icon :name="$icon" class="mt-0.5 size-4" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
