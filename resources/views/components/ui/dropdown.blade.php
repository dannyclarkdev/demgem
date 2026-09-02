@props(['align' => 'right', 'width' => 'w-52'])
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
    <div @click="open = !open">
        {{ $trigger }}
    </div>
    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.{{ $align }}
        @click.outside="open = false"
        @click="open = false"
        class="absolute z-30 mt-2 {{ $width }} {{ $align === 'right' ? 'right-0' : 'left-0' }} overflow-hidden rounded-md border border-line bg-panel py-1 shadow-xl shadow-black/30"
    >
        {{ $slot }}
    </div>
</div>
