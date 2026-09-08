{{--
    A date in the world: a day, a month, and a year, bound to one array property.

    `name` is the wire property that holds ['year' => …, 'month' => …, 'day' => …], and
    `months` is the calendar's month list, so the month is picked by name and stored
    by number. Errors are read per part, so "Harvest has 20 days that year" lands
    under the day.
--}}
@props(['name', 'months', 'label' => null, 'hint' => null, 'required' => false])
@php
    $id = str_replace('.', '-', $name);
    $error = $errors->first($name.'.day') ?: ($errors->first($name.'.month') ?: $errors->first($name.'.year'));
@endphp
<x-ui.field :label="$label" :for="$id.'-day'" :error="$error" :hint="$hint" {{ $attributes }}>
    <div class="grid grid-cols-[5rem_1fr_6rem] gap-2">
        <input
            type="number"
            id="{{ $id }}-day"
            name="{{ $name }}[day]"
            min="1"
            max="999"
            placeholder="Day"
            aria-label="Day"
            wire:model="{{ $name }}.day"
            @if ($required) required @endif
            class="ui-input{{ $errors->has($name.'.day') ? ' ui-input--error' : '' }}"
        >
        <select
            id="{{ $id }}-month"
            name="{{ $name }}[month]"
            aria-label="Month"
            wire:model="{{ $name }}.month"
            class="ui-input{{ $errors->has($name.'.month') ? ' ui-input--error' : '' }}"
        >
            @unless ($required)
                <option value="">Month</option>
            @endunless
            @foreach ($months as $index => $month)
                <option value="{{ $index + 1 }}">{{ filled($month['name']) ? $month['name'] : 'Month '.($index + 1) }}</option>
            @endforeach
        </select>
        <input
            type="number"
            id="{{ $id }}-year"
            name="{{ $name }}[year]"
            min="1"
            max="99999"
            placeholder="Year"
            aria-label="Year"
            wire:model="{{ $name }}.year"
            @if ($required) required @endif
            class="ui-input{{ $errors->has($name.'.year') ? ' ui-input--error' : '' }}"
        >
    </div>
</x-ui.field>
