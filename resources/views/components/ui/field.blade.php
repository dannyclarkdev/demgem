@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null])
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-medium text-ink-muted">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($error)
        <p class="text-sm text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-faint">{{ $hint }}</p>
    @endif
</div>
