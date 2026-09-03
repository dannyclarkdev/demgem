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

    <div class="mb-4 grid gap-4 md:grid-cols-2">
        <x-ui.card title="Next session">
            <x-slot:header>
                @can('create', [\App\Models\GameSession::class, $campaign])
                    <x-ui.button :href="route('sessions.create', $campaign)" variant="ghost" size="sm" icon="plus">Plan one</x-ui.button>
                @endcan
            </x-slot:header>
            @if ($nextSession === null)
                <p class="text-sm text-ink-faint">{{ $role->isDm() ? 'Nothing planned. The loop starts with a date.' : 'The GM has not scheduled the next game yet.' }}</p>
            @else
                @php($when = $nextSession->scheduledAtIn($timezone))
                <a href="{{ $nextSession->url() }}" class="block">
                    <p class="eyebrow">{{ $nextSession->label() }}</p>
                    <p class="mt-1 font-display text-xl font-semibold text-ink">{{ $nextSession->displayTitle() }}</p>
                    @if ($when)
                        <p class="mt-2 text-sm text-ink-muted">{{ $when->format('D j M Y') }} at {{ $when->format('H:i') }} {{ $when->format('T') }}</p>
                        <p class="mt-1 text-xs text-ink-faint">{{ $nextSession->scheduled_at->diffForHumans() }}</p>
                    @else
                        <p class="mt-2 text-sm text-ink-faint">Not scheduled yet.</p>
                    @endif
                </a>
            @endif
        </x-ui.card>

        <x-ui.card title="Latest recap">
            @if ($latestRecap === null)
                <p class="text-sm text-ink-faint">{{ $role->isDm() ? 'Publish a recap and the party can read it here.' : 'No recaps yet.' }}</p>
            @else
                <a href="{{ $latestRecap->url() }}" class="block">
                    <p class="eyebrow">{{ $latestRecap->label() }}</p>
                    <p class="mt-1 font-display text-xl font-semibold text-ink">{{ $latestRecap->displayTitle() }}</p>
                    <p class="mt-2 line-clamp-3 text-sm text-ink-muted">{{ $latestRecap->recapExcerpt() }}</p>
                </a>
            @endif
        </x-ui.card>
    </div>

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

</div>
