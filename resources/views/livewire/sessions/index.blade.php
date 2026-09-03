<div>
    <x-ui.page-header title="Sessions" :eyebrow="$campaign->name" description="Prep, play, recap. The loop the campaign runs on.">
        @can('create', [\App\Models\GameSession::class, $campaign])
            <x-ui.button :href="route('sessions.create', $campaign)" icon="plus">New session</x-ui.button>
        @endcan
    </x-ui.page-header>

    @if ($total > 0 || $search !== '')
        <div class="mb-4 relative min-w-56 sm:max-w-xs">
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Filter by number or title" class="ui-input pl-9" aria-label="Filter sessions">
        </div>
    @endif

    @if ($total === 0)
        @if ($search !== '')
            <x-ui.empty-state title="Nothing matches" description="Clear the filter to see every session you can view." icon="calendar">
                <x-ui.button variant="secondary" size="sm" wire:click="$set('search', '')">Clear filter</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state
                title="No sessions yet"
                :description="$role->isDm()
                    ? 'Plan the first one. Give it a date, prep a strong start, and the loop begins.'
                    : 'The GM has not scheduled a session yet.'"
                icon="calendar"
            >
                @can('create', [\App\Models\GameSession::class, $campaign])
                    <x-ui.button :href="route('sessions.create', $campaign)" icon="plus">Plan session 1</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @endif
    @else
        <div class="space-y-6">
            @if ($upcoming->isNotEmpty())
                <x-ui.card title="Upcoming" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($upcoming as $session)
                            <x-session-item :session="$session" :role="$role" :timezone="$timezone" />
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($needsRecap->isNotEmpty())
                <x-ui.card title="Needs a recap" :padding="false">
                    <x-slot:header>
                        <span class="text-xs text-ink-faint">Played, and the party is waiting</span>
                    </x-slot:header>
                    <ul class="divide-y divide-line">
                        @foreach ($needsRecap as $session)
                            <x-session-item :session="$session" :role="$role" :timezone="$timezone" />
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($past->isNotEmpty())
                <x-ui.card title="Past" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($past as $session)
                            <x-session-item :session="$session" :role="$role" :timezone="$timezone" />
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    @endif
</div>
