@props(['name', 'title' => null, 'maxWidth' => 'max-w-md'])
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail.name === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail.name === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/70" @click="open = false"></div>
            <div
                x-show="open"
                x-transition
                x-trap.noscroll="open"
                role="dialog"
                aria-modal="true"
                class="relative w-full {{ $maxWidth }} rounded-xl border border-line bg-panel shadow-2xl shadow-black/40"
            >
                @if ($title)
                    <header class="flex items-center border-b border-line px-5 py-3">
                        <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
                        <button type="button" class="ml-auto text-ink-faint hover:text-ink" @click="open = false" aria-label="Close">
                            <x-ui.icon name="x" class="size-4" />
                        </button>
                    </header>
                @endif
                <div class="p-5">{{ $slot }}</div>
                @isset($footer)
                    <footer class="flex items-center justify-end gap-2 border-t border-line px-5 py-3">{{ $footer }}</footer>
                @endisset
            </div>
        </div>
    </template>
</div>
