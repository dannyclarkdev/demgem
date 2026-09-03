{{--
    Polled, never live-bound. Every edit below is an explicit action, so a poll landing
    mid-typing cannot overwrite what the GM is entering.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s class="text-base">
    <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
        <x-ui.badge :variant="$encounter->status->badgeVariant()" :icon="$encounter->status->icon()">{{ $encounter->status->label() }}</x-ui.badge>

        @if ($encounter->round > 0)
            <span class="font-display text-lg font-semibold text-ink">Round {{ $encounter->round }}</span>
        @endif

        @if ($activeId && $combatants->firstWhere('id', $activeId))
            <span class="text-sm text-ink-muted">&middot; {{ $combatants->firstWhere('id', $activeId)->name }} is up</span>
        @endif

        <div class="ml-auto flex flex-wrap items-center gap-1.5">
            <x-ui.button size="sm" icon="skip-forward" wire:click="nextTurn">
                {{ $activeId ? 'Next turn' : 'Start' }}
            </x-ui.button>
            <x-ui.button variant="secondary" size="sm" icon="dice" wire:click="rollInitiative">Roll initiative</x-ui.button>
            <x-ui.button variant="ghost" size="sm" icon="arrow-down" wire:click="sortByInitiative">Sort</x-ui.button>
            @if ($encounter->status === \App\Enums\EncounterStatus::Done)
                <x-ui.button variant="ghost" size="sm" wire:click="reopenEncounter">Reopen</x-ui.button>
            @else
                <x-ui.button variant="ghost" size="sm" wire:click="endEncounter">End</x-ui.button>
            @endif
            <x-ui.button variant="ghost" size="icon" wire:click="resetEncounter" wire:confirm="Clear the round count and the turn marker?" aria-label="Reset the fight">
                <x-ui.icon name="refresh" class="size-4" />
            </x-ui.button>
        </div>
    </div>

    @if ($combatants->isEmpty())
        <p class="px-5 py-6 text-sm text-ink-faint">Nobody in the fight yet. Add the party, drop in a prepped monster, or type a name below.</p>
    @else
        <ul wire:sort="reorder" class="divide-y divide-line">
            @foreach ($combatants as $combatant)
                <li
                    wire:key="combatant-{{ $combatant->id }}"
                    wire:sort:item="{{ $combatant->id }}"
                    class="px-5 py-3 {{ $combatant->id === $activeId ? 'border-l-2 border-ember bg-ember/5' : '' }} {{ $combatant->isDown() ? 'opacity-60' : '' }}"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" wire:sort:handle class="cursor-grab text-ink-faint hover:text-ink-muted" aria-label="Drag to reorder">
                            <x-ui.icon name="grip" class="size-4" />
                        </button>

                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-line-strong bg-canvas font-mono text-sm font-semibold tabular-nums text-ink">
                            {{ $combatant->initiative ?? '—' }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 font-medium text-ink">
                                @if ($combatant->entity)
                                    <a href="{{ $combatant->entity->url() }}" target="_blank" rel="noopener" class="hover:text-ember">{{ $combatant->name }}</a>
                                @else
                                    {{ $combatant->name }}
                                @endif
                                @if ($combatant->isPlayerCharacter())
                                    <span class="text-xs font-normal text-ink-faint">PC</span>
                                @endif
                            </p>
                            @if ($combatant->conditionList() !== [])
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($combatant->conditionList() as $condition)
                                        <button type="button" wire:click="removeCondition('{{ $combatant->id }}', @js($condition))" aria-label="Remove {{ $condition }}">
                                            <x-ui.badge variant="danger">{{ $condition }} <x-ui.icon name="x" class="size-2.5" /></x-ui.badge>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($combatant->ac !== null)
                            <span class="hidden shrink-0 items-center gap-1 text-sm text-ink-muted sm:flex" title="Armour class">
                                <x-ui.icon name="shield" class="size-3.5 text-ink-faint" />{{ $combatant->ac }}
                            </span>
                        @endif

                        @if ($combatant->hp !== null)
                            <span class="shrink-0 font-mono text-sm tabular-nums {{ $combatant->isDown() ? 'text-danger' : 'text-ink' }}">
                                {{ $combatant->hp }}@if ($combatant->max_hp)<span class="text-ink-faint">/{{ $combatant->max_hp }}</span>@endif
                            </span>
                        @endif

                        <div wire:sort:ignore class="ml-auto flex shrink-0 items-center gap-0.5">
                            @if ($combatant->hp !== null)
                                <x-ui.button variant="ghost" size="icon" wire:click="openDamage('{{ $combatant->id }}')" aria-label="Damage or heal">
                                    <x-ui.icon name="heart" class="size-4" />
                                </x-ui.button>
                            @endif
                            <x-ui.button variant="ghost" size="icon" wire:click="openConditions('{{ $combatant->id }}')" aria-label="Conditions">
                                <x-ui.icon name="alert" class="size-4" />
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $combatant->id }}', -1)" :disabled="$loop->first" aria-label="Move up">
                                <x-ui.icon name="arrow-up" class="size-4" />
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="icon" wire:click="move('{{ $combatant->id }}', 1)" :disabled="$loop->last" aria-label="Move down">
                                <x-ui.icon name="arrow-down" class="size-4" />
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="icon" wire:click="removeCombatant('{{ $combatant->id }}')" wire:confirm="Remove {{ $combatant->name }} from the fight?" aria-label="Remove">
                                <x-ui.icon name="trash" class="size-4" />
                            </x-ui.button>
                        </div>
                    </div>

                    @if ($damageFor === $combatant->id)
                        <form wire:submit="applyDamage('{{ $combatant->id }}', 1)" class="mt-3 flex flex-wrap items-end gap-2 rounded-md border border-line bg-canvas p-3">
                            <div class="w-24">
                                <x-ui.input type="number" name="damage" wire:model.blur="damage" label="Amount" autofocus />
                            </div>
                            <x-ui.button type="submit" size="sm" icon="minus">Damage</x-ui.button>
                            <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="applyDamage('{{ $combatant->id }}', -1)">Heal</x-ui.button>
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeDamage">Cancel</x-ui.button>
                        </form>
                    @endif

                    @if ($editingConditionsFor === $combatant->id)
                        <form wire:submit="addCondition('{{ $combatant->id }}')" class="mt-3 flex flex-wrap items-end gap-2 rounded-md border border-line bg-canvas p-3">
                            <div class="min-w-44 flex-1">
                                <x-ui.input name="newCondition" wire:model="newCondition" label="Condition" list="common-conditions" autofocus />
                                <datalist id="common-conditions">
                                    @foreach ($commonConditions as $condition)
                                        <option value="{{ $condition }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <x-ui.button type="submit" size="sm" icon="plus">Add</x-ui.button>
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeConditions">Done</x-ui.button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="space-y-3 border-t border-line px-5 py-4">
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($party->isNotEmpty())
                <x-ui.button variant="secondary" size="sm" icon="users" wire:click="addParty">Add the party</x-ui.button>
            @endif
            @foreach ($prepped as $monster)
                <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addEntity('{{ $monster->id }}')">{{ $monster->name }}</x-ui.button>
            @endforeach
        </div>

        <form wire:submit="addCombatant" class="flex flex-wrap items-end gap-2">
            <div class="min-w-40 flex-1">
                <x-ui.input label="Add a combatant" name="newName" wire:model="newName" placeholder="Goblin" />
            </div>
            <div class="w-20">
                <x-ui.input type="number" label="How many" name="newQuantity" wire:model="newQuantity" min="1" max="20" />
            </div>
            <div class="w-20">
                <x-ui.input type="number" label="HP" name="newHp" wire:model="newHp" />
            </div>
            <div class="w-20">
                <x-ui.input type="number" label="AC" name="newAc" wire:model="newAc" />
            </div>
            <div class="w-20">
                <x-ui.input type="number" label="Init +" name="newInitiativeBonus" wire:model="newInitiativeBonus" />
            </div>
            <x-ui.button type="submit" icon="plus">Add</x-ui.button>
        </form>
    </div>
</div>
