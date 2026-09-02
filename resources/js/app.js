// Livewire 4 bundles Alpine and starts it on DOMContentLoaded. This module runs before that,
// so registering on alpine:init is safe. Theme bootstrapping lives inline in the layout <head>.

document.addEventListener('alpine:init', () => {
    window.Alpine.data('markdownEditor', ({ url }) => ({
        mode: 'write',
        open: false,
        results: [],
        active: 0,
        query: null,
        triggerStart: null,
        timer: null,

        onInput() {
            const ta = this.$refs.ta;
            const pos = ta.selectionStart;
            const before = ta.value.slice(0, pos);

            let match = before.match(/\[\[([^\[\]\n|]*)$/);
            if (match) {
                this.triggerStart = pos - match[0].length;
            } else {
                match = before.match(/(?:^|[\s(])@([^\s@\[\]]{0,60})$/);
                if (match) this.triggerStart = pos - match[1].length - 1;
            }

            if (!match) {
                this.close();
                return;
            }

            this.query = match[1];
            this.open = true;
            this.search();
        },

        search() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                try {
                    const response = await fetch(`${url}?q=${encodeURIComponent(this.query ?? '')}`, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    this.results = response.ok ? await response.json() : [];
                } catch (error) {
                    this.results = [];
                }
                this.active = 0;
            }, 120);
        },

        onKeydown(event) {
            if (!this.open) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.active = Math.min(this.active + 1, Math.max(this.results.length - 1, 0));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.active = Math.max(this.active - 1, 0);
            } else if ((event.key === 'Enter' || event.key === 'Tab') && this.results[this.active]) {
                event.preventDefault();
                this.choose(this.results[this.active]);
            } else if (event.key === 'Escape') {
                this.close();
            }
        },

        choose(item) {
            const ta = this.$refs.ta;
            const pos = ta.selectionStart;
            const link = `[[${item.needsPrefix ? item.type + ':' : ''}${item.name}]]`;
            const before = ta.value.slice(0, this.triggerStart);
            let after = ta.value.slice(pos);
            if (after.startsWith(']]')) after = after.slice(2);

            ta.value = before + link + after;
            const caret = before.length + link.length;
            ta.setSelectionRange(caret, caret);
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            this.close();
            ta.focus();
        },

        close() {
            this.open = false;
            this.results = [];
            this.query = null;
        },

        wrap(beforeText, afterText, placeholder) {
            const ta = this.$refs.ta;
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const selected = ta.value.slice(start, end) || placeholder;

            ta.value = ta.value.slice(0, start) + beforeText + selected + afterText + ta.value.slice(end);
            ta.setSelectionRange(start + beforeText.length, start + beforeText.length + selected.length);
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            ta.focus();
        },

        prefixLines(prefix) {
            const ta = this.$refs.ta;
            const start = ta.value.lastIndexOf('\n', ta.selectionStart - 1) + 1;
            let end = ta.value.indexOf('\n', ta.selectionEnd);
            if (end === -1) end = ta.value.length;

            const block = ta.value
                .slice(start, end)
                .split('\n')
                .map((line) => (line.startsWith(prefix) ? line.slice(prefix.length) : prefix + line))
                .join('\n');

            ta.value = ta.value.slice(0, start) + block + ta.value.slice(end);
            ta.setSelectionRange(start, start + block.length);
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            ta.focus();
        },
    }));
});
