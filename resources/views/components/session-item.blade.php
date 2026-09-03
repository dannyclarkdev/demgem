@props(['session', 'role', 'timezone'])
@php
    $when = $session->scheduledAtIn($timezone);
@endphp
<li wire:key="session-{{ $session->id }}">
    <a href="{{ $session->url() }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-raised/50">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-md bg-raised font-display text-sm font-semibold text-ink">
            {{ $session->number }}
        </span>
        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-ink">{{ $session->displayTitle() }}</p>
            <p class="truncate text-xs text-ink-faint">
                @if ($when)
                    {{ $when->format('D j M Y') }} at {{ $when->format('H:i') }} {{ $when->format('T') }}
                @else
                    No date yet
                @endif
            </p>
        </div>
        <div class="hidden items-center gap-1.5 sm:flex">
            @if ($session->isOverdue() && $role->isDm())
                <x-ui.badge variant="danger" icon="clock">Overdue</x-ui.badge>
            @endif
            @if ($session->hasPublishedRecap())
                <x-ui.badge variant="success" icon="book-open">Recap</x-ui.badge>
            @elseif ($session->needsRecap() && $role->isDm())
                <x-ui.badge icon="edit">No recap</x-ui.badge>
            @endif
            @if ($role->isDm() && $session->visibility === \App\Enums\Visibility::Dm)
                <x-ui.badge variant="dm" icon="eye-off">GM only</x-ui.badge>
            @endif
            <x-ui.badge :variant="$session->status->badgeVariant()" :icon="$session->status->icon()">{{ $session->status->label() }}</x-ui.badge>
        </div>
        <x-ui.icon name="chevron-right" class="size-4 text-ink-faint" />
    </a>
</li>
