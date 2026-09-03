{{--
    Tab buttons only. The panels stay with the caller, so a Livewire child inside a
    panel keeps its place in the component tree:

    <div x-data="{ tab: 'dice' }">
        <x-ui.tabs :tabs="['dice' => 'Dice', 'tables' => 'Tables']" />
        <div x-show="tab === 'dice'">…</div>
    </div>

    A tab value may be a plain label or ['label' => …, 'icon' => …, 'count' => …].
--}}
@props(['tabs', 'model' => 'tab'])
<div role="tablist" {{ $attributes->merge(['class' => 'flex items-center gap-1 border-b border-line']) }}>
    @foreach ($tabs as $key => $tab)
        @php
            $label = is_array($tab) ? ($tab['label'] ?? $key) : $tab;
            $icon = is_array($tab) ? ($tab['icon'] ?? null) : null;
            $count = is_array($tab) ? ($tab['count'] ?? null) : null;
            $selected = $model." === '".$key."'";
        @endphp
        <button
            type="button"
            role="tab"
            :aria-selected="{{ $selected }}"
            @click="{{ $model }} = '{{ $key }}'"
            :class="{{ $selected }}
                ? 'border-ember text-ink'
                : 'border-transparent text-ink-muted hover:text-ink'"
            class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium"
        >
            @if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
            {{ $label }}
            @if ($count !== null)
                <span class="rounded-full bg-raised px-1.5 py-0.5 font-mono text-[11px] text-ink-faint">{{ $count }}</span>
            @endif
        </button>
    @endforeach
</div>
