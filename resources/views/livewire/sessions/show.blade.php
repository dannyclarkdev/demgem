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
            <x-ui.button :href="route('sessions.run', [$campaign, $session->number])" size="sm" icon="play">Run</x-ui.button>
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
                <x-slot:header>
                    @if ($canEdit)
                        @if ($session->hasPublishedRecap())
                            <x-ui.badge variant="success" icon="eye">Published</x-ui.badge>
                        @else
                            <x-ui.badge variant="dm" icon="eye-off">Draft</x-ui.badge>
                        @endif
                    @endif
                </x-slot:header>

                @if ($canEdit)
                    <form wire:submit="saveRecap" class="space-y-4">
                        <x-ui.markdown-editor
                            name="recap"
                            wire:model="recap"
                            rows="10"
                            :autocomplete-url="$autocompleteUrl"
                            preview-action="previewRecap"
                            :preview="$recapPreview"
                            hint="What the party will remember. Link people and places with [[double brackets]]."
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button type="submit" variant="secondary">Save draft</x-ui.button>
                            @if ($session->hasPublishedRecap())
                                <x-ui.button type="button" variant="ghost" icon="eye-off" wire:click="unpublishRecap">Unpublish</x-ui.button>
                            @else
                                <x-ui.button type="button" icon="eye" wire:click="publishRecap">Publish recap</x-ui.button>
                            @endif
                            @if (! filled($session->recap) && filled($session->live_notes))
                                <x-ui.button type="button" variant="ghost" icon="zap" wire:click="startRecapFromLiveNotes">Start from live notes</x-ui.button>
                            @endif
                        </div>
                        <p class="text-xs text-ink-faint">
                            {{ $session->hasPublishedRecap()
                                ? 'The party can read this now.'
                                : 'Only GMs can read this. Publishing is a separate step from marking the session played.' }}
                        </p>
                    </form>
                @elseif ($recapHtml === null || $recapHtml === '')
                    <p class="text-sm text-ink-faint">The GM has not published a recap for this session.</p>
                @else
                    <div class="prose-entity">{!! $recapHtml !!}</div>
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
