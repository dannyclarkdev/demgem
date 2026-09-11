{{--
    The decision log, drawn for whoever is looking. A GM gets the form and the
    controls; a player gets the rows the GM revealed. The query decided that, not
    this file.
--}}
<div class="space-y-4">
    @if ($canManage)
        <form wire:submit="record" class="space-y-3 rounded-lg border border-line bg-panel p-4">
            <x-ui.textarea label="{{ $scoped ? 'What did the party choose?' : 'Record a decision' }}" name="newChoice" wire:model="newChoice" rows="2" placeholder="Let the smuggler go with the ledger." />
            <x-ui.textarea label="What came of it" name="newConsequence" wire:model="newConsequence" rows="2" placeholder="Leave it blank until the world answers." hint="Optional now. Come back and fill it in when the consequence lands." />
            @unless ($scoped)
                <x-ui.select label="Made in" name="newSessionId" wire:model="newSessionId">
                    <option value="">Between sessions</option>
                    @foreach ($sessionOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->label() }}{{ $option->title ? ' · '.$option->title : '' }}</option>
                    @endforeach
                </x-ui.select>
            @endunless
            <div>
                <x-ui.button type="submit" icon="plus">Record it</x-ui.button>
            </div>
        </form>
    @endif

    @if ($decisions->isEmpty() && $scoped)
        <p class="text-sm text-ink-faint">{{ $canManage ? 'Nothing recorded for this session yet.' : 'Nothing recorded for this session.' }}</p>
    @elseif ($decisions->isEmpty())
        <x-ui.empty-state
            icon="flag"
            title="{{ $canManage ? 'No decisions recorded' : 'Nothing recorded yet' }}"
            :description="$canManage
                ? 'The choice the party made and what it cost them. Write the choice at the table, and the consequence when the world answers.'
                : 'When the GM records a choice the party made, it turns up here.'"
        />
    @else
        <ol class="space-y-3">
            @foreach ($decisions as $decision)
                <li wire:key="decision-{{ $decision->id }}" class="rounded-lg border border-line bg-panel p-4">
                    @if ($canManage && $editingId === $decision->id)
                        <form wire:submit="save" class="space-y-3">
                            <x-ui.textarea label="The choice" name="editingChoice" wire:model="editingChoice" rows="2" />
                            <x-ui.textarea label="What came of it" name="editingConsequence" wire:model="editingConsequence" rows="2" />
                            @unless ($scoped)
                                <x-ui.select label="Made in" name="editingSessionId" wire:model="editingSessionId">
                                    <option value="">Between sessions</option>
                                    @foreach ($sessionOptions as $option)
                                        <option value="{{ $option->id }}">{{ $option->label() }}{{ $option->title ? ' · '.$option->title : '' }}</option>
                                    @endforeach
                                </x-ui.select>
                            @endunless
                            <div class="flex items-center gap-2">
                                <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="cancelEdit">Cancel</x-ui.button>
                            </div>
                        </form>
                    @else
                        <div class="prose-entity">{!! $html[$decision->id]['choice'] !!}</div>

                        @if ($decision->hasConsequence())
                            <div class="mt-3 border-l-2 border-ember/50 pl-3">
                                <p class="eyebrow mb-1">What came of it</p>
                                <div class="prose-entity text-ink-muted">{!! $html[$decision->id]['consequence'] !!}</div>
                            </div>
                        @elseif ($canManage)
                            <p class="mt-3 text-xs text-ink-faint">No consequence yet.</p>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-ink-faint">
                            {{-- Absent when this viewer may not see the session, which is the
                                 whole gate: the query never loaded it. --}}
                            @php($link = $decision->game_session_id ? ($sessionLinks[$decision->game_session_id] ?? null) : null)
                            @if ($link && ! $scoped)
                                <a href="{{ $link->url() }}" class="inline-flex items-center gap-1 hover:text-ember">
                                    <x-ui.icon name="calendar" class="size-3 shrink-0" />
                                    <span>{{ $link->label() }}{{ $link->title ? ' · '.$link->title : '' }}</span>
                                </a>
                            @endif
                            <span>{{ $decision->created_at?->diffForHumans() }}</span>

                            @if ($canManage)
                                <div class="ml-auto flex items-center gap-1">
                                    <x-ui.button variant="ghost" size="icon" wire:click="edit('{{ $decision->id }}')" aria-label="Edit this decision">
                                        <x-ui.icon name="edit" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button
                                        variant="ghost"
                                        size="icon"
                                        wire:click="toggleVisibility('{{ $decision->id }}')"
                                        :title="$decision->player_visible ? 'The party sees this' : 'Hidden from the party'"
                                        :aria-label="$decision->player_visible ? 'Hide this decision from the party' : 'Show this decision to the party'"
                                    >
                                        <x-ui.icon :name="$decision->player_visible ? 'eye' : 'eye-off'" class="size-4 {{ $decision->player_visible ? 'text-ember' : '' }}" />
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="icon" wire:click="delete('{{ $decision->id }}')" wire:confirm="Delete this decision?" aria-label="Delete this decision">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </x-ui.button>
                                </div>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
