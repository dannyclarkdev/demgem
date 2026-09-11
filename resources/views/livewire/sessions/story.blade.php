<div>
    <x-ui.page-header
        title="The story so far"
        :eyebrow="$campaign->name"
        :description="$role->isDm()
            ? 'Every recap in order. Drafts and missing recaps show here for you only.'
            : 'Everything the GM has written up, from the beginning.'"
    >
        <x-ui.button :href="route('sessions.index', $campaign)" variant="secondary" size="sm" icon="calendar">Sessions</x-ui.button>
    </x-ui.page-header>

    @if ($rewards['sessions'] > 0 || $rewards['milestones'] > 0)
        <div class="mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-lg border border-line bg-panel px-5 py-4">
            @if ($rewards['sessions'] > 0)
                <div>
                    <p class="eyebrow">XP earned</p>
                    <p class="mt-0.5 font-display text-xl font-semibold text-ink">{{ number_format($rewards['xp']) }}</p>
                    <p class="text-xs text-ink-faint">over {{ $rewards['sessions'] }} {{ Str::plural('session', $rewards['sessions']) }}</p>
                </div>
            @endif
            @if ($rewards['milestones'] > 0)
                <div>
                    <p class="eyebrow">Milestones</p>
                    <p class="mt-0.5 font-display text-xl font-semibold text-ink">{{ $rewards['milestones'] }}</p>
                    <p class="text-xs text-ink-faint">reached so far</p>
                </div>
            @endif
        </div>
    @endif

    @if ($sessions->isEmpty())
        <x-ui.empty-state
            title="No story yet"
            :description="$role->isDm()
                ? 'Play a session, write the recap, and publish it. The party reads it here.'
                : 'The GM has not published a recap yet.'"
            icon="book-open"
        >
            @can('create', [\App\Models\GameSession::class, $campaign])
                <x-ui.button :href="route('sessions.index', $campaign)" variant="secondary" size="sm">Go to sessions</x-ui.button>
            @endcan
        </x-ui.empty-state>
    @else
        <div class="space-y-6">
            @foreach ($sessions as $session)
                @php($when = $session->scheduledAtIn($timezone))
                <article class="rounded-lg border border-line bg-panel px-5 py-5 sm:px-6 sm:py-6">
                    <header class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">
                                {{ $session->label() }}
                                @if ($when)
                                    · {{ $when->format('D j M Y') }}
                                @endif
                            </p>
                            <h2 class="mt-1 font-display text-xl font-semibold text-ink">
                                <a href="{{ $session->url() }}" class="hover:text-ember">{{ $session->displayTitle() }}</a>
                            </h2>
                            @if ($reckoning !== null && $session->in_game_start !== null)
                                <p class="mt-1 text-sm text-ink-muted">In the world: {{ $reckoning->formatRange($session->in_game_start, $session->in_game_end) }}</p>
                            @endif
                            @if ($session->hasReward())
                                <p class="mt-1 flex items-center gap-1.5 text-sm text-ink-muted">
                                    <x-ui.icon name="zap" class="size-3.5 text-ink-faint" />
                                    <span>{{ $session->rewardLine() }}</span>
                                </p>
                            @endif
                        </div>

                        @if (! $session->hasPublishedRecap())
                            <x-ui.badge variant="dm" icon="eye-off">{{ filled($session->recap) ? 'Draft, not published' : 'No recap yet' }}</x-ui.badge>
                        @endif
                    </header>

                    @if (isset($recaps[$session->id]) && $recaps[$session->id] !== '')
                        <div class="prose-entity">{!! $recaps[$session->id] !!}</div>
                    @else
                        <p class="text-ink-faint">This session has no recap written yet.</p>
                    @endif

                    @can('update', $session)
                        @if (! $session->hasPublishedRecap())
                            <div class="mt-4">
                                <x-ui.button :href="$session->url()" variant="secondary" size="sm" icon="edit">
                                    {{ filled($session->recap) ? 'Finish and publish it' : 'Write the recap' }}
                                </x-ui.button>
                            </div>
                        @endif
                    @endcan
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $sessions->links() }}</div>
    @endif
</div>
