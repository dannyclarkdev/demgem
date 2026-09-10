<div>
    <x-ui.page-header
        title="Compendium"
        :eyebrow="$campaign->name"
        :description="$hasShipped
            ? 'Your own creatures and every one in '.$campaign->ruleset->label().'. Nothing shipped belongs to your campaign until you put it in a fight. GMs only.'
            : 'The creatures you write. This campaign runs no ruleset with a shipped book, so what is here is yours. GMs only.'"
    >
        <x-ui.button icon="plus" :href="route('compendium.create', $campaign)" wire:navigate>New creature</x-ui.button>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-56 flex-1">
                <x-ui.input
                    label="Search"
                    name="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="goblin"
                    autocomplete="off"
                />
            </div>
            <div class="min-w-40">
                <x-ui.select label="Type" name="creatureType" wire:model.live="creatureType">
                    <option value="">Any type</option>
                    @foreach ($creatureTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="min-w-44">
                <x-ui.select label="Challenge" name="challenge" wire:model.live="challenge">
                    <option value="">Any challenge</option>
                    @foreach ($this->challengeBands() as $key => $band)
                        <option value="{{ $key }}">{{ $band['label'] }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            @if ($ownCount > 0 && $hasShipped)
                <x-ui.checkbox label="Only mine" name="mine" wire:model.live="mine" />
            @endif
            @if ($search !== '' || $creatureType !== '' || $challenge !== '' || $mine)
                <x-ui.button variant="ghost" wire:click="clearFilters">Clear</x-ui.button>
            @endif
        </div>
    </x-ui.card>

    @if ($statBlocks->isEmpty())
        @if ($ownCount === 0 && ! $hasShipped && $search === '' && $creatureType === '' && $challenge === '')
            <x-ui.empty-state
                title="No creatures yet"
                description="Write one and it turns up here, in the tracker's picker, and on any NPC that fights as it."
                icon="book-open"
            />
        @else
            <x-ui.empty-state
                title="Nothing matches"
                description="Widen the challenge band or clear the type filter."
                icon="search"
            />
        @endif
    @else
        <x-ui.card :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($statBlocks as $statBlock)
                    <li wire:key="stat-block-{{ $statBlock->id }}">
                        <a
                            href="{{ route('compendium.show', [$campaign, $statBlock->slug]) }}"
                            wire:navigate
                            class="flex flex-wrap items-center gap-3 px-5 py-3 hover:text-ember"
                        >
                            <x-ui.icon name="book-open" class="size-4 shrink-0 text-ink-faint" />
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate font-medium text-ink">{{ $statBlock->name }}</span>
                                    @if (! $statBlock->isShipped())
                                        <x-ui.badge variant="dm">Yours</x-ui.badge>
                                    @endif
                                </span>
                                <span class="block truncate text-xs text-ink-faint">{{ $statBlock->type_line }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-ink-faint">AC {{ $statBlock->ac }}</span>
                            <span class="shrink-0 text-xs text-ink-faint">HP {{ $statBlock->hp }}</span>
                            <x-ui.badge class="shrink-0">CR {{ $statBlock->cr }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <div class="mt-4">{{ $statBlocks->links() }}</div>
    @endif

    {{-- The notice belongs to the shipped rows and to nothing else. Rendering it under
         a list of a GM's own creatures would be a false claim about who wrote them. --}}
    @if ($hasShipped)
        <x-compendium.attribution class="mt-6" />
    @endif
</div>
