<div class="mx-auto max-w-2xl space-y-6">
    <x-ui.page-header title="Calendar" :eyebrow="$campaign->name" description="The months, the week, the moons, and the day it is in the world.">
        <x-ui.button :href="route('calendar.show', $campaign)" variant="ghost" size="sm">Back to the calendar</x-ui.button>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="The reckoning">
            <div class="space-y-5">
                <x-ui.input label="Name" name="name" wire:model="name" required hint="What the world calls its calendar." />
                <x-ui.input label="Era" name="era" wire:model="era" maxlength="12" hint="Printed after the year: 1042 AR. Leave it blank for a bare year." />
                <div class="flex justify-end">
                    <x-ui.button type="button" variant="ghost" size="sm" wire:click="useEarth" wire:confirm="Replace the months, week, moons, and leap rule with the Earth's?">Start from the Earth</x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Months">
            <x-slot:header>
                <x-ui.button type="button" variant="ghost" size="sm" icon="plus" wire:click="addMonth" :disabled="count($months) >= $maxMonths">Add a month</x-ui.button>
            </x-slot:header>
            @error('months')<p class="mb-3 text-sm text-danger">{{ $message }}</p>@enderror
            <ol class="space-y-3">
                @foreach ($months as $index => $month)
                    <li class="grid grid-cols-[2rem_1fr_6rem_auto] items-end gap-3" wire:key="month-{{ $index }}">
                        <span class="pb-2 text-sm tabular-nums text-ink-faint">{{ $index + 1 }}.</span>
                        <x-ui.input name="months.{{ $index }}.name" wire:model="months.{{ $index }}.name" placeholder="Month name" :label="$loop->first ? 'Name' : null" />
                        <x-ui.input name="months.{{ $index }}.days" type="number" min="1" max="999" wire:model="months.{{ $index }}.days" :label="$loop->first ? 'Days' : null" />
                        <x-ui.button type="button" variant="ghost" size="icon" icon="trash" wire:click="removeMonth({{ $index }})" :disabled="count($months) <= 1" aria-label="Remove month" />
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <x-ui.card title="The week">
            <x-ui.input label="Weekdays" name="weekdays" wire:model="weekdays" hint="In order, separated by commas. Leave it blank for a world with no week. The first day of year 1 is the first weekday." />
        </x-ui.card>

        <x-ui.card title="Moons">
            <x-slot:header>
                <x-ui.button type="button" variant="ghost" size="sm" icon="plus" wire:click="addMoon" :disabled="count($moons) >= $maxMoons">Add a moon</x-ui.button>
            </x-slot:header>
            @if (count($moons) === 0)
                <p class="text-sm text-ink-faint">No moons. A world can have up to {{ $maxMoons }}.</p>
            @else
                <ol class="space-y-3">
                    @foreach ($moons as $index => $moon)
                        <li class="grid grid-cols-[1fr_6rem_6rem_auto] items-end gap-3" wire:key="moon-{{ $index }}">
                            <x-ui.input name="moons.{{ $index }}.name" wire:model="moons.{{ $index }}.name" placeholder="Moon name" :label="$loop->first ? 'Name' : null" />
                            <x-ui.input name="moons.{{ $index }}.cycle" type="number" min="1" max="999" step="0.01" wire:model="moons.{{ $index }}.cycle" :label="$loop->first ? 'Cycle, days' : null" />
                            <x-ui.input name="moons.{{ $index }}.offset" type="number" min="0" max="999" wire:model="moons.{{ $index }}.offset" :label="$loop->first ? 'Offset' : null" />
                            <x-ui.button type="button" variant="ghost" size="icon" icon="trash" wire:click="removeMoon({{ $index }})" aria-label="Remove moon" />
                        </li>
                    @endforeach
                </ol>
                <p class="mt-3 text-xs text-ink-faint">The offset is how many days into its cycle the moon was on the first day of year 1. Zero means a new moon that day.</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Leap years">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Every" name="leapEvery" type="number" min="2" max="1000" wire:model="leapEvery" hint="Years. Blank means no leap years." />
                <x-ui.select label="Month that gains a day" name="leapMonth" wire:model="leapMonth">
                    <option value="">None</option>
                    @foreach ($months as $index => $month)
                        <option value="{{ $index + 1 }}">{{ filled($month['name']) ? $month['name'] : 'Month '.($index + 1) }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </x-ui.card>

        <x-ui.card title="Today in the world">
            <x-ui.game-date name="current" :months="$months" label="The current date" />
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <x-ui.button :href="route('calendar.show', $campaign)" variant="ghost">Cancel</x-ui.button>
            <x-ui.button type="submit" wire:loading.attr="disabled">Save calendar</x-ui.button>
        </div>
    </form>
</div>
