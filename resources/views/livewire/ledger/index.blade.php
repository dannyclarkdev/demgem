<div>
    <x-ui.page-header
        title="Ledger"
        :eyebrow="$campaign->name"
        description="The party's purse and pack. Anyone at the table writes to it; a wrong row is deleted and written again."
    />

    <div class="mb-6 grid gap-4 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
        <div class="rounded-lg border border-line bg-panel px-5 py-4">
            <p class="eyebrow">In the purse</p>
            <p class="mt-0.5 font-display text-3xl font-semibold {{ $balance < 0 ? 'text-danger' : 'text-ink' }}">
                {{ number_format($balance, 2) }} <span class="text-lg text-ink-muted">{{ $currency }}</span>
            </p>
        </div>

        <div class="rounded-lg border border-line bg-panel px-5 py-4">
            <p class="eyebrow mb-2">In the pack</p>
            @if ($inventory === [])
                <p class="text-sm text-ink-faint">Nothing carried yet.</p>
            @else
                <ul class="flex flex-wrap gap-2">
                    @foreach ($inventory as $line)
                        @php($page = $line['entity_id'] ? ($itemLinks[$line['entity_id']] ?? null) : null)
                        <li>
                            @if ($page)
                                <a href="{{ $page->url() }}"><x-ui.badge icon="box">{{ $line['quantity'] }} × {{ $line['name'] }}</x-ui.badge></a>
                            @else
                                <x-ui.badge icon="box">{{ $line['quantity'] }} × {{ $line['name'] }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0">
            @if ($entries->isEmpty())
                <x-ui.empty-state
                    icon="box"
                    title="An empty ledger"
                    description="Record the starting purse, the first find, or the first bribe. Every member can."
                />
            @else
                <ol class="divide-y divide-line rounded-lg border border-line bg-panel">
                    @foreach ($entries as $entry)
                        <li wire:key="entry-{{ $entry->id }}" class="flex items-start gap-3 px-4 py-3">
                            <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-raised text-ink-muted">
                                <x-ui.icon :name="$entry->isCoin() ? 'zap' : 'box'" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">
                                    @if ($entry->isCoin())
                                        <span class="font-mono {{ (float) $entry->amount < 0 ? 'text-danger' : 'text-success' }}">{{ $entry->signedAmount() }}</span> {{ $currency }}
                                    @else
                                        @php($page = $entry->entity_id ? ($itemLinks[$entry->entity_id] ?? null) : null)
                                        <span class="font-mono {{ ($entry->quantity ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ ($entry->quantity ?? 0) < 0 ? '−' : '+' }}{{ abs($entry->quantity ?? 0) }}</span>
                                        @if ($page)
                                            <a href="{{ $page->url() }}" class="hover:text-ember">{{ $entry->item_name }}</a>
                                        @else
                                            {{ $entry->item_name }}
                                        @endif
                                    @endif
                                </p>
                                @if (filled($entry->note))
                                    <p class="text-sm text-ink-muted">{{ $entry->note }}</p>
                                @endif
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint">
                                    @if ($entry->author)
                                        <span>{{ $entry->author->name }}</span>
                                    @endif
                                    @php($link = $entry->game_session_id ? ($sessionLinks[$entry->game_session_id] ?? null) : null)
                                    @if ($link)
                                        <a href="{{ $link->url() }}" class="inline-flex items-center gap-1 hover:text-ember">
                                            <x-ui.icon name="calendar" class="size-3 shrink-0" />
                                            <span>{{ $link->label() }}</span>
                                        </a>
                                    @endif
                                    <span>{{ $entry->created_at?->diffForHumans() }}</span>
                                </p>
                            </div>
                            @can('delete', $entry)
                                <x-ui.button variant="ghost" size="icon" wire:click="delete('{{ $entry->id }}')" wire:confirm="Delete this row?" aria-label="Delete this row">
                                    <x-ui.icon name="trash" class="size-4" />
                                </x-ui.button>
                            @endcan
                        </li>
                    @endforeach
                </ol>

                <div class="mt-4">{{ $entries->links() }}</div>
            @endif
        </div>

        <aside class="min-w-0">
            @can('create', [\App\Models\LedgerEntry::class, $campaign])
                <form wire:submit="record" class="space-y-3 rounded-lg border border-line bg-panel p-4">
                    <p class="eyebrow">Record a movement</p>

                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.select label="What" name="kind" wire:model.live="kind">
                            @foreach ($kinds as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select label="Which way" name="direction" wire:model="direction">
                            <option value="find">Found or earned</option>
                            <option value="spend">Spent or lost</option>
                        </x-ui.select>
                    </div>

                    @if ($kind === \App\Enums\LedgerKind::Coin->value)
                        <x-ui.input :label="'Amount in '.$currency" name="amount" type="number" step="0.01" min="0.01" wire:model="amount" placeholder="40" />
                    @else
                        <x-ui.input label="Item" name="itemName" wire:model="itemName" placeholder="Tidewarden Signet" />
                        <div class="grid grid-cols-[6rem_1fr] gap-3">
                            <x-ui.input label="How many" name="quantity" type="number" min="1" wire:model="quantity" />
                            <x-ui.select label="Its page" name="entityId" wire:model="entityId" hint="Optional.">
                                <option value="">No page</option>
                                @foreach ($itemOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif

                    <x-ui.input label="Note" name="note" wire:model="note" placeholder="From the drowned chest" />

                    <x-ui.select label="During" name="sessionId" wire:model="sessionId">
                        <option value="">Between sessions</option>
                        @foreach ($sessionOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->label() }}{{ $option->title ? ' · '.$option->title : '' }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.button type="submit" icon="plus">Record it</x-ui.button>
                </form>
            @endcan
        </aside>
    </div>
</div>
