@props(['label', 'name', 'error' => null])
@php
    $id = $attributes->get('id', str_replace('.', '-', $name));
    $error ??= $errors->first($name);
@endphp
<div class="space-y-1">
    <label for="{{ $id }}" class="flex cursor-pointer items-center gap-2.5 text-sm text-ink-muted">
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            {{ $attributes->except('id')->merge(['class' => 'size-4 rounded border-line-strong bg-canvas text-ember focus:ring-ember/30']) }}
        >
        <span>{{ $label }}</span>
    </label>
    @if ($error)
        <p class="text-sm text-danger">{{ $error }}</p>
    @endif
</div>
