{{--
    What the wall shows, and the buttons that change it. GM only: the lists are
    empty for anyone else, and every action authorises useGmTools on its own.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s class="space-y-4">
    @php
        $showing = match (true) {
            $focus === null => 'Nothing chosen. The wall shows the fight while one runs, then the party.',
            $page !== null => $page->name.' is on the screen.',
            default => $focus->label().' is on the screen.',
        };
    @endphp
    <p class="text-sm text-ink-muted">{{ $showing }}</p>

    @if ($canManage)
        <div class="flex flex-wrap gap-1.5">
            <x-ui.button
                size="sm"
                :variant="$focus === \App\Enums\ScreenFocus::Fight ? 'primary' : 'secondary'"
                icon="swords"
                wire:click="focus('fight')"
                :disabled="! $hasFight"
                :title="$hasFight ? 'Put the turn order on the screen' : 'No fight running'"
            >The fight</x-ui.button>
            <x-ui.button
                size="sm"
                :variant="$focus === \App\Enums\ScreenFocus::Clocks ? 'primary' : 'secondary'"
                icon="clock"
                wire:click="focus('clocks')"
                :disabled="! $hasClocks"
                :title="$hasClocks ? 'Put the revealed clocks on the screen' : 'No clock the party can see'"
            >The clocks</x-ui.button>
            @if ($focus !== null)
                <x-ui.button size="sm" variant="ghost" icon="x" wire:click="clear">Clear</x-ui.button>
            @endif
        </div>

        @if ($handouts->isNotEmpty())
            <div>
                <p class="eyebrow mb-1.5">Handouts</p>
                <ul class="divide-y divide-line">
                    @foreach ($handouts as $handout)
                        @php($isUp = $page?->id === $handout->id)
                        <li wire:key="screen-handout-{{ $handout->id }}" class="flex items-center gap-2 py-2">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink">{{ $handout->name }}</span>
                                @if ($handout->visibility === \App\Enums\Visibility::Dm)
                                    <span class="block text-xs text-dm">Hidden. Putting it up shows the party.</span>
                                @endif
                            </span>
                            <x-ui.button
                                size="sm"
                                :variant="$isUp ? 'primary' : 'secondary'"
                                icon="monitor"
                                wire:click="showPage('{{ $handout->id }}')"
                                :disabled="$isUp"
                            >{{ $isUp ? 'Up' : 'On the screen' }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($maps->isNotEmpty())
            <div>
                <p class="eyebrow mb-1.5">Maps</p>
                <ul class="divide-y divide-line">
                    @foreach ($maps as $map)
                        @php($isUp = $page?->id === $map->id)
                        <li wire:key="screen-map-{{ $map->id }}" class="flex items-center gap-2 py-2">
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ $map->name }}</span>
                            <x-ui.button
                                size="sm"
                                :variant="$isUp ? 'primary' : 'secondary'"
                                icon="monitor"
                                wire:click="showPage('{{ $map->id }}')"
                                :disabled="$isUp"
                            >{{ $isUp ? 'Up' : 'On the screen' }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.button :href="route('screen', $campaign)" target="_blank" variant="ghost" size="sm" icon="external" class="w-full">
            Open the screen in a new tab
        </x-ui.button>
    @endif
</div>
