{{--
    One image, a transform, and pointer events. See resources/js/map.js for why
    there is no map library in here.

    touch-action: none on the frame, or a two-finger pinch scrolls the page instead
    of zooming the map, which is the single thing that decides whether this works on
    a tablet at all.
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
        <div x-data="mapViewer" class="overflow-hidden rounded-lg border border-line bg-panel">
            <div class="flex flex-wrap items-center gap-1.5 border-b border-line px-3 py-2">
                <x-ui.button
                    variant="secondary"
                    size="lg"
                    x-on:click="zoomBy(1.5)"
                    x-bind:disabled="! canZoomIn"
                    aria-label="Zoom in"
                >
                    <x-ui.icon name="plus" class="size-4" />
                </x-ui.button>
                <x-ui.button
                    variant="secondary"
                    size="lg"
                    x-on:click="zoomBy(1 / 1.5)"
                    x-bind:disabled="! canZoomOut"
                    aria-label="Zoom out"
                >
                    <x-ui.icon name="minus" class="size-4" />
                </x-ui.button>
                <x-ui.button variant="ghost" size="lg" x-on:click="reset()">Fit</x-ui.button>

                <span class="ml-auto font-mono text-sm tabular-nums text-ink-faint" x-text="zoomPercent + '%'"></span>
            </div>

            {{-- The frame is the picture's own shape, set from naturalWidth once it
                 loads, and capped so a tall map does not run off the screen. The
                 image then fills the frame exactly, which is what makes clamping the
                 pan to the frame the same thing as clamping it to the map. --}}
            <div
                x-ref="frame"
                x-bind:style="{ aspectRatio: ratio }"
                class="relative max-h-[75vh] w-full touch-none overflow-hidden bg-canvas select-none"
                x-on:wheel="onWheel($event)"
                x-on:pointerdown="onPointerDown($event)"
                x-on:pointermove="onPointerMove($event)"
                x-on:pointerup="onPointerUp($event)"
                x-on:pointercancel="onPointerUp($event)"
            >
                <div
                    class="absolute inset-0 origin-top-left"
                    x-bind:style="{ transform }"
                    x-bind:class="pointers.size ? 'cursor-grabbing' : 'cursor-grab'"
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
                </div>
            </div>
        </div>
    @endif
</div>
