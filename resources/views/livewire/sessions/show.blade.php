@php
    $when = $session->scheduledAtIn($timezone);
@endphp
<div>
    <x-ui.page-header :title="$session->displayTitle()" :eyebrow="$session->label()">
        <x-ui.badge :variant="$session->status->badgeVariant()" :icon="$session->status->icon()">{{ $session->status->label() }}</x-ui.badge>
        @if ($role->isDm() && $session->visibility === \App\Enums\Visibility::Dm)
            <x-ui.badge variant="dm" icon="eye-off">GM only</x-ui.badge>
        @endif
        @can('update', $session)
            <x-ui.button :href="route('sessions.prep', [$campaign, $session->number])" variant="secondary" size="sm" icon="zap">Prep</x-ui.button>
            <x-ui.button :href="route('sessions.edit', [$campaign, $session->number])" variant="ghost" size="sm" icon="edit">Edit</x-ui.button>
        @endcan
        @can('delete', $session)
            <x-ui.button
                variant="ghost"
                size="sm"
                icon="trash"
                wire:click="delete"
                wire:confirm="{{ $session->hasPublishedRecap()
                    ? 'Delete '.$session->label().'? The party loses the recap they can read today. Secrets go back to the pool.'
                    : 'Delete '.$session->label().'? Secrets go back to the pool.' }}"
            >Delete</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <x-ui.card title="Recap">
                @if ($recapHtml === null)
                    <p class="text-sm text-ink-faint">
                        @if ($role->isDm())
                            No recap yet. Write one after you play, then publish it for the party.
                        @else
                            The GM has not published a recap for this session.
                        @endif
                    </p>
                @elseif ($recapHtml === '')
                    <p class="text-sm text-ink-faint">The recap is empty.</p>
                @else
                    <div class="prose-entity">{!! $recapHtml !!}</div>
                    @if ($role->isDm() && ! $session->hasPublishedRecap())
                        <p class="mt-4 flex items-center gap-1.5 text-xs text-dm"><x-ui.icon name="eye-off" class="size-3.5" /> Not published. Only GMs can read this.</p>
                    @endif
                @endif
            </x-ui.card>
        </div>

        <aside class="space-y-4">
            <x-ui.card title="When">
                @if ($when)
                    <p class="font-display text-lg font-semibold">{{ $when->format('D j M Y') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ $when->format('H:i') }} {{ $when->format('T') }}</p>
                    <p class="mt-3 text-xs text-ink-faint">{{ $session->scheduled_at->diffForHumans() }}</p>
                @else
                    <p class="text-sm text-ink-faint">No date yet.</p>
                @endif
            </x-ui.card>
        </aside>
    </div>
</div>
