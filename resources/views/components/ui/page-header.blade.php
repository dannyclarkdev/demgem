@props(['title', 'eyebrow' => null, 'description' => null])
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eyebrow mb-1">{{ $eyebrow }}</p>
        @endif
        <h1 class="font-display text-3xl font-semibold tracking-tight">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </div>
    @if (trim($slot))
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
