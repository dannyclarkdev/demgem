{{--
    The wall. One thing at a time, large, and no controls on it.

    Everything here was decided on the server under the party's gate, whoever opened
    the page: a hidden combatant is not in $combatants, a hidden pin is not in
    $markers, and a handout the GM took back is not $page at all. There is no @if in
    here that hides anything, because nothing here needs hiding.

    The poll is the sixty-second backstop the table keeps, for the same reasons: a
    socket drops and a laptop sleeps, and a wall that stopped is worse than one that
    is a minute behind.
--}}
<div wire:poll.visible.{{ $pollSeconds }}s class="flex min-h-dvh flex-col">
    <header class="flex flex-wrap items-center justify-between gap-3 px-8 py-5">
        <p class="flex items-center gap-2.5 font-display text-2xl font-semibold tracking-tight text-ink">
            <x-ui.logo class="size-6 text-ember" />
            {{ $campaign->name }}
        </p>
        @if ($focus)
            <p class="eyebrow">{{ $focus->label() }}</p>
        @endif
    </header>

    <main class="flex flex-1 flex-col px-8 pb-6">
        @if ($focus === \App\Enums\ScreenFocus::Fight && $fight)
            @php
                $activeId = $fight->active_combatant_id;
                $activeName = $combatants->firstWhere('id', $activeId)?->name;
                $hasHiddenTurn = $activeId !== null && $activeName === null;
                $lairIndex = $fight->lairMarkerIndex($combatants);
            @endphp
            <div class="mb-4 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                @if ($fight->round > 0)
                    <span class="font-display text-4xl font-semibold text-ink">Round {{ $fight->round }}</span>
                @endif
                @if ($activeName)
                    <span class="text-2xl text-ink-muted"><span class="font-medium text-ink">{{ $activeName }}</span> is up</span>
                @elseif ($hasHiddenTurn)
                    <span class="text-2xl text-ink-muted">Something you cannot see is taking its turn</span>
                @endif
            </div>

            @if ($combatants->isEmpty())
                <p class="text-2xl text-ink-faint">The GM has not put anything on the table yet.</p>
            @else
                <ol class="divide-y divide-line rounded-xl border border-line bg-panel">
                    @foreach ($combatants as $combatant)
                        @if ($lairIndex === $loop->index)
                            @include('livewire.table.partials.screen-lair-marker', ['encounter' => $fight])
                        @endif

                        @php
                            $word = $combatant->healthWord();
                            $wordVariant = match ($word) {
                                'Unhurt' => 'success',
                                'Badly hurt', 'Down' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <li
                            wire:key="screen-row-{{ $combatant->id }}"
                            class="flex flex-wrap items-center gap-x-5 gap-y-2 px-6 py-4 {{ $combatant->id === $activeId ? 'border-l-4 border-ember bg-ember/5' : '' }} {{ $combatant->isDown() ? 'opacity-60' : '' }}"
                        >
                            <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-lg border border-line-strong bg-canvas font-mono text-xl font-semibold tabular-nums text-ink-muted">
                                {{ $loop->iteration }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-3xl font-medium text-ink">{{ $combatant->name }}</p>
                                @if ($combatant->conditionList() !== [])
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($combatant->conditionList() as $condition)
                                            <x-ui.badge variant="danger" class="text-base">{{ $condition }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Death saves, in the open, by the table's rule. The row
                                 still had to pass the gate to be here at all. --}}
                            @if ($combatant->deathSavesVisibleToPlayers())
                                <span class="flex shrink-0 items-center gap-3" aria-label="Death saves: {{ $combatant->death_save_successes }} saved, {{ $combatant->death_save_failures }} failed">
                                    <span class="flex items-center gap-1.5">
                                        @foreach (range(1, $deathSaves) as $pip)
                                            <span class="size-4 rounded-full border {{ $combatant->death_save_successes >= $pip ? 'border-success bg-success' : 'border-line-strong' }}"></span>
                                        @endforeach
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        @foreach (range(1, $deathSaves) as $pip)
                                            <span class="size-4 rounded-full border {{ $combatant->death_save_failures >= $pip ? 'border-danger bg-danger' : 'border-line-strong' }}"></span>
                                        @endforeach
                                    </span>
                                </span>
                            @endif

                            @if ($word)
                                <x-ui.badge :variant="$wordVariant" class="shrink-0 px-3 py-1 text-lg">{{ $word }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach

                    @if ($lairIndex === $combatants->count())
                        @include('livewire.table.partials.screen-lair-marker', ['encounter' => $fight])
                    @endif
                </ol>
            @endif

        @elseif ($focus === \App\Enums\ScreenFocus::Handout && $page)
            <figure class="flex flex-1 flex-col items-center justify-center gap-4">
                @if ($file && $file->hasGeneratedConversion('tile'))
                    <img src="{{ $file->getUrl() }}" alt="{{ $page->name }}" class="max-h-[62vh] max-w-full rounded-lg object-contain shadow-2xl shadow-black/40">
                @elseif ($file)
                    <span class="inline-flex size-32 items-center justify-center rounded-2xl border border-line bg-panel text-ink-faint">
                        <x-ui.icon name="file-text" class="size-16" />
                    </span>
                    <p class="text-xl text-ink-muted">{{ $file->file_name }}</p>
                @else
                    <span class="inline-flex size-32 items-center justify-center rounded-2xl border border-line bg-panel text-ink-faint">
                        <x-ui.icon name="paperclip" class="size-16" />
                    </span>
                @endif
                <figcaption class="font-display text-3xl font-semibold text-ink">{{ $page->name }}</figcaption>
            </figure>

        @elseif ($focus === \App\Enums\ScreenFocus::Map && $page)
            <div x-data="mapViewer({ canEdit: false })" class="flex flex-1 flex-col overflow-hidden rounded-xl border border-line bg-panel">
                <div
                    x-ref="frame"
                    x-bind:style="{ aspectRatio: ratio }"
                    class="relative max-h-[68vh] w-full touch-none overflow-hidden bg-canvas select-none"
                    x-on:wheel="onWheel($event)"
                    x-on:pointerdown="onPointerDown($event)"
                    x-on:pointermove="onPointerMove($event)"
                    x-on:pointerup="onPointerUp($event)"
                    x-on:pointercancel="onPointerUp($event)"
                >
                    <div class="absolute inset-0 origin-top-left" x-bind:style="{ transform }">
                        <img
                            x-ref="image"
                            src="{{ $page->imageUrl() }}"
                            alt="{{ $page->name }}"
                            class="pointer-events-none h-full w-full object-contain"
                            draggable="false"
                            loading="eager"
                            x-on:load="onImageLoad()"
                        >

                        @foreach ($markers as $marker)
                            @php
                                [$shift, $origin, $align] = match (true) {
                                    $marker->x < 18 => ['-20px -100%', 'origin-bottom-left', 'left'],
                                    $marker->x > 82 => ['calc(-100% + 20px) -100%', 'origin-bottom-right', 'right'],
                                    default => ['-50% -100%', 'origin-bottom', 'center'],
                                };
                            @endphp
                            <span
                                wire:key="screen-pin-{{ $marker->id }}"
                                class="absolute z-10 {{ $origin }}"
                                style="left: {{ $marker->x }}%; top: {{ $marker->y }}%; translate: {{ $shift }};"
                                x-bind:style="{ transform: pinTransform }"
                            >
                                <x-ui.map-pin :label="$marker->label" :opens-map="$marker->opensAMap()" :align="$align" />
                            </span>
                        @endforeach
                    </div>
                </div>
                <p class="px-5 py-3 font-display text-2xl font-semibold text-ink">{{ $page->name }}</p>
            </div>

        @elseif ($focus === \App\Enums\ScreenFocus::Clocks)
            @if ($clocks->isEmpty())
                <p class="text-2xl text-ink-faint">Nothing counting.</p>
            @else
                <ul class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($clocks as $clock)
                        <li wire:key="screen-clock-{{ $clock->id }}" class="flex items-center gap-6 rounded-xl border border-line bg-panel p-6">
                            <x-ui.clock :clock="$clock" :size="144" />
                            <div class="min-w-0 flex-1">
                                <p class="text-2xl font-medium text-ink">{{ $clock->name }}</p>
                                <p class="mt-1 font-mono text-lg {{ $clock->isComplete() ? 'text-success' : 'text-ink-faint' }}">
                                    {{ $clock->readout() }}
                                    @if ($clock->isComplete())
                                        &middot; full
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

        @else
            {{-- Nothing chosen and no fight running. The campaign's name, and who is
                 in it, so the wall is never a blank rectangle. --}}
            <div class="flex flex-1 flex-col items-center justify-center gap-8 text-center">
                <x-ui.logo class="size-20 text-ember opacity-60" />
                <h1 class="font-display text-6xl font-semibold tracking-tight text-ink">{{ $campaign->name }}</h1>
                @if ($party->isNotEmpty())
                    <ul class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-2xl text-ink-muted">
                        @foreach ($party as $pc)
                            <li wire:key="screen-pc-{{ $pc->id }}">{{ $pc->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </main>

    @if ($focus !== \App\Enums\ScreenFocus::Clocks && $clocks->isNotEmpty())
        <footer class="border-t border-line bg-panel px-8 py-4">
            <ul class="flex flex-wrap items-center gap-x-10 gap-y-4">
                @foreach ($clocks as $clock)
                    <li wire:key="screen-strip-clock-{{ $clock->id }}" class="flex items-center gap-3">
                        <x-ui.clock :clock="$clock" :size="56" />
                        <div class="min-w-0">
                            <p class="truncate text-lg font-medium text-ink">{{ $clock->name }}</p>
                            <p class="font-mono text-sm {{ $clock->isComplete() ? 'text-success' : 'text-ink-faint' }}">{{ $clock->readout() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </footer>
    @endif
</div>
