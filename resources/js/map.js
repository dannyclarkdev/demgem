/**
 * The map viewer: pan and zoom on one image, in about a hundred lines.
 *
 * There is no map library here on purpose. Leaflet and OpenSeadragon are tile
 * clients, and a campaign map is one file: what they would give us over a
 * transform and a pointer handler is a tile pyramid nobody is going to build.
 *
 * Nothing in here talks to the server. Panning and zooming are the two things a
 * person does constantly, and a Livewire round trip for either would make the
 * map feel like a website instead of a map.
 *
 * Pointer events rather than mouse events, so a mouse, a pen and a finger take
 * the same code path. That is the whole reason the tablet works.
 */
const MIN_SCALE = 1;
const MAX_SCALE = 8;

/** Pixels a pointer may wander before a tap counts as a drag. */
const DRAG_SLOP = 4;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('mapViewer', ({ canEdit = false } = {}) => ({
        scale: 1,
        x: 0,
        y: 0,

        // GM only, and off until the GM presses "Add a pin". The next click on the
        // image is then a coordinate instead of a pan.
        canEdit,
        placing: false,
        dragging: null,
        suppressPinClick: false,

        // The frame takes the image's own shape, read once when it loads. A fixed
        // aspect ratio letterboxes the image inside the frame, and then panning
        // clamps to the frame rather than to the picture: the map can be dragged
        // into an empty band, which reads as a bug because it is one.
        ratio: 3 / 2,

        // Live pointers, keyed by pointerId. One is a drag; two are a pinch.
        pointers: new Map(),
        pinch: null,
        dragged: false,

        init() {
            // A resize changes what fits, so the clamp has to run again or the
            // image can end up parked off the edge of a narrower frame.
            this.onResize = () => this.clamp();
            window.addEventListener('resize', this.onResize);
        },

        destroy() {
            window.removeEventListener('resize', this.onResize);
        },

        onImageLoad() {
            const image = this.$refs.image;

            if (image.naturalWidth && image.naturalHeight) {
                this.ratio = image.naturalWidth / image.naturalHeight;
            }

            this.reset();
        },

        get transform() {
            return `translate(${this.x}px, ${this.y}px) scale(${this.scale})`;
        },

        /**
         * Pins keep their size on screen at every zoom, or they are dinner plates at
         * 8x. The offset that puts a pin's tail on its coordinate is the standalone
         * `translate` property in the markup, not part of this transform: a pin near
         * an edge shifts its label inwards, and that choice belongs with the
         * coordinate rather than in here.
         */
        get pinTransform() {
            return `scale(${1 / this.scale})`;
        },

        get zoomPercent() {
            return Math.round(this.scale * 100);
        },

        get canZoomIn() {
            return this.scale < MAX_SCALE;
        },

        get canZoomOut() {
            return this.scale > MIN_SCALE;
        },

        /** Zoom about a point in the frame, keeping whatever is under it under it. */
        zoomTo(next, originX, originY) {
            const frame = this.$refs.frame.getBoundingClientRect();
            const cx = originX ?? frame.width / 2;
            const cy = originY ?? frame.height / 2;

            next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, next));

            const ratio = next / this.scale;

            this.x = cx - (cx - this.x) * ratio;
            this.y = cy - (cy - this.y) * ratio;
            this.scale = next;

            this.clamp();
        },

        zoomBy(factor) {
            this.zoomTo(this.scale * factor);
        },

        reset() {
            this.scale = 1;
            this.x = 0;
            this.y = 0;
        },

        /**
         * The image never leaves the frame. At 1x it sits still; past that it may
         * move by exactly the overflow and no further. A GM who has lost the map
         * off the side of the screen has stopped running the game.
         */
        clamp() {
            if (!this.$refs.frame) return;

            const frame = this.$refs.frame.getBoundingClientRect();
            const overflowX = Math.max(0, frame.width * this.scale - frame.width);
            const overflowY = Math.max(0, frame.height * this.scale - frame.height);

            this.x = Math.min(0, Math.max(-overflowX, this.x));
            this.y = Math.min(0, Math.max(-overflowY, this.y));
        },

        onWheel(event) {
            event.preventDefault();

            const frame = this.$refs.frame.getBoundingClientRect();

            this.zoomTo(
                this.scale * (event.deltaY < 0 ? 1.15 : 1 / 1.15),
                event.clientX - frame.left,
                event.clientY - frame.top,
            );
        },

        onPointerDown(event) {
            // A pin being dragged owns the gesture. The map must not pan underneath it.
            if (this.dragging) return;

            this.$refs.frame.setPointerCapture(event.pointerId);
            this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            this.dragged = false;

            if (this.pointers.size === 2) {
                this.pinch = { distance: this.pinchDistance(), scale: this.scale };
            }
        },

        onPointerMove(event) {
            if (this.dragging) {
                // A real mouse emits a pointermove between press and release, so a
                // tap is only told from a drag by distance. Without the threshold
                // every tap is a move to where the pin already was, and the pin
                // never opens.
                if (Math.hypot(event.clientX - this.dragging.x, event.clientY - this.dragging.y) > DRAG_SLOP) {
                    this.dragging.moved = true;
                }

                return;
            }

            if (!this.pointers.has(event.pointerId)) return;

            const previous = this.pointers.get(event.pointerId);
            this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (this.pointers.size === 2 && this.pinch) {
                const frame = this.$refs.frame.getBoundingClientRect();
                const centre = this.pinchCentre();

                this.zoomTo(
                    this.pinch.scale * (this.pinchDistance() / this.pinch.distance),
                    centre.x - frame.left,
                    centre.y - frame.top,
                );

                this.dragged = true;

                return;
            }

            const dx = event.clientX - previous.x;
            const dy = event.clientY - previous.y;

            if (Math.abs(dx) > DRAG_SLOP / 2 || Math.abs(dy) > DRAG_SLOP / 2) this.dragged = true;

            this.x += dx;
            this.y += dy;
            this.clamp();
        },

        onPointerUp(event) {
            this.pointers.delete(event.pointerId);

            if (this.pointers.size < 2) this.pinch = null;
        },

        /**
         * A point on the image, as a percentage of the image itself. The server never
         * learns the screen size, so the same pair of numbers means the same place on
         * a phone and on a projector.
         */
        percentAt(clientX, clientY) {
            const image = this.$refs.image.getBoundingClientRect();

            return {
                x: round(((clientX - image.left) / image.width) * 100),
                y: round(((clientY - image.top) / image.height) * 100),
            };
        },

        /**
         * A drag that ends over the image is not a click, or every pan would drop a
         * pin where the GM let go.
         */
        onFrameClick(event) {
            if (!this.placing || this.dragged) return;

            const point = this.percentAt(event.clientX, event.clientY);

            if (point.x < 0 || point.x > 100 || point.y < 0 || point.y > 100) return;

            this.placing = false;
            this.$wire.call('placeMarker', point.x, point.y);
        },

        /**
         * Dragging a pin. It captures the pointer so the map stays still underneath.
         *
         * Only the drag lives on pointer events. Opening a pin is a click, because a
         * pin is a button and a keyboard user presses Enter on it: a pointer-only
         * handler would leave them no way in at all.
         */
        startPinDrag(event, markerId) {
            if (!this.canEdit) return;

            event.stopPropagation();
            event.target.setPointerCapture(event.pointerId);

            this.dragging = {
                markerId,
                pointerId: event.pointerId,
                x: event.clientX,
                y: event.clientY,
                moved: false,
            };
        },

        endPinDrag(event) {
            const drag = this.dragging;

            this.dragging = null;

            if (!drag || drag.pointerId !== event.pointerId || !drag.moved) return;

            event.stopPropagation();

            // The click that follows this pointerup would open the pin we just moved.
            this.suppressPinClick = true;

            const point = this.percentAt(event.clientX, event.clientY);

            this.$wire.call('moveMarker', drag.markerId, point.x, point.y);
        },

        onPinClick(event, markerId) {
            event.stopPropagation();

            if (this.suppressPinClick) {
                this.suppressPinClick = false;
                return;
            }

            if (!this.canEdit) return;

            this.$wire.call('openMarker', markerId);
        },

        pinchDistance() {
            const [a, b] = [...this.pointers.values()];

            return Math.hypot(a.x - b.x, a.y - b.y) || 1;
        },

        pinchCentre() {
            const [a, b] = [...this.pointers.values()];

            return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        },
    }));
});

function round(value) {
    return Math.round(value * 1000) / 1000;
}
