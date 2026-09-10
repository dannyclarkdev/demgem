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
            <x-ui.button variant="ghost" size="icon" wire:click="openLair" :title="$encounter->hasLairAction() ? 'Edit the lair action' : 'Add a lair action'" aria-label="Lair action">
                <x-ui.icon name="flag" class="size-4 {{ $encounter->hasLairAction() ? 'text-ember' : '' }}" />
            </x-ui.button>
            <x-ui.button variant="ghost" size="icon" wire:click="duplicate" title="Build this fight again, ready to run" aria-label="Duplicate this fight">
                <x-ui.icon name="copy" class="size-4" />
            </x-ui.button>
            <x-ui.button variant="ghost" size="icon" wire:click="resetEncounter" wire:confirm="Clear the round count and the turn marker?" aria-label="Reset the fight">
                <x-ui.icon name="refresh" class="size-4" />
            </x-ui.button>
        </div>
    </div>

    {{-- What the fight costs, against what this party can afford.

         The numbers are demgem's own and the line says so: the SRD prices a creature
         but publishes no encounter budget, and the book that does is not ours to copy.
         config/encounters.php holds the rule the read-out is named after. --}}
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line bg-canvas px-5 py-2 text-sm">
        @if ($budget->hasParty())
            <x-ui.badge :variant="$difficulty->badgeVariant()">{{ $difficulty->label() }}</x-ui.badge>
            <span class="font-mono tabular-nums text-ink">{{ number_format($spent) }} XP</span>
            <span class="text-ink-faint">
                &middot; {{ $budget->characters }} {{ Str::plural('character', $budget->characters) }}:
                low {{ number_format($budget->low) }},
                moderate {{ number_format($budget->moderate) }},
                high {{ number_format($budget->high) }}
            </span>
            {{-- With rows the compendium cannot price, the band is a floor rather than a
                 verdict, and it says so. A GM reading "Trivial" over a fight of five
                 hand-typed monsters would be reading it wrong. --}}
            @if ($unpriced > 0)
                <span class="text-ink-muted" title="A row typed by hand has no XP to read, and a guess would be a number with nothing behind it.">
                    &middot; {{ $unpriced }} {{ Str::plural('row', $unpriced) }} not priced, so this is a floor
                </span>
            @endif
            <span class="ml-auto text-xs text-ink-faint" title="{{ $difficulty->description() }}">demgem's own scale</span>
        @else
            <span class="text-ink-faint">Add the party to see what this fight is worth to them.</span>
        @endif
    </div>

    @if ($editingLair)
        <form wire:submit="saveLair" class="space-y-2 border-b border-line bg-canvas px-5 py-4">
            <x-ui.textarea
                label="Lair action"
                name="lairNote"
                wire:model="lairNote"
                rows="3"
                placeholder="The cavern floor buckles. Every creature on it makes a DC 14 Dexterity save."
                hint="Your own words. It sits in the turn order at the count below, and the party sees the marker without the text."
            />
            <div class="flex flex-wrap items-end gap-2">
                <div class="w-28">
                    <x-ui.input type="number" label="On initiative" name="lairInitiative" wire:model="lairInitiative" min="-99" max="999" />
                </div>
                <x-ui.button type="submit" size="sm" icon="check">Save</x-ui.button>
                <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeLair">Cancel</x-ui.button>
            </div>
        </form>
    @endif

    @if ($combatants->isEmpty())
        <p class="px-5 py-6 text-sm text-ink-faint">Nobody in the fight yet. Add the party, drop in a prepped monster, or type a name below.</p>
    @else
        <ul wire:sort="reorder" class="divide-y divide-line">
            @foreach ($combatants as $combatant)
                @if ($lairIndex === $loop->index)
                    @include('livewire.encounters.partials.lair-marker', ['encounter' => $encounter, 'showNote' => true])
                @endif

                <li
                    wire:key="combatant-{{ $combatant->id }}"
                    wire:sort:item="{{ $combatant->id }}"
                    class="px-5 py-3 {{ $combatant->id === $activeId ? 'border-l-2 border-ember bg-ember/5' : '' }} {{ $combatant->isDown() ? 'opacity-60' : '' }}"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" wire:sort:handle class="-ml-1.5 inline-flex size-8 shrink-0 cursor-grab items-center justify-center text-ink-faint hover:text-ink-muted" aria-label="Drag to reorder">
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
                            @if ($combatant->conditionList() !== [] || $combatant->isConcentrating() || $combatant->hasLegendaryActions())
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    @foreach ($combatant->conditionList() as $condition)
                                        <button type="button" wire:click="removeCondition('{{ $combatant->id }}', @js($condition))" aria-label="Remove {{ $condition }}">
                                            <x-ui.badge variant="danger">{{ $condition }} <x-ui.icon name="x" class="size-2.5" /></x-ui.badge>
                                        </button>
                                    @endforeach

                                    @if ($combatant->isConcentrating())
                                        <button type="button" wire:click="clearConcentration('{{ $combatant->id }}')" aria-label="{{ $combatant->name }} stops concentrating">
                                            <x-ui.badge variant="accent" icon="target">{{ $combatant->concentrating_on }} <x-ui.icon name="x" class="size-2.5" /></x-ui.badge>
                                        </button>
                                    @endif

                                    {{-- One click spends a use. They come back on this creature's own
                                         turn, which NextTurn does when the marker reaches it. --}}
                                    @if ($combatant->hasLegendaryActions())
                                        <button
                                            type="button"
                                            wire:click="spendLegendaryAction('{{ $combatant->id }}')"
                                            title="Spend a legendary action"
                                            aria-label="Spend a legendary action for {{ $combatant->name }}"
                                        >
                                            <x-ui.badge :variant="($combatant->legendary_actions_left ?? 0) > 0 ? 'dm' : 'neutral'" icon="zap">{{ $combatant->legendary_actions_left ?? 0 }}/{{ $combatant->legendary_actions_max }}</x-ui.badge>
                                        </button>
                                    @endif
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
                            {{-- The eye is what the party sees. Off by default for everything
                                 the GM adds, so a surprise stays a surprise. --}}
                            <x-ui.button
                                variant="ghost"
                                size="icon"
                                wire:click="toggleVisibility('{{ $combatant->id }}')"
                                :title="$combatant->isVisibleToPlayers() ? 'The party sees this row' : 'Hidden from the party'"
                                :aria-label="($combatant->isVisibleToPlayers() ? 'Hide ' : 'Show ').$combatant->name.' on the player table'"
                            >
                                <x-ui.icon
                                    :name="$combatant->isVisibleToPlayers() ? 'eye' : 'eye-off'"
                                    class="size-4 {{ $combatant->isVisibleToPlayers() ? 'text-ember' : '' }}"
                                />
                            </x-ui.button>
                            @if ($combatant->hp !== null)
                                <x-ui.button variant="ghost" size="icon" wire:click="openDamage('{{ $combatant->id }}')" aria-label="Damage or heal">
                                    <x-ui.icon name="heart" class="size-4" />
                                </x-ui.button>
                            @endif
                            <x-ui.button variant="ghost" size="icon" wire:click="openConditions('{{ $combatant->id }}')" aria-label="Conditions">
                                <x-ui.icon name="alert" class="size-4" />
                            </x-ui.button>
                            <x-ui.button variant="ghost" size="icon" wire:click="openRules('{{ $combatant->id }}')" title="Concentration and legendary actions" aria-label="Concentration and legendary actions">
                                <x-ui.icon name="target" class="size-4 {{ $combatant->isConcentrating() ? 'text-ember' : '' }}" />
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

                    {{-- Death saves. Only a row on nought has them, and the whole table
                         is watching them anyway, which is why the party's screen carries
                         them too. See Combatant::deathSavesVisibleToPlayers(). --}}
                    @if ($combatant->isDown())
                        <div class="mt-2 flex flex-wrap items-center gap-3 rounded-md border border-line bg-canvas px-3 py-2">
                            @if ($combatant->isDeadOnSaves())
                                <span class="text-sm font-medium text-danger">Dead</span>
                            @elseif ($combatant->isStable())
                                <span class="text-sm font-medium text-success">Stable</span>
                            @else
                                <span class="text-sm text-ink-muted">Death saves</span>
                            @endif

                            <div class="flex items-center gap-1">
                                <span class="text-xs text-ink-faint">saved</span>
                                @foreach (range(1, $deathSaves) as $pip)
                                    <button
                                        type="button"
                                        wire:click="deathSaveSuccess('{{ $combatant->id }}')"
                                        class="size-4 rounded-full border {{ $combatant->death_save_successes >= $pip ? 'border-success bg-success' : 'border-line-strong' }}"
                                        aria-label="Record a successful death save for {{ $combatant->name }}"
                                    ></button>
                                @endforeach
                            </div>

                            <div class="flex items-center gap-1">
                                <span class="text-xs text-ink-faint">failed</span>
                                @foreach (range(1, $deathSaves) as $pip)
                                    <button
                                        type="button"
                                        wire:click="deathSaveFailure('{{ $combatant->id }}')"
                                        class="size-4 rounded-full border {{ $combatant->death_save_failures >= $pip ? 'border-danger bg-danger' : 'border-line-strong' }}"
                                        aria-label="Record a failed death save for {{ $combatant->name }}"
                                    ></button>
                                @endforeach
                            </div>

                            @if ($combatant->hasDeathSaves())
                                <button type="button" wire:click="clearDeathSaves('{{ $combatant->id }}')" class="text-xs text-ink-faint hover:text-ember">Clear</button>
                            @endif
                        </div>
                    @endif

                    @if ($concentrationDcFor === $combatant->id && $concentrationDc !== null)
                        <div class="mt-2 flex flex-wrap items-center gap-3 rounded-md border border-ember bg-ember/5 px-3 py-2">
                            <span class="text-sm text-ink">{{ $combatant->name }} rolls a DC {{ $concentrationDc }} save or loses it.</span>
                            <button type="button" wire:click="dismissConcentrationSave" class="ml-auto text-xs text-ink-faint hover:text-ember">Dismiss</button>
                        </div>
                    @endif

                    @if ($editingRulesFor === $combatant->id)
                        <form wire:submit="saveRules('{{ $combatant->id }}')" class="mt-3 flex flex-wrap items-end gap-2 rounded-md border border-line bg-canvas p-3">
                            <div class="min-w-44 flex-1">
                                <x-ui.input
                                    label="Concentrating on"
                                    name="newConcentration"
                                    wire:model="newConcentration"
                                    placeholder="Hold Person"
                                    hint="Damage prompts the save. Dropping to nought ends it."
                                    autofocus
                                />
                            </div>
                            <div class="w-28">
                                <x-ui.input type="number" label="Legendary" name="newLegendaryMax" wire:model="newLegendaryMax" min="0" max="{{ \App\Models\Combatant::MAX_LEGENDARY_ACTIONS }}" />
                            </div>
                            <x-ui.button type="submit" size="sm" icon="check">Save</x-ui.button>
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeRules">Cancel</x-ui.button>
                        </form>
                    @endif

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

            @if ($lairIndex === $combatants->count())
                @include('livewire.encounters.partials.lair-marker', ['encounter' => $encounter, 'showNote' => true])
            @endif
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

        @if ($hasCompendium)
            <div class="rounded-md border border-line bg-canvas p-3">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-44 flex-1">
                        <x-ui.input
                            label="From the compendium"
                            name="compendiumSearch"
                            wire:model.live.debounce.300ms="compendiumSearch"
                            placeholder="goblin"
                            autocomplete="off"
                        />
                    </div>
                    <div class="w-20">
                        <x-ui.input type="number" label="How many" name="newQuantity" wire:model="newQuantity" min="1" max="20" />
                    </div>
                    <x-ui.checkbox label="Roll HP" name="rollHitPoints" wire:model="rollHitPoints" />
                </div>

                @if ($compendiumResults->isNotEmpty())
                    <ul class="mt-2 divide-y divide-line rounded-md border border-line">
                        @foreach ($compendiumResults as $statBlock)
                            <li wire:key="compendium-{{ $statBlock->id }}" class="flex flex-wrap items-center gap-2 px-3 py-2">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-ink">{{ $statBlock->name }}</span>
                                    <span class="block truncate text-xs text-ink-faint">
                                        CR {{ $statBlock->cr }} &middot; AC {{ $statBlock->ac }} &middot; HP {{ $statBlock->hp }}
                                    </span>
                                </span>
                                <a
                                    href="{{ route('compendium.show', [$campaign, $statBlock->slug]) }}"
                                    wire:navigate
                                    class="shrink-0 text-xs text-ink-faint hover:text-ember"
                                >Read</a>
                                <x-ui.button
                                    size="sm"
                                    icon="plus"
                                    wire:click="addFromCompendium('{{ $statBlock->id }}')"
                                >Add</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (trim($compendiumSearch) !== '')
                    <p class="mt-2 text-xs text-ink-faint">Nothing by that name.</p>
                @endif
            </div>
        @endif

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
