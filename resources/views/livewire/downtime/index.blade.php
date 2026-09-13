<div>
    <x-ui.page-header
        title="Downtime"
        :eyebrow="$campaign->name"
        :description="$role->isDm()
            ? 'What each character did between sessions, and what it cost in days. Write on anyone; a player writes on their own.'
            : 'What the party did between sessions. Write what your character did, and how many days it took.'"
    >
        <x-ui.button :href="route('sessions.index', $campaign)" variant="secondary" size="sm" icon="calendar">Sessions</x-ui.button>
    </x-ui.page-header>

    <div class="max-w-3xl space-y-6">
        @if ($totals->isNotEmpty())
            <x-ui.card title="Days spent" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($totals as $row)
                        <li wire:key="downtime-total-{{ $row['character']->id }}" class="flex items-center gap-3 px-5 py-3">
                            <a href="{{ $row['character']->url() }}" class="min-w-0 flex-1 truncate font-medium text-ink hover:text-ember">{{ $row['character']->name }}</a>
                            <span class="shrink-0 font-mono text-sm tabular-nums text-ink-muted">{{ \App\Models\DowntimeActivity::dayCount($row['days']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <livewire:downtime.log :campaign="$campaign" :wire:key="'downtime-'.$campaign->id" />
    </div>
</div>
