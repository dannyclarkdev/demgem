{{--
    The SRD 5.2.1 sheet. Every number on it that is not a score was computed in the
    model on this render; nothing here does arithmetic. The form appears for whoever
    may edit the character, and the policy decided that, not this file.
--}}
<div class="space-y-4">
    @if ($editing)
        <form wire:submit="save" class="space-y-5">
            <div>
                <p class="eyebrow mb-2">Ability scores</p>
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    @foreach ($abilities as $ability => $name)
                        <x-ui.input :label="$name" name="scores.{{ $ability }}" type="number" min="1" max="30" wire:model="scores.{{ $ability }}" />
                    @endforeach
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <p class="eyebrow mb-2">Saving throws</p>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach ($abilities as $ability => $name)
                            <x-ui.checkbox :label="$name" name="savingThrows-{{ $ability }}" wire:model="savingThrows" value="{{ $ability }}" />
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="eyebrow mb-2">Hit points</p>
                    <div class="grid grid-cols-3 gap-2">
                        <x-ui.input label="Max" name="hpMax" type="number" min="0" wire:model="hpMax" />
                        <x-ui.input label="Current" name="hpCurrent" type="number" min="0" wire:model="hpCurrent" />
                        <x-ui.input label="Temporary" name="hpTemp" type="number" min="0" wire:model="hpTemp" />
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <x-ui.select label="Hit die" name="hitDie" wire:model="hitDie">
                            @foreach ($hitDice as $die)
                                <option value="{{ $die }}">d{{ $die }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input label="Dice spent" name="hitDiceSpent" type="number" min="0" wire:model="hitDiceSpent" />
                        <x-ui.input label="Armour class" name="armorClass" type="number" min="0" wire:model="armorClass" />
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <x-ui.input label="Speed" name="speed" type="number" min="0" wire:model="speed" />
                        <x-ui.select label="Spellcasting ability" name="spellcastingAbility" wire:model="spellcastingAbility">
                            <option value="">None</option>
                            @foreach ($abilities as $ability => $name)
                                <option value="{{ $ability }}">{{ $name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                </div>
            </div>

            <div>
                <p class="eyebrow mb-2">Skills</p>
                <p class="mb-2 text-xs text-ink-faint">Tick a skill for proficiency, and the second box for expertise.</p>
                <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($skillList as $key => $skill)
                        <div class="flex items-center gap-3">
                            <x-ui.checkbox :label="$skill['name']" name="skills-{{ $key }}" wire:model="skills" value="{{ $key }}" />
                            <x-ui.checkbox label="Expertise" name="expertise-{{ $key }}" wire:model="expertise" value="{{ $key }}" class="text-xs" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="eyebrow mb-2">Spell slots</p>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9">
                    @foreach ($slotLevels as $level)
                        <div class="rounded-md border border-line p-2">
                            <p class="mb-1 text-center font-mono text-xs text-ink-faint">Level {{ $level }}</p>
                            <x-ui.input label="Total" name="spellSlots.{{ $level }}.total" type="number" min="0" max="9" wire:model="spellSlots.{{ $level }}.total" />
                            <x-ui.input label="Used" name="spellSlots.{{ $level }}.used" type="number" min="0" max="9" wire:model="spellSlots.{{ $level }}.used" class="mt-1" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" icon="check">Save sheet</x-ui.button>
                <x-ui.button type="button" variant="ghost" wire:click="cancelEdit">Cancel</x-ui.button>
            </div>
        </form>
    @elseif ($sheet === null)
        <div class="flex flex-wrap items-center gap-3">
            <p class="min-w-0 flex-1 text-sm text-ink-muted">No sheet yet. {{ $canWrite ? 'Write the scores and the rest follows.' : 'The player has not written one.' }}</p>
            @if ($canWrite)
                <x-ui.button size="sm" icon="edit" wire:click="edit">Edit sheet</x-ui.button>
            @endif
        </div>
    @else
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <span class="text-ink-muted">Proficiency bonus <span class="font-mono font-semibold text-ink">{{ \App\Support\Sheets\FifthEdition::signed($sheet->proficiencyBonus()) }}</span></span>
            <span class="text-ink-muted">Initiative <span class="font-mono font-semibold text-ink">{{ \App\Support\Sheets\FifthEdition::signed($sheet->initiative()) }}</span></span>
            @if ($sheet->armor_class !== null)
                <span class="text-ink-muted">Armour class <span class="font-mono font-semibold text-ink">{{ $sheet->armor_class }}</span></span>
            @endif
            @if ($sheet->speed !== null)
                <span class="text-ink-muted">Speed <span class="font-mono font-semibold text-ink">{{ $sheet->speed }}</span></span>
            @endif
            <span class="text-ink-muted">Passive Perception <span class="font-mono font-semibold text-ink">{{ $sheet->passivePerception() }}</span></span>
            @if ($canWrite)
                <span class="ml-auto flex items-center gap-1">
                    <x-ui.button size="sm" variant="secondary" icon="edit" wire:click="edit">Edit sheet</x-ui.button>
                    <x-ui.button size="sm" variant="ghost" icon="moon" wire:click="longRest" wire:confirm="Take a long rest? Hit points and slots come back, and half the hit dice.">Long rest</x-ui.button>
                </span>
            @endif
        </div>

        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach ($abilities as $ability => $name)
                <div class="rounded-lg border border-line bg-raised px-2 py-3 text-center">
                    <p class="text-xs font-medium tracking-wide text-ink-faint uppercase">{{ $name }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ $sheet->score($ability) }}</p>
                    <p class="font-mono text-sm text-ink-muted">{{ \App\Support\Sheets\FifthEdition::signed($sheet->modifier($ability)) }}</p>
                    <p class="mt-1 text-xs text-ink-faint">Save <span class="font-mono {{ $sheet->hasSaveProficiency($ability) ? 'font-semibold text-ember' : 'text-ink-muted' }}">{{ \App\Support\Sheets\FifthEdition::signed($sheet->saveBonus($ability)) }}</span></p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_16rem]">
            <ul class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($skillList as $key => $skill)
                    <li class="flex items-center gap-2">
                        <span class="inline-flex size-4 shrink-0 items-center justify-center rounded-full border {{ $sheet->hasSkillProficiency($key) ? ($sheet->hasExpertise($key) ? 'border-ember bg-ember' : 'border-ember bg-ember/40') : 'border-line-strong' }}" title="{{ $sheet->hasExpertise($key) ? 'Expertise' : ($sheet->hasSkillProficiency($key) ? 'Proficient' : 'Not proficient') }}"></span>
                        <span class="min-w-0 flex-1 truncate text-ink">{{ $skill['name'] }} <span class="text-ink-faint">({{ strtoupper($skill['ability']) }})</span></span>
                        <span class="font-mono tabular-nums text-ink">{{ \App\Support\Sheets\FifthEdition::signed($sheet->skillBonus($key)) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="space-y-3">
                <div class="rounded-lg border border-line bg-raised p-3">
                    <p class="eyebrow">Hit points</p>
                    <p class="mt-1 font-display text-3xl font-semibold {{ $sheet->hp_current === 0 ? 'text-danger' : 'text-ink' }}">
                        {{ $sheet->hp_current }} <span class="text-lg text-ink-faint">/ {{ $sheet->hp_max }}</span>
                    </p>
                    @if ($sheet->hp_temp > 0)
                        <p class="text-sm text-ink-muted">+{{ $sheet->hp_temp }} temporary</p>
                    @endif
                    <p class="mt-2 text-sm text-ink-muted">Hit dice <span class="font-mono text-ink">{{ $sheet->hitDiceLeft() }}</span> of {{ $sheet->hitDiceLabel() }}</p>

                    @if ($canWrite)
                        <div class="mt-3 space-y-2">
                            <form wire:submit="damage" class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <x-ui.input label="Damage" name="damageAmount" type="number" min="1" wire:model="damageAmount" placeholder="12" />
                                </div>
                                <x-ui.button type="submit" size="sm" variant="danger" class="mb-0.5">Take</x-ui.button>
                            </form>
                            <form wire:submit="heal" class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <x-ui.input label="Healing" name="healAmount" type="number" min="1" wire:model="healAmount" placeholder="8" />
                                </div>
                                <x-ui.button type="submit" size="sm" variant="secondary" class="mb-0.5">Heal</x-ui.button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($sheet->hasSlots())
                    <div class="rounded-lg border border-line bg-raised p-3">
                        <p class="eyebrow">Spell slots</p>
                        @if ($sheet->spellcasting_ability)
                            <p class="mt-1 text-xs text-ink-muted">{{ $abilities[$sheet->spellcasting_ability] }} · save DC {{ 8 + $sheet->proficiencyBonus() + $sheet->modifier($sheet->spellcasting_ability) }} · attack {{ \App\Support\Sheets\FifthEdition::signed($sheet->proficiencyBonus() + $sheet->modifier($sheet->spellcasting_ability)) }}</p>
                        @endif
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($sheet->slots() as $level => $slot)
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="w-12 shrink-0 font-mono text-ink-faint">L{{ $level }}</span>
                                    <span class="flex flex-wrap items-center gap-1" aria-label="{{ $slot['total'] - $slot['used'] }} of {{ $slot['total'] }} slots left">
                                        @foreach (range(1, $slot['total']) as $pip)
                                            <span class="size-3 rounded-full border {{ $pip <= $slot['total'] - $slot['used'] ? 'border-ember bg-ember' : 'border-line-strong' }}"></span>
                                        @endforeach
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="border-t border-line pt-3">
        <p class="eyebrow">The party's pack</p>
        @if ($pack === [])
            <p class="mt-1 text-sm text-ink-faint">Nothing in the pack. The ledger is where it goes in.</p>
        @else
            <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @foreach ($pack as $line)
                    <li wire:key="pack-{{ $line['key'] }}" class="text-ink-muted"><span class="font-mono text-ink">{{ $line['quantity'] }} ×</span> {{ $line['name'] }}</li>
                @endforeach
            </ul>
        @endif
        <p class="mt-2 text-xs text-ink-faint"><a href="{{ route('ledger.index', $campaign) }}" class="hover:text-ember">The ledger</a> is the party's; every member writes to it.</p>
    </div>

    <p class="text-xs text-ink-faint">{{ $attribution }}</p>
</div>
