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

    <x-ui.card title="The party" class="mb-4" :padding="false">
        <x-slot:header>
            <a href="{{ route('entities.index', [$campaign, 'characters', 'pc' => 1]) }}" class="text-xs text-ink-faint hover:text-ink">All player characters</a>
        </x-slot:header>

        @if ($party->isEmpty())
            <p class="px-5 py-4 text-sm text-ink-faint">
                {{ $role->isDm() ? 'Mark a character as a player character and the party turns up here.' : 'No player characters yet.' }}
            </p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($party as $pc)
                    <li>
                        <a href="{{ $pc->url() }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-raised">
                            @if ($pc->imageUrl('thumb'))
                                <img src="{{ $pc->imageUrl('thumb') }}" alt="" class="size-8 shrink-0 rounded-md object-cover">
                            @else
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-raised text-ink-muted">
                                    <x-ui.icon name="user" class="size-4" />
                                </span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $pc->name }}</span>
                                @if (filled($pc->character_class) || $pc->level !== null)
                                    <span class="block truncate text-sm text-ink-faint">{{ collect([$pc->character_class, $pc->level !== null ? 'level '.$pc->level : null])->filter()->implode(' · ') }}</span>
                                @endif
                            </span>
                            @if ($pc->player)
                                <span class="shrink-0 text-sm text-ink-muted">{{ $pc->player->name }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="Quests in play" class="mb-4" :padding="false">
        <x-slot:header>
            <a href="{{ route('entities.index', [$campaign, 'quests', 'status' => 'active']) }}" class="text-xs text-ink-faint hover:text-ink">All active quests</a>
        </x-slot:header>

        @if ($activeQuests->isEmpty())
            <p class="px-5 py-4 text-sm text-ink-faint">
                {{ $role->isDm() ? 'Set a quest to active and it turns up here and on the Run screen.' : 'The party has not taken on anything yet.' }}
            </p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($activeQuests as $quest)
                    @php ($progress = $quest->objectiveProgress())
                    <li>
                        <a href="{{ $quest->url() }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-raised">
                            <x-ui.icon name="target" class="size-4 shrink-0 text-ember" />
                            <span class="min-w-0 flex-1 truncate font-medium text-ink">{{ $quest->name }}</span>
                            <x-ui.progress :value="$progress['done']" :max="$progress['total']" class="w-32 shrink-0" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
