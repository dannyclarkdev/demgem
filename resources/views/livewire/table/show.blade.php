{{--
    The player's screen. The fight while there is one, the dice the whole table
    shares, and something worth reading while there is not.

    Both this page and the nested fight poll at sixty seconds. A broadcast normally
    beats the poll to it; the poll is what covers a dropped socket and a slept laptop.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s>
    <x-ui.page-header
        title="The table"
        :eyebrow="$campaign->name"
        description="What is happening right now. It changes on its own, so leave it open."
    />

    <div class="mb-4">
        <livewire:table.presence :campaign="$campaign" :wire:key="'table-presence'" />
    </div>

    @if ($fight)
        <x-ui.card :padding="false" class="mb-4">
            {{-- The GM's own label for the fight, and a GM writes it like a note. The
                 party gets the turn order; they do not get "The betrayal at the ford". --}}
            @if ($role->isDm())
                <x-slot:header>
                    <span class="truncate text-xs text-ink-faint">{{ $fight->name }}</span>
                </x-slot:header>
            @endif
            <livewire:table.fight :campaign="$campaign" :encounter-id="$fight->id" :wire:key="'fight-'.$fight->id" />
        </x-ui.card>
    @else
        <x-ui.empty-state
            icon="swords"
            title="No fight running"
            description="When the GM starts one, the turn order turns up here on its own."
            class="mb-4"
        />
    @endif

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <x-ui.card title="Dice" :padding="false">
            @if ($mayRoll)
                <div class="border-b border-line">
                    <livewire:dice.tray :campaign="$campaign" :wire:key="'table-tray'" />
                </div>
            @endif
            <div class="h-80">
                <livewire:dice.log :campaign="$campaign" :wire:key="'table-dice-log'" />
            </div>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card title="The party" :padding="false">
                @if ($party->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-faint">No player characters yet.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($party as $pc)
                            <li>
                                <a href="{{ $pc->url() }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-raised">
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

            <x-ui.card title="Last time">
                @if ($latestRecap === null)
                    <p class="text-sm text-ink-faint">No recaps yet.</p>
                @else
                    <a href="{{ $latestRecap->url() }}" class="block">
                        <p class="eyebrow">{{ $latestRecap->label() }}</p>
                        <p class="mt-1 font-display text-xl font-semibold text-ink">{{ $latestRecap->displayTitle() }}</p>
                        <p class="mt-2 line-clamp-4 text-sm text-ink-muted">{{ $latestRecap->recapExcerpt() }}</p>
                    </a>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
