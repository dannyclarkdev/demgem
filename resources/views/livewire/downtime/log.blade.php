{{--
    The downtime log, drawn for whoever is looking. The rows are the ones the query
    loaded under this viewer's own role; the form appears when the policy says this
    member may write on the character in question. Nothing in here decides either.
--}}
<div class="space-y-4">
    @if ($total !== null)
        <p class="text-sm text-ink-muted">
            <span class="font-display text-lg font-semibold text-ink">{{ \App\Models\DowntimeActivity::dayCount($total) }}</span>
            of downtime{{ $activities->isEmpty() ? '.' : ', over '.$activities->count().' '.Str::plural('entry', $activities->count()).'.' }}
        </p>
    @endif

    @if ($canWrite)
        <form wire:submit="record" class="space-y-3 rounded-lg border border-line bg-panel p-4">
            @unless ($scopedToCharacter)
                <x-ui.select label="Who" name="newCharacterId" wire:model="newCharacterId">
                    <option value="">Pick a character</option>
                    @foreach ($characterOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </x-ui.select>
            @endunless
            <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                <x-ui.input label="What they did" name="newActivity" wire:model="newActivity" placeholder="Trained with the Tidewardens" />
                <x-ui.input label="Days" name="newDays" type="number" min="0" max="{{ \App\Models\DowntimeActivity::MAX_DAYS }}" wire:model="newDays" />
            </div>
            @if ($hasCalendar)
                <x-ui.game-date name="newStartsOn" :months="$months" label="Starting on" hint="Optional. The day it began, in the world's calendar." />
            @endif
            <x-ui.textarea label="Notes" name="newNotes" wire:model="newNotes" rows="2" placeholder="Who they met, what it cost, what they learned." />
            @unless ($scopedToSession)
                <x-ui.select label="Around" name="newSessionId" wire:model="newSessionId">
                    <option value="">No session in particular</option>
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

    @if ($activities->isEmpty() && ($scopedToCharacter || $scopedToSession))
        <p class="text-sm text-ink-faint">{{ $scopedToSession ? 'Nothing recorded around this session.' : 'No downtime recorded yet.' }}</p>
    @elseif ($activities->isEmpty())
        <x-ui.empty-state
            icon="compass"
            title="No downtime recorded"
            :description="$canWrite
                ? 'What a character did between sessions, and how many days it took. Write it while it is fresh.'
                : 'When the party writes what they did between sessions, it turns up here.'"
        />
    @else
        <ol class="space-y-3">
            @foreach ($activities as $activity)
                <li wire:key="downtime-{{ $activity->id }}" class="rounded-lg border border-line bg-panel p-4">
                    @if ($editingId === $activity->id)
                        <form wire:submit="save" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-[1fr_7rem]">
                                <x-ui.input label="What they did" name="editingActivity" wire:model="editingActivity" />
                                <x-ui.input label="Days" name="editingDays" type="number" min="0" max="{{ \App\Models\DowntimeActivity::MAX_DAYS }}" wire:model="editingDays" />
                            </div>
                            @if ($hasCalendar)
                                <x-ui.game-date name="editingStartsOn" :months="$months" label="Starting on" />
                            @endif
                            <x-ui.textarea label="Notes" name="editingNotes" wire:model="editingNotes" rows="2" />
                            @unless ($scopedToSession)
                                <x-ui.select label="Around" name="editingSessionId" wire:model="editingSessionId">
                                    <option value="">No session in particular</option>
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
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="min-w-0 flex-1 font-medium text-ink">{{ $activity->activity }}</p>
                            <span class="shrink-0 font-mono text-sm tabular-nums text-ink-muted">{{ \App\Models\DowntimeActivity::dayCount($activity->days) }}</span>
                        </div>

                        @if ($ranges[$activity->id])
                            <p class="mt-1 text-sm text-ink-muted">{{ $ranges[$activity->id] }}</p>
                        @endif

                        @if ($html[$activity->id] !== '')
                            <div class="prose-entity mt-2 text-sm text-ink-muted">{!! $html[$activity->id] !!}</div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-ink-faint">
                            @unless ($scopedToCharacter)
                                <a href="{{ $activity->character->url() }}" class="inline-flex items-center gap-1 hover:text-ember">
                                    <x-ui.icon name="user" class="size-3 shrink-0" />
                                    <span>{{ $activity->character->name }}</span>
                                </a>
                            @endunless
                            {{-- Absent when this viewer may not see the session, which is the
                                 whole gate: the query never loaded it. --}}
                            @php($link = $activity->game_session_id ? ($sessionLinks[$activity->game_session_id] ?? null) : null)
                            @if ($link && ! $scopedToSession)
                                <a href="{{ $link->url() }}" class="inline-flex items-center gap-1 hover:text-ember">
                                    <x-ui.icon name="calendar" class="size-3 shrink-0" />
                                    <span>{{ $link->label() }}{{ $link->title ? ' · '.$link->title : '' }}</span>
                                </a>
                            @endif
                            <span>{{ $activity->created_at?->diffForHumans() }}</span>

                            @if (in_array($activity->id, $editable, true))
                                <div class="ml-auto flex items-center gap-1">
                                    <x-ui.button variant="ghost" size="icon" wire:click="edit('{{ $activity->id }}')" aria-label="Edit this downtime">
                                        <x-ui.icon name="edit" class="size-4" />
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" size="icon" wire:click="delete('{{ $activity->id }}')" wire:confirm="Delete this downtime?" aria-label="Delete this downtime">
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
