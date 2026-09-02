@props(['title' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-panel']) }}>
    @if ($title || isset($header))
        <header class="flex items-center gap-3 border-b border-line px-5 py-3">
            @if ($title)
                <h2 class="font-display text-base font-semibold">{{ $title }}</h2>
            @endif
            @isset($header)
                <div class="ml-auto flex items-center gap-2">{{ $header }}</div>
            @endisset
        </header>
    @endif
    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <footer class="border-t border-line px-5 py-3">{{ $footer }}</footer>
    @endisset
</section>
