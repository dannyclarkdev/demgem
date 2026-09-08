<div>
    <x-ui.page-header title="Timeline" :eyebrow="$campaign->name" :description="$calendar === null ? null : 'Every dated event and session, in the order the world lived them.'">
        @if ($calendar !== null)
            <x-ui.button :href="route('calendar.show', $campaign)" variant="ghost" size="sm" icon="sun">Calendar</x-ui.button>
        @endif
    </x-ui.page-header>

    @if ($calendar === null)
        <x-ui.empty-state title="No calendar yet" icon="sun" :description="$role->isDm() ? 'A timeline needs a calendar to count on. Set one up and dates follow.' : 'The GM has not set up the world\'s calendar yet.'">
            @if ($role->isDm())
                <x-ui.button :href="route('calendar.edit', $campaign)" icon="plus">Set up the calendar</x-ui.button>
            @endif
        </x-ui.empty-state>
    @elseif ($years === [])
        <x-ui.empty-state title="Nothing dated yet" icon="activity" :description="$role->isDm() ? 'Give an event a day, or a session an in-game date, and it lands here.' : 'Nothing in the world has a date on it yet.'">
            @if ($role->isDm())
                <x-ui.button :href="route('entities.create', [$campaign, 'events'])" variant="secondary" icon="plus">New event</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        <div class="space-y-8">
            @foreach ($years as $year)
                <section>
                    <h2 class="eyebrow mb-3">{{ $year['label'] }}</h2>
                    <ol class="relative ml-2 space-y-4 border-l border-line pl-6">
                        @foreach ($year['rows'] as $row)
                            @if ($row['kind'] === 'today')
                                <li class="relative">
                                    <span class="absolute -left-[1.85rem] top-1.5 size-3 rounded-full bg-ember ring-4 ring-canvas"></span>
                                    <p class="text-sm font-medium text-ember">Today</p>
                                    <p class="text-xs text-ink-faint">{{ $row['when'] }}</p>
                                </li>
                            @else
                                <li class="relative">
                                    <span class="absolute -left-[1.6rem] top-2 size-2 rounded-full {{ $row['kind'] === 'session' ? 'bg-ink-faint' : 'bg-line-strong' }}"></span>
                                    <p class="text-xs text-ink-faint">{{ $row['when'] }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-2">
                                        <x-ui.icon :name="$row['kind'] === 'session' ? 'calendar' : 'flag'" class="size-3.5 shrink-0 text-ink-faint" />
                                        <a href="{{ $row['url'] }}" class="font-medium text-ink hover:text-ember">{{ $row['title'] }}</a>
                                        @if ($row['subtitle'])
                                            <span class="text-xs text-ink-faint">{{ $row['subtitle'] }}</span>
                                        @endif
                                    </p>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
    @endif
</div>
