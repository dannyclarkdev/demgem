@props([
    'label' => null,
    'name',
    'hint' => null,
    'rows' => 12,
    'autocompleteUrl',
    'previewAction' => null,
    'preview' => '',
])
@php
    $id = $attributes->get('id', str_replace('.', '-', $name));
    $error = $errors->first($name);
@endphp
<div x-data="markdownEditor({ url: @js($autocompleteUrl) })" class="space-y-1.5" wire:ignore.self>
    <div class="flex flex-wrap items-center justify-between gap-2">
        @if ($label)
            <label for="{{ $id }}" class="block text-sm font-medium text-ink-muted">{{ $label }}</label>
        @endif
        <div class="flex items-center gap-0.5">
            <template x-if="mode === 'write'">
                <div class="flex items-center gap-0.5">
                    <button type="button" class="ui-editor-btn font-bold" title="Bold" @click="wrap('**', '**', 'bold')">B</button>
                    <button type="button" class="ui-editor-btn italic" title="Italic" @click="wrap('_', '_', 'italic')">I</button>
                    <button type="button" class="ui-editor-btn" title="Heading" @click="prefixLines('## ')">H</button>
                    <button type="button" class="ui-editor-btn" title="Bulleted list" @click="prefixLines('- ')">&bull;</button>
                    <button type="button" class="ui-editor-btn" title="Quote" @click="prefixLines('> ')">&rdquo;</button>
                    <button type="button" class="ui-editor-btn" title="Wiki link" @click="wrap('[[', ']]', 'Name')">[[ ]]</button>
                </div>
            </template>
            @if ($previewAction)
                <span class="mx-1 h-4 w-px bg-line" aria-hidden="true"></span>
                <button type="button" class="ui-editor-btn" :class="mode === 'write' ? 'ui-editor-btn--active' : ''" @click="mode = 'write'">Write</button>
                <button type="button" class="ui-editor-btn" :class="mode === 'preview' ? 'ui-editor-btn--active' : ''" @click="mode = 'preview'; $wire.{{ $previewAction }}()">Preview</button>
            @endif
        </div>
    </div>

    <div class="relative">
        <textarea
            x-ref="ta"
            x-show="mode === 'write'"
            @input="onInput"
            @keydown="onKeydown"
            @blur="setTimeout(() => close(), 150)"
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('id')->merge(['class' => 'ui-input resize-y font-mono text-[13px] leading-relaxed'.($error ? ' ui-input--error' : '')]) }}
        ></textarea>

        <div x-show="mode === 'preview'" x-cloak class="ui-input prose-entity min-h-40">
            @if ($preview !== '')
                {!! $preview !!}
            @else
                <p class="text-ink-faint">Nothing to preview yet.</p>
            @endif
        </div>

        <div x-show="open" x-cloak class="absolute bottom-2 left-2 z-20 w-72 overflow-hidden rounded-md border border-line bg-panel shadow-xl shadow-black/40">
            <template x-for="(item, i) in results" :key="item.type + '-' + item.slug">
                <button
                    type="button"
                    @mousedown.prevent="choose(item)"
                    :class="i === active ? 'bg-raised text-ink' : 'text-ink-muted'"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-raised"
                >
                    <span class="w-16 shrink-0 text-[11px] tracking-wide text-ink-faint uppercase" x-text="item.typeLabel"></span>
                    <span class="truncate" x-text="item.name"></span>
                </button>
            </template>
            <div x-show="results.length === 0" class="px-3 py-2 text-xs text-ink-faint">No match. Close the brackets to link a new entity.</div>
        </div>
    </div>

    @if ($error)
        <p class="text-sm text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-faint">{{ $hint }}</p>
    @endif
</div>
