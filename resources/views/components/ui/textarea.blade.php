@props(['label' => null, 'name', 'hint' => null, 'error' => null, 'rows' => 4])
@php
    $id = $attributes->get('id', str_replace('.', '-', $name));
    $error ??= $errors->first($name);
@endphp
<x-ui.field :label="$label" :for="$id" :error="$error" :hint="$hint">
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge(['class' => 'ui-input resize-y'.($error ? ' ui-input--error' : '')]) }}
    >{{ $slot }}</textarea>
</x-ui.field>
