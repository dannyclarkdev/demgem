<div>
    @if ($calendar === null)
        <x-ui.page-header title="Calendar" :eyebrow="$campaign->name" />
        <x-ui.empty-state title="No calendar yet" icon="sun" :description="$role->isDm() ? 'Name the months, set the week, hang a moon or two, and say what day it is.' : 'The GM has not set up the world\'s calendar yet.'">
            @if ($role->isDm())
                <x-ui.button :href="route('calendar.edit', $campaign)" icon="plus">Set up the calendar</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        <x-ui.page-header :title="$todayFormatted" :eyebrow="$calendar->name" :description="collect($moonsToday)->map(fn ($moon) => $moon->phase->symbol().' '.$moon->describe())->implode(' · ') ?: null">
            @if ($role->isDm())
                <x-ui.button :href="route('calendar.edit', $campaign)" variant="secondary" size="sm" icon="settings">Edit the calendar</x-ui.button>
            @endif
        </x-ui.page-header>

        @if ($role->isDm())
            <x-ui.card title="Move the day" class="mb-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button variant="secondary" size="sm" wire:click="advance(-1)">A day back</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="advance(1)">Advance a day</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="advance(7)">A week</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" wire:click="advance(30)">Thirty days</x-ui.button>
                    </div>
                    <form wire:submit="setDate" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <x-ui.game-date name="date" :months="$reckoning->months" label="Set the date" required class="sm:w-80" />
                        <x-ui.button type="submit" size="md" wire:loading.attr="disabled">Set</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card :padding="false">
            <x-slot:header>
                <h2 class="font-display text-base font-semibold">{{ $monthTitle }}</h2>
                <div class="ml-auto flex items-center gap-1">
                    <x-ui.button variant="ghost" size="sm" wire:click="goToToday">Today</x-ui.button>
                    <x-ui.button variant="ghost" size="icon" icon="chevron-left" wire:click="previousMonth" aria-label="Previous month" />
                    <x-ui.button variant="ghost" size="icon" icon="chevron-right" wire:click="nextMonth" aria-label="Next month" />
                </div>
            </x-slot:header>

            @php($columns = count($weekdays) > 0 ? count($weekdays) : 7)
            <div class="overflow-x-auto">
                <div class="min-w-[40rem]">
                    @if (count($weekdays) > 0)
                        <div class="grid border-b border-line" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr))">
                            @foreach ($weekdays as $weekday)
                                <div class="truncate px-2 py-2 text-center text-xs font-medium uppercase tracking-wide text-ink-faint">{{ $weekday }}</div>
                            @endforeach
                        </div>
                    @endif
                    <div class="grid" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr))">
                        @for ($blank = 0; $blank < $leadingBlanks; $blank++)
                            <div class="min-h-20 border-b border-r border-line bg-raised/40"></div>
                        @endfor
                        @foreach ($days as $cell)
                            <div class="{{ $cell['isToday'] ? 'bg-ember/10 ring-1 ring-inset ring-ember' : '' }} min-h-20 border-b border-r border-line p-2" wire:key="day-{{ $cell['day'] }}">
                                <div class="flex items-start justify-between gap-1">
                                    <span class="text-sm font-medium tabular-nums {{ $cell['isToday'] ? 'text-ember' : 'text-ink' }}">{{ $cell['day'] }}</span>
                                    @if (count($cell['moons']) > 0)
                                        <span class="text-xs leading-none" title="{{ collect($cell['moons'])->map->describe()->implode(', ') }}">
                                            @foreach ($cell['moons'] as $moon)<span>{{ $moon->phase->symbol() }}</span>@endforeach
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif
</div>
