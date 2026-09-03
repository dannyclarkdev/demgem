{{--
    One image, a transform, and pointer events. See resources/js/map.js for why
    there is no map library in here.

    touch-action: none on the frame, or a two-finger pinch scrolls the page instead
    of zooming the map, which is the single thing that decides whether this works on
    a tablet at all.

    Every pin in $markers was chosen in the query under this viewer's own role. There
    is no @if in here that hides one, because there is nothing here to hide.
--}}
<div>
    @if ($map === null)
        <p class="rounded-lg border border-dashed border-line-strong px-6 py-14 text-center text-ink-faint">That map is gone.</p>
    @elseif ($imageUrl === null)
        <x-ui.empty-state
            icon="map"
            title="No image yet"
            description="A map needs a picture. Upload one and it fills this space."
        >
            @can('update', $map)
                <x-ui.button :href="$map->editUrl()" size="sm" icon="edit">Upload the map</x-ui.button>
            @endcan
        </x-ui.empty-state>
    @else
        <div x-data="mapViewer({ canEdit: @js($isDm) })" class="overflow-hidden rounded-lg border border-line bg-panel">
            <div class="flex flex-wrap items-center gap-1.5 border-b border-line px-3 py-2">
                <x-ui.button variant="secondary" size="lg" x-on:click="zoomBy(1.5)" x-bind:disabled="! canZoomIn" aria-label="Zoom in">
                    <x-ui.icon name="plus" class="size-4" />
                </x-ui.button>
                <x-ui.button variant="secondary" size="lg" x-on:click="zoomBy(1 / 1.5)" x-bind:disabled="! canZoomOut" aria-label="Zoom out">
                    <x-ui.icon name="minus" class="size-4" />
                </x-ui.button>
                <x-ui.button variant="ghost" size="lg" x-on:click="reset()">Fit</x-ui.button>

                @if ($isDm)
                    <x-ui.button
                        size="lg"
                        icon="map-pin"
                        x-on:click="placing = ! placing"
                        x-bind:class="placing ? 'ring-2 ring-ember ring-offset-2 ring-offset-panel' : ''"
                    >
                        <span x-text="placing ? 'Click the map' : 'Add a pin'">Add a pin</span>
                    </x-ui.button>

                    @if ($markers->isNotEmpty())
                        <x-ui.button variant="ghost" size="lg" icon="eye" wire:click="setAllVisibility(true)">Reveal all</x-ui.button>
                        <x-ui.button variant="ghost" size="lg" icon="eye-off" wire:click="setAllVisibility(false)">Hide all</x-ui.button>
                    @endif
                @endif

                <span class="ml-auto font-mono text-sm tabular-nums text-ink-faint" x-text="zoomPercent + '%'"></span>
            </div>

            {{-- The frame is the picture's own shape, set from naturalWidth once it
                 loads, and capped so a tall map does not run off the screen. The
                 image then fills the frame exactly, which is what makes clamping the
                 pan to the frame the same thing as clamping it to the map. --}}
            <div
                x-ref="frame"
                x-bind:style="{ aspectRatio: ratio }"
                x-bind:class="placing ? 'cursor-crosshair' : ''"
                class="relative max-h-[75vh] w-full touch-none overflow-hidden bg-canvas select-none"
                x-on:wheel="onWheel($event)"
                x-on:pointerdown="onPointerDown($event)"
                x-on:pointermove="onPointerMove($event)"
                x-on:pointerup="onPointerUp($event)"
                x-on:pointercancel="onPointerUp($event)"
                x-on:click="onFrameClick($event)"
            >
                <div
                    class="absolute inset-0 origin-top-left"
                    x-bind:style="{ transform }"
                    x-bind:class="placing ? '' : (pointers.size ? 'cursor-grabbing' : 'cursor-grab')"
                >
                    <img
                        x-ref="image"
                        src="{{ $imageUrl }}"
                        alt="{{ $map->name }}"
                        class="pointer-events-none h-full w-full object-contain"
                        draggable="false"
                        loading="eager"
                        x-on:load="onImageLoad()"
                    >

                    @foreach ($markers as $marker)
                        {{-- A pin near an edge keeps its tail on the coordinate and
                             leans its label inwards, or the label is clipped by the
                             frame and the name is lost on a narrow screen. --}}
                        @php
                            // The tail is inset 20px from the leaning edge: ml-4/mr-4
                            // is 16px and the tail is 8px wide, so its centre is 16+4
                            // in. The translate pays that back, and the tail lands on
                            // the coordinate whichever way the label leans.
                            [$shift, $origin, $align] = match (true) {
                                $marker->x < 18 => ['-20px -100%', 'origin-bottom-left', 'left'],
                                $marker->x > 82 => ['calc(-100% + 20px) -100%', 'origin-bottom-right', 'right'],
                                default => ['-50% -100%', 'origin-bottom', 'center'],
                            };
                        @endphp
                        <button
                            type="button"
                            wire:key="pin-{{ $marker->id }}"
                            class="absolute z-10 {{ $origin }} {{ $isDm ? 'cursor-pointer' : 'cursor-default' }}"
                            style="left: {{ $marker->x }}%; top: {{ $marker->y }}%; translate: {{ $shift }};"
                            x-bind:style="{ transform: pinTransform }"
                            @if ($isDm)
                                x-on:pointerdown="startPinDrag($event, @js($marker->id))"
                                x-on:pointerup="endPinDrag($event)"
                                x-on:pointercancel="endPinDrag($event)"
                                x-on:click="onPinClick($event, @js($marker->id))"
                            @elseif ($marker->opensAMap())
                                onclick="window.location = @js($marker->target->url())"
                            @endif
                            aria-label="{{ $marker->label }}"
                        >
                            <x-ui.map-pin
                                :label="$marker->label"
                                :hidden="$isDm && ! $marker->isVisibleToPlayers()"
                                :opens-map="$marker->opensAMap()"
                                :align="$align"
                            />
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- A player with an empty map is not looking at a bug. Say which it is. --}}
            @if (! $isDm && $markers->isEmpty())
                <p class="border-t border-line px-3 py-2 text-sm text-ink-faint">
                    Nothing marked on this one yet. Pins turn up here as the party finds the places.
                </p>
            @endif

            @if ($isDm)
                @php
                    $shown = $markers->where('player_visible', true)->count();
                    $tally = $markers->count().' '.Str::plural('pin', $markers->count()).', '.$shown.' the party can see.';
                @endphp
                <p class="border-t border-line px-3 py-2 text-sm text-ink-faint">
                    {{ $tally }} Drag a pin to move it, tap one to name it.
                </p>
            @endif
        </div>

        @if ($isDm && $editing)
            <x-ui.card title="This pin" class="mt-4">
                <form wire:submit="saveMarker" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-48 flex-1">
                        <x-ui.input name="label" wire:model="label" label="Label" class="h-11 text-base" />
                    </div>
                    <div class="min-w-48 flex-1">
                        <x-ui.select name="targetId" wire:model="targetId" label="Points at">
                            <option value="">Nothing, just a label</option>
                            @foreach ($targetOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }} ({{ $option->type->label() }})</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    @php ($openPin = $markers->firstWhere('id', $editing))
                    @if ($openPin)
                        {{-- The same eye, the same word, and the same default the
                             tracker taught a GM in slice 5. --}}
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            size="lg"
                            wire:click="toggleVisibility('{{ $editing }}')"
                            :title="$openPin->isVisibleToPlayers() ? 'The party sees this pin' : 'Hidden from the party'"
                        >
                            <x-ui.icon :name="$openPin->isVisibleToPlayers() ? 'eye' : 'eye-off'" class="size-4 {{ $openPin->isVisibleToPlayers() ? 'text-ember' : '' }}" />
                            {{ $openPin->isVisibleToPlayers() ? 'The party sees it' : 'Hidden' }}
                        </x-ui.button>
                    @endif
                    <x-ui.button type="submit" size="lg">Save</x-ui.button>
                    <x-ui.button type="button" variant="ghost" size="lg" wire:click="closeMarker">Cancel</x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="ghost"
                        size="lg"
                        icon="trash"
                        wire:click="removeMarker('{{ $editing }}')"
                        wire:confirm="Take this pin off the map?"
                    >Remove</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    @endif
</div>
