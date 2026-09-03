{{--
    A side panel for tools the GM opens for a few seconds. Unlike x-ui.modal it does
    not teleport to the body: the drawer holds nested Livewire components, and moving
    their markup out of the parent's DOM tree breaks the parent's next morph.

    It is also not modal. Nothing is trapped and nothing is dimmed, because the GM
    needs to read the page behind it while rolling.

    Open it from anywhere with $dispatch('open-drawer', { name: 'tools' }).
--}}
@props(['name', 'title' => null, 'icon' => null, 'width' => 'w-full sm:w-[24rem]'])
<div
    x-data="{ open: false }"
    x-on:open-drawer.window="if ($event.detail.name === '{{ $name }}') open = true"
    x-on:close-drawer.window="if ($event.detail.name === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
>
    <button
        type="button"
        @click="open = !open"
        :aria-expanded="open"
        class="fixed right-4 bottom-4 z-40 inline-flex items-center gap-2 rounded-full border border-line-strong bg-panel px-4 py-2.5 text-sm font-medium text-ink shadow-xl shadow-black/30 hover:border-ember hover:text-ember"
    >
        @if ($icon)<x-ui.icon :name="$icon" class="size-4" />@endif
        {{ $title ?? 'Tools' }}
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        @click.outside="open = false"
        class="fixed inset-x-0 bottom-0 z-40 flex max-h-[85vh] flex-col rounded-t-xl border border-line bg-panel shadow-2xl shadow-black/50 sm:inset-x-auto sm:top-0 sm:right-0 sm:bottom-0 sm:max-h-none sm:rounded-none sm:rounded-l-xl sm:border-y-0 sm:border-r-0 {{ $width }}"
        role="region"
        :aria-hidden="! open"
        aria-label="{{ $title ?? 'Tools' }}"
    >
        <header class="flex items-center gap-2 border-b border-line px-4 py-3">
            @if ($icon)<x-ui.icon :name="$icon" class="size-4 text-ember" />@endif
            <h2 class="font-display text-base font-semibold">{{ $title ?? 'Tools' }}</h2>
            <button type="button" class="ml-auto text-ink-faint hover:text-ink" @click="open = false" aria-label="Close">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto">{{ $slot }}</div>
    </div>
</div>
