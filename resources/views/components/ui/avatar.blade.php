@props(['name', 'size' => 'md'])
@php
    $initials = collect(preg_split('/\s+/', trim($name)))
        ->map(fn (string $part) => preg_replace('/[^\p{L}\p{N}]/u', '', $part))
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $hue = crc32($name) % 360;
    $sizes = ['sm' => 'size-6 text-[10px]', 'md' => 'size-8 text-xs', 'lg' => 'size-12 text-base'];
@endphp
<span
    style="--h: {{ $hue }}; background: oklch(0.32 0.06 var(--h)); color: oklch(0.9 0.06 var(--h));"
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold tracking-wide select-none '.$sizes[$size]]) }}
    aria-hidden="true"
>{{ $initials ?: '?' }}</span>
