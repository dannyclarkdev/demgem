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

document.addEventListener('alpine:init', () => {
    window.Alpine.data('mapViewer', () => ({
        scale: 1,
        x: 0,
        y: 0,

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
            this.$refs.frame.setPointerCapture(event.pointerId);
            this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            this.dragged = false;

            if (this.pointers.size === 2) {
                this.pinch = { distance: this.pinchDistance(), scale: this.scale };
            }
        },

        onPointerMove(event) {
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

            if (Math.abs(dx) > 2 || Math.abs(dy) > 2) this.dragged = true;

            this.x += dx;
            this.y += dy;
            this.clamp();
        },

        onPointerUp(event) {
            this.pointers.delete(event.pointerId);

            if (this.pointers.size < 2) this.pinch = null;
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
