@props(['href', 'active' => false, 'icon' => null, 'count' => null])
<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'group relative flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm '.($active ? 'bg-raised text-ink' : 'text-ink-muted hover:bg-raised/60 hover:text-ink')]) }}
>
    @if ($active)
        <span class="absolute inset-y-1.5 -left-3 w-0.5 rounded-full bg-ember" aria-hidden="true"></span>
    @endif
    @if ($icon)
        <x-ui.icon :name="$icon" class="size-4 {{ $active ? 'text-ember' : 'text-ink-faint group-hover:text-ink-muted' }}" />
    @endif
    <span class="truncate">{{ $slot }}</span>
    @if ($count !== null)
        <span class="ml-auto font-mono text-xs text-ink-faint">{{ $count }}</span>
    @endif
</a>
