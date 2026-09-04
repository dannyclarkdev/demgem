<div>
    <x-ui.page-header title="Campaigns" eyebrow="Library" description="Every table you run or play at.">
        <x-ui.button :href="route('campaigns.import')" variant="secondary" icon="download">Import</x-ui.button>
        <x-ui.button :href="route('campaigns.create')" icon="plus">New campaign</x-ui.button>
    </x-ui.page-header>

    @if ($campaigns->isEmpty())
        <x-ui.empty-state title="No campaigns yet" description="Create your first campaign to start building its world, or ask a game master for an invite link." icon="book-open">
            <x-ui.button :href="route('campaigns.create')" icon="plus">New campaign</x-ui.button>
        </x-ui.empty-state>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($campaigns as $campaign)
                <li>
                    <a href="{{ route('campaigns.show', $campaign) }}" class="group flex h-full flex-col overflow-hidden rounded-lg border border-line bg-panel transition hover:border-line-strong hover:bg-raised/40">
                        @if ($campaign->coverUrl())
                            <img src="{{ $campaign->coverUrl('card') }}" alt="" class="h-28 w-full object-cover">
                        @endif
                        <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start gap-3">
                            <h2 class="font-display text-xl font-semibold tracking-tight group-hover:text-ember">{{ $campaign->name }}</h2>
                            <x-ui.badge :variant="$campaign->pivot->role->isDm() ? 'accent' : 'neutral'" class="ml-auto shrink-0">{{ $campaign->pivot->role->label() }}</x-ui.badge>
                        </div>
                        @if ($campaign->description)
                            <p class="mt-2 line-clamp-2 text-sm text-ink-muted">{{ $campaign->description }}</p>
                        @endif
                        <div class="mt-auto flex items-center gap-3 pt-4 text-xs text-ink-faint">
                            <span class="inline-flex items-center gap-1"><x-ui.icon name="users" class="size-3.5" /> {{ $campaign->members_count }}</span>
                            <span>{{ $campaign->ruleset->label() }}</span>
                        </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
