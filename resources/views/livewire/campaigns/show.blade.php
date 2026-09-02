<div>
    @if ($campaign->coverUrl())
        <img src="{{ $campaign->coverUrl('card') }}" alt="" class="mb-6 h-44 w-full rounded-lg border border-line object-cover">
    @endif

    <x-ui.page-header :title="$campaign->name" :eyebrow="$campaign->ruleset->label()" :description="$campaign->description">
        <x-ui.badge :variant="$role->isDm() ? 'accent' : 'neutral'">{{ $role->label() }}</x-ui.badge>
        @if ($role->isDm())
            <x-ui.button :href="route('campaigns.settings', $campaign)" variant="secondary" icon="settings" size="sm">Settings</x-ui.button>
        @endif
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('campaigns.members', $campaign) }}" class="rounded-lg border border-line bg-panel p-5 transition hover:border-line-strong">
            <p class="eyebrow">Members</p>
            <p class="mt-2 font-display text-3xl font-semibold">{{ $membersCount }}</p>
            <p class="mt-1 text-sm text-ink-muted">{{ $role->isDm() ? 'Invite players and manage roles.' : 'See who is at the table.' }}</p>
        </a>

        @if ($activeInvites !== null)
            <a href="{{ route('campaigns.members', $campaign) }}#invites" class="rounded-lg border border-line bg-panel p-5 transition hover:border-line-strong">
                <p class="eyebrow">Active invites</p>
                <p class="mt-2 font-display text-3xl font-semibold">{{ $activeInvites }}</p>
                <p class="mt-1 text-sm text-ink-muted">Share a link. Players join with one click.</p>
            </a>
        @endif

        <div class="rounded-lg border border-line bg-panel p-5">
            <p class="eyebrow">Started</p>
            <p class="mt-2 font-display text-3xl font-semibold">{{ $campaign->created_at->format('M j') }}</p>
            <p class="mt-1 text-sm text-ink-muted">{{ $campaign->created_at->format('Y') }}</p>
        </div>
    </div>

    <div class="mt-8">
        <x-ui.empty-state title="The world is empty" description="Characters, locations, factions, items, quests, and notes arrive in the next slice." icon="map-pin" />
    </div>
</div>
