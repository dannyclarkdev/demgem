<div>
    <x-ui.page-header
        title="Compendium"
        :eyebrow="$campaign->name"
        :description="'Every creature in '.$campaign->ruleset->label().'. Reference only: nothing here belongs to your campaign until you put it in a fight. GMs only.'"
    />

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
            @if ($search !== '' || $creatureType !== '' || $challenge !== '')
                <x-ui.button variant="ghost" wire:click="clearFilters">Clear</x-ui.button>
            @endif
        </div>
    </x-ui.card>

    @if ($statBlocks->isEmpty())
        <x-ui.empty-state
            title="Nothing matches"
            description="Widen the challenge band or clear the type filter."
            icon="search"
        />
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
                                <span class="block truncate font-medium text-ink">{{ $statBlock->name }}</span>
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

    <x-compendium.attribution class="mt-6" />
</div>
