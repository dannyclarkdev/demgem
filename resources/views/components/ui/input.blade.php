@props(['label' => null, 'name', 'type' => 'text', 'hint' => null, 'error' => null])
@php
    $id = $attributes->get('id', str_replace('.', '-', $name));
    $error ??= $errors->first($name);
@endphp
<x-ui.field :label="$label" :for="$id" :error="$error" :hint="$hint">
    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge(['class' => 'ui-input'.($error ? ' ui-input--error' : '')]) }}
    >
</x-ui.field>
