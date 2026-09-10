<div>
    <x-ui.page-header :title="$statBlock->name" :eyebrow="$statBlock->type_line">
        @if ($canEdit)
            <x-ui.button icon="edit" :href="route('compendium.edit', [$campaign, $statBlock->slug])" wire:navigate>Edit</x-ui.button>
        @endif
        @if ($canCopy)
            <x-ui.button variant="secondary" icon="copy" wire:click="copyToCampaign">
                {{ $isOwn ? 'Duplicate' : 'Copy to my campaign' }}
            </x-ui.button>
        @endif
        @if ($canEdit)
            <x-ui.button
                variant="ghost"
                icon="trash"
                wire:click="deleteStatBlock"
                wire:confirm="Delete {{ $statBlock->name }}? Any fight it is in keeps its numbers."
            >Delete</x-ui.button>
        @endif
        <x-ui.button
            variant="ghost"
            icon="arrow-left"
            :href="route('compendium.index', $campaign)"
            wire:navigate
        >Compendium</x-ui.button>
    </x-ui.page-header>

    @if ($isOwn)
        <p class="mb-4 text-sm text-ink-muted">
            <x-ui.badge variant="dm">Yours</x-ui.badge>
            Written for this campaign. It travels in your export, prose and all.
        </p>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                <div><dt class="eyebrow">AC</dt><dd class="text-ink">{{ $statBlock->ac }}</dd></div>
                <div><dt class="eyebrow">Initiative</dt><dd class="text-ink">{{ $statBlock->initiative_bonus >= 0 ? '+' : '' }}{{ $statBlock->initiative_bonus }}</dd></div>
                <div><dt class="eyebrow">HP</dt><dd class="text-ink">{{ $statBlock->hp }} @if ($statBlock->hit_dice)<span class="text-ink-faint">({{ $statBlock->hit_dice }})</span>@endif</dd></div>
                <div class="col-span-2 sm:col-span-3"><dt class="eyebrow">Speed</dt><dd class="text-ink">{{ $statBlock->speed }}</dd></div>
            </dl>

            @if ($statBlock->ability_scores)
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-96 text-sm">
                        <thead>
                            <tr class="text-left text-ink-faint">
                                <th class="py-1 font-medium"></th>
                                @foreach ($statBlock->ability_scores as $ability => $values)
                                    <th class="py-1 text-center font-medium uppercase">{{ $ability }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-line">
                                <td class="py-1 text-ink-faint">Score</td>
                                @foreach ($statBlock->ability_scores as $values)
                                    <td class="py-1 text-center text-ink">{{ $values['score'] }}</td>
                                @endforeach
                            </tr>
                            <tr class="border-t border-line">
                                <td class="py-1 text-ink-faint">Mod</td>
                                @foreach ($statBlock->ability_scores as $values)
                                    <td class="py-1 text-center font-mono text-ink">{{ $values['mod'] }}</td>
                                @endforeach
                            </tr>
                            <tr class="border-t border-line">
                                <td class="py-1 text-ink-faint">Save</td>
                                @foreach ($statBlock->ability_scores as $values)
                                    <td class="py-1 text-center font-mono text-ink">{{ $values['save'] }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <dl class="mt-4 space-y-1 border-t border-line pt-4 text-sm">
                @foreach (['Skills' => $statBlock->skills, 'Resistances' => $statBlock->resistances, 'Immunities' => $statBlock->immunities, 'Vulnerabilities' => $statBlock->vulnerabilities, 'Gear' => $statBlock->gear, 'Senses' => $statBlock->senses, 'Languages' => $statBlock->languages] as $label => $value)
                    @if ($value)
                        <div class="flex flex-wrap gap-2">
                            <dt class="font-medium text-ink-muted">{{ $label }}</dt>
                            <dd class="min-w-0 flex-1 text-ink">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
                <div class="flex flex-wrap gap-2">
                    <dt class="font-medium text-ink-muted">CR</dt>
                    <dd class="min-w-0 flex-1 text-ink">
                        {{ $statBlock->cr }}
                        <span class="text-ink-faint">(XP {{ number_format($statBlock->xp) }}@if ($statBlock->cr_note); {{ $statBlock->cr_note }}@endif)</span>
                    </dd>
                </div>
            </dl>

            @foreach ($sections as $heading => $entries)
                <div class="mt-5 border-t border-line pt-4">
                    <h2 class="mb-2 font-serif text-lg text-ink">{{ $heading }}</h2>
                    <div class="space-y-3">
                        @foreach ($entries as $entry)
                            <div>
                                @if ($entry['name'])
                                    <p class="font-medium text-ink">{{ $entry['name'] }}</p>
                                @endif
                                <div class="prose-entity text-sm">{!! $entry['html'] !!}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card>
                <h2 class="mb-3 font-serif text-lg text-ink">Into the fight</h2>

                @if ($encounters->isEmpty())
                    <p class="text-sm text-ink-faint">
                        No encounters yet. Start one and this becomes a button.
                    </p>
                    <x-ui.button class="mt-3" :href="route('encounters.index', $campaign)" wire:navigate icon="swords">
                        Encounters
                    </x-ui.button>
                @else
                    <form wire:submit="addToEncounter" class="space-y-3">
                        <x-ui.select label="Encounter" name="encounterId" wire:model="encounterId">
                            @foreach ($encounters as $encounter)
                                <option value="{{ $encounter->id }}">{{ $encounter->name }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input
                            label="How many"
                            name="quantity"
                            type="number"
                            min="1"
                            :max="\App\Actions\Encounters\AddCombatants::MAX_QUANTITY"
                            wire:model="quantity"
                        />

                        <div>
                            <x-ui.checkbox label="Roll hit points" name="rollHitPoints" wire:model="rollHitPoints" />
                            <p class="mt-1 text-xs text-ink-faint">
                                Each copy rolls {{ $statBlock->hit_dice }} rather than taking the average of {{ $statBlock->hp }}.
                            </p>
                        </div>

                        <x-ui.button type="submit" icon="plus" class="w-full">Add to the turn order</x-ui.button>
                    </form>

                    <p class="mt-3 text-xs text-ink-faint">
                        They arrive hidden from the party, like anything else you add.
                    </p>
                @endif
            </x-ui.card>

            {{-- Shipped prose only. A GM's own words are not SRD material, and printing
                 the notice under them would credit the wrong author. A copy of a shipped
                 creature keeps its source and licence, so it still shows one. --}}
            @if ($statBlock->license === config('compendium.license'))
                <x-compendium.attribution />
            @endif
        </div>
    </div>
</div>
