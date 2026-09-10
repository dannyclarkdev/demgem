{{--
    One form for a creature the GM writes.

    Every field but the name is optional. A creature with a name and two traits is a
    complete thing here: the tracker adds it by name with nothing to copy, exactly as it
    adds a row a GM types straight into the turn order.
--}}
<div>
    <x-ui.page-header
        :title="$statBlock === null ? 'New creature' : 'Edit '.$statBlock->name"
        :eyebrow="$campaign->name"
        description="Yours, in your campaign's compendium. Every field but the name is optional."
    >
        <x-ui.button
            variant="ghost"
            icon="arrow-left"
            :href="$statBlock === null ? route('compendium.index', $campaign) : route('compendium.show', [$campaign, $statBlock->slug])"
            wire:navigate
        >Cancel</x-ui.button>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">What it is</h2>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input label="Name" name="name" wire:model="name" placeholder="Harbour thug" autofocus />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input
                        label="Type line"
                        name="typeLine"
                        wire:model="typeLine"
                        placeholder="Medium Humanoid, Neutral"
                        hint="The line printed under the name. The three fields below are the same line, read for the filters."
                    />
                </div>
                <x-ui.input label="Size" name="size" wire:model="size" placeholder="Medium" />
                <x-ui.input label="Creature type" name="creatureType" wire:model="creatureType" placeholder="Humanoid" />
                <x-ui.input label="Subtype" name="subtype" wire:model="subtype" placeholder="Goblinoid" />
                <x-ui.input label="Alignment" name="alignment" wire:model="alignment" placeholder="Neutral Evil" />
                <div class="sm:col-span-2">
                    <x-ui.checkbox label="A swarm" name="isSwarm" wire:model="isSwarm" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">The numbers the tracker copies</h2>
            <p class="mt-1 text-sm text-ink-muted">
                These are what a combatant takes when you put this creature in a fight. Leave one blank and the row arrives without it.
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <x-ui.input type="number" label="Armour class" name="ac" wire:model="ac" min="0" max="99" />
                <x-ui.input type="number" label="Hit points" name="hp" wire:model="hp" min="0" />
                <x-ui.input type="number" label="Initiative bonus" name="initiativeBonus" wire:model="initiativeBonus" min="-20" max="20" />
                <x-ui.input
                    label="Hit dice"
                    name="hitDice"
                    wire:model="hitDice"
                    placeholder="4d8 + 4"
                    hint="Roll HP in the tracker uses this."
                />
                <div class="sm:col-span-2">
                    <x-ui.input label="Speed" name="speed" wire:model="speed" placeholder="30 ft., swim 30 ft." />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">Ability scores</h2>
            <p class="mt-1 text-sm text-ink-muted">
                The modifier follows the score, so there is nothing to keep in step. A save is only worth filling when it is not the modifier.
            </p>

            {{-- Two across at a tablet width, three at a laptop. At three the card is
                 about 130px wide and a number input's spinner takes the whole field,
                 so the score reads as blank. --}}
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($abilityKeys as $ability)
                    <div class="rounded-md border border-line p-3">
                        <p class="eyebrow">{{ strtoupper($ability) }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 [&>*]:min-w-20 [&>*]:flex-1">
                            <x-ui.input
                                type="number"
                                label="Score"
                                :name="'abilities.'.$ability.'.score'"
                                wire:model="abilities.{{ $ability }}.score"
                                min="0"
                                max="99"
                            />
                            <x-ui.input
                                label="Save"
                                :name="'abilities.'.$ability.'.save'"
                                wire:model="abilities.{{ $ability }}.save"
                                placeholder="+4"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">Everything else it prints</h2>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <x-ui.input label="Skills" name="skills" wire:model="skills" placeholder="Perception +2, Stealth +4" />
                <x-ui.input label="Senses" name="senses" wire:model="senses" placeholder="Darkvision 60 ft., Passive Perception 12" />
                <x-ui.input label="Languages" name="languages" wire:model="languages" placeholder="Common, Goblin" />
                <x-ui.input label="Gear" name="gear" wire:model="gear" placeholder="Scimitar, Shortbow" />
                <x-ui.input label="Resistances" name="resistances" wire:model="resistances" />
                <x-ui.input label="Immunities" name="immunities" wire:model="immunities" />
                <x-ui.input label="Vulnerabilities" name="vulnerabilities" wire:model="vulnerabilities" />
                <x-ui.input
                    type="number"
                    label="Legendary action uses"
                    name="legendaryActionUses"
                    wire:model="legendaryActionUses"
                    min="0"
                    :max="\App\Models\Combatant::MAX_LEGENDARY_ACTIONS"
                    hint="A creature that gets them arrives in the fight with this many."
                />
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">What it is worth</h2>
            <p class="mt-1 text-sm text-ink-muted">
                XP is what the encounter budget reads. It is suggested from the rating and then yours to change.
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <x-ui.input
                    label="Challenge rating"
                    name="cr"
                    wire:model.blur="cr"
                    placeholder="1/4"
                    hint="A fraction or a whole number. The list sorts by it."
                />
                <x-ui.input type="number" label="XP" name="xp" wire:model="xp" min="0" />
                <x-ui.input label="Note" name="crNote" wire:model="crNote" placeholder="PB +2" />
            </div>
        </x-ui.card>

        @foreach ($sectionHeadings as $section => $heading)
            <x-ui.card wire:key="section-{{ $section }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $heading }}</h2>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="sm"
                        icon="plus"
                        wire:click="addSectionEntry('{{ $section }}')"
                    >Add</x-ui.button>
                </div>

                @if ($sections[$section] === [])
                    <p class="mt-2 text-sm text-ink-faint">Nothing here yet.</p>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach ($sections[$section] as $index => $entry)
                            <div wire:key="entry-{{ $section }}-{{ $index }}" class="rounded-md border border-line p-3">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="min-w-44 flex-1">
                                        <x-ui.input
                                            label="Name"
                                            :name="'sections.'.$section.'.'.$index.'.name'"
                                            wire:model="sections.{{ $section }}.{{ $index }}.name"
                                            placeholder="Scimitar"
                                        />
                                    </div>
                                    <x-ui.button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        wire:click="removeSectionEntry('{{ $section }}', {{ $index }})"
                                        aria-label="Remove this entry"
                                    >
                                        <x-ui.icon name="trash" class="size-4" />
                                    </x-ui.button>
                                </div>
                                <div class="mt-2">
                                    <x-ui.textarea
                                        label="Text"
                                        :name="'sections.'.$section.'.'.$index.'.text'"
                                        wire:model="sections.{{ $section }}.{{ $index }}.text"
                                        rows="3"
                                        hint="Markdown. Yours to write however the table reads best."
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @endforeach

        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit" icon="check">Save</x-ui.button>
            <x-ui.button
                type="button"
                variant="ghost"
                :href="$statBlock === null ? route('compendium.index', $campaign) : route('compendium.show', [$campaign, $statBlock->slug])"
                wire:navigate
            >Cancel</x-ui.button>
        </div>
    </form>
</div>
