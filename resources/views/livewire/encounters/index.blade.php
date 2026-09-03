<div>
    <x-ui.page-header title="Encounters" :eyebrow="$campaign->name" description="Turn order, hit points, and conditions. GMs only." />

    <x-ui.card class="mb-4">
        <form wire:submit="create" class="flex flex-wrap items-end gap-2">
            <div class="min-w-56 flex-1">
                <x-ui.input label="New encounter" name="newName" wire:model="newName" placeholder="Ambush at the toll bridge" />
            </div>
            <x-ui.button type="submit" icon="plus">Create</x-ui.button>
        </form>
    </x-ui.card>

    @if ($encounters->isEmpty())
        <x-ui.empty-state
            title="No encounters yet"
            description="Build one now and it waits with its monsters in it, or start one from the Run screen when the fight begins."
            icon="swords"
        />
    @else
        <x-ui.card :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($encounters as $encounter)
                    <li wire:key="encounter-{{ $encounter->id }}" class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <a href="{{ $encounter->url() }}" class="flex min-w-0 flex-1 items-center gap-3 hover:text-ember">
                            <x-ui.icon name="swords" class="size-4 shrink-0 text-ink-faint" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-ink">{{ $encounter->name }}</span>
                                <span class="block truncate text-xs text-ink-faint">
                                    {{ $encounter->combatants_count }} {{ Str::plural('combatant', $encounter->combatants_count) }}
                                    @if ($encounter->gameSession)
                                        &middot; {{ $encounter->gameSession->label() }}
                                    @endif
                                    @if ($encounter->round > 0)
                                        &middot; round {{ $encounter->round }}
                                    @endif
                                </span>
                            </span>
                        </a>
                        <x-ui.badge :variant="$encounter->status->badgeVariant()" :icon="$encounter->status->icon()">{{ $encounter->status->label() }}</x-ui.badge>
                        <x-ui.button
                            variant="ghost"
                            size="icon"
                            wire:click="delete('{{ $encounter->id }}')"
                            wire:confirm="Delete {{ $encounter->name }} and its {{ $encounter->combatants_count }} {{ Str::plural('combatant', $encounter->combatants_count) }}? This cannot be undone."
                            aria-label="Delete encounter"
                        >
                            <x-ui.icon name="trash" class="size-4" />
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
