<x-ui.card title="Body history">
    <div class="space-y-4">
        <p class="text-sm text-ink-muted">Earlier saved bodies are kept without expiry and are visible only to GMs. Automatic wiki-link renames are not recorded.</p>
        <x-ui.button type="button" variant="secondary" size="sm" wire:click="$toggle('open')">{{ $open ? 'Close history' : 'Open history' }}</x-ui.button>
        @if ($open)
            <ul class="space-y-2">
                @forelse ($revisions as $revision)
                    <li wire:key="revision-{{ $revision->id }}" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-ink-muted">Replaced {{ $revision->recorded_at->timezone($campaign->timezone)->format('j M Y, H:i:s T') }} by {{ $revision->replaced_by_name ?? 'Unknown' }}</span>
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="select('{{ $revision->id }}')">Inspect</x-ui.button>
                    </li>
                @empty
                    <li class="text-sm text-ink-faint">No earlier bodies yet. The next body change will preserve the current text.</li>
                @endforelse
            </ul>
            {{ $revisions->links() }}
            @if ($selected)
                <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                    <div class="min-w-0 space-y-2">
                        <p class="eyebrow">Current body</p>
                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words rounded border border-line bg-raised p-3 text-sm text-ink">{{ $currentBody === null || $currentBody === '' ? '(Empty body)' : $currentBody }}</pre>
                    </div>
                    <div class="min-w-0 space-y-2">
                        <p class="eyebrow">Earlier body</p>
                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words rounded border border-line bg-raised p-3 text-sm text-ink">{{ $selected->body === null || $selected->body === '' ? '(Empty body)' : $selected->body }}</pre>
                    </div>
                </div>
                <x-ui.button type="button" variant="secondary" wire:click="restore" wire:confirm="Restore this body? The current body will be kept in history. All other fields stay as they are." wire:loading.attr="disabled">Restore this body</x-ui.button>
            @endif
        @endif
    </div>
</x-ui.card>
