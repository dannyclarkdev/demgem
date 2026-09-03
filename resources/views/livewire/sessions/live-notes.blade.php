<div>
    <div class="mb-1.5 flex items-center justify-between gap-2">
        <label for="live-notes" class="text-sm font-medium text-ink-muted">Live notes</label>
        <span class="flex items-center gap-1.5 text-sm">
            <span wire:dirty wire:target="notes" class="flex items-center gap-1.5 text-ember">
                <x-ui.icon name="clock" class="size-3.5" /> Saving
            </span>
            <span wire:dirty.remove wire:target="notes" class="flex items-center gap-1.5 text-ink-faint">
                @if ($savedAt !== null)
                    <x-ui.icon name="check" class="size-3.5 text-success" /> Saved at {{ $savedAt }}
                @else
                    Saves as you type
                @endif
            </span>
        </span>
    </div>

    <textarea
        id="live-notes"
        wire:model.live.debounce.1500ms="notes"
        wire:dirty.class="ui-input--dirty"
        rows="16"
        class="ui-input resize-y font-mono text-[13px] leading-relaxed"
        placeholder="What actually happened. Names, numbers, promises."
    ></textarea>

    <p class="mt-1.5 text-sm text-ink-faint">GM only. If another GM types at the same time, the last save wins.</p>
</div>
