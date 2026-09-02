@props(['title', 'description' => null, 'icon' => 'book-open'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-lg border border-dashed border-line-strong px-6 py-14 text-center']) }}>
    <span class="mb-4 inline-flex size-12 items-center justify-center rounded-full bg-raised text-ember">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <h3 class="font-display text-lg font-semibold">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-ink-muted">{{ $description }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-5 flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
