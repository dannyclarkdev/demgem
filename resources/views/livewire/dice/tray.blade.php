<div class="flex h-full flex-col">
    <div class="space-y-3 border-b border-line p-4">
        <div class="flex flex-wrap gap-1.5">
            @foreach ($quickDice as $sides)
                <button
                    type="button"
                    wire:click="rollQuick({{ $sides }})"
                    class="inline-flex min-w-12 items-center justify-center rounded-md border border-line bg-canvas px-2.5 py-2 font-mono text-sm text-ink hover:border-ember hover:text-ember"
                >d{{ $sides }}</button>
            @endforeach
        </div>

        <div class="flex gap-1.5" role="group" aria-label="Advantage">
            <button
                type="button"
                wire:click="$set('advantage', '{{ $advantage === 'kh' ? '' : 'kh' }}')"
                class="flex-1 rounded-md border px-2 py-1.5 text-xs font-medium {{ $advantage === 'kh' ? 'border-success bg-success/15 text-success' : 'border-line text-ink-muted hover:text-ink' }}"
                aria-pressed="{{ $advantage === 'kh' ? 'true' : 'false' }}"
            >Advantage</button>
            <button
                type="button"
                wire:click="$set('advantage', '{{ $advantage === 'kl' ? '' : 'kl' }}')"
                class="flex-1 rounded-md border px-2 py-1.5 text-xs font-medium {{ $advantage === 'kl' ? 'border-danger bg-danger/15 text-danger' : 'border-line text-ink-muted hover:text-ink' }}"
                aria-pressed="{{ $advantage === 'kl' ? 'true' : 'false' }}"
            >Disadvantage</button>
        </div>

        @if ($advantage !== '')
            <p class="text-xs text-ink-faint">The first die becomes 2d&hellip;{{ $advantage }}1.</p>
        @endif

        <form wire:submit="roll" class="space-y-2">
            <x-ui.input name="formula" wire:model="formula" placeholder="2d6+3" aria-label="Formula" class="font-mono" />
            <div class="flex gap-2">
                <div class="flex-1">
                    <x-ui.input name="label" wire:model="label" placeholder="Goblin attack" aria-label="What for" />
                </div>
                <x-ui.button type="submit" icon="dice">Roll</x-ui.button>
            </div>
        </form>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        @if ($rolls->isEmpty())
            <p class="p-4 text-sm text-ink-faint">Nothing rolled yet. Try 4d6kh3.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($rolls as $roll)
                    <li wire:key="roll-{{ $roll->id }}" class="flex items-start gap-3 px-4 py-3">
                        <span class="min-w-11 shrink-0 text-right font-display text-2xl font-semibold tabular-nums text-ink">{{ $roll->total }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs text-ink-muted">{{ $roll->formula }}</p>
                            <p class="mt-0.5 flex flex-wrap gap-1">
                                @foreach ($roll->faces() as $face)
                                    <span class="inline-flex size-5 items-center justify-center rounded border font-mono text-[11px] {{ $face['dropped'] ? 'border-line text-ink-faint line-through' : 'border-line-strong text-ink-muted' }}">{{ $face['face'] }}</span>
                                @endforeach
                                @if ($roll->detail['modifier'] !== 0)
                                    <span class="font-mono text-[11px] text-ink-faint">{{ $roll->detail['modifier'] > 0 ? '+' : '' }}{{ $roll->detail['modifier'] }}</span>
                                @endif
                            </p>
                            @if ($roll->label)
                                <p class="mt-0.5 truncate text-xs text-ink-faint">{{ $roll->label }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-[11px] text-ink-faint">{{ $roll->created_at?->setTimezone($campaign->timezone)->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($rolls->isNotEmpty())
        <div class="border-t border-line px-4 py-2 text-right">
            <x-ui.button variant="ghost" size="sm" wire:click="clearLog" wire:confirm="Clear your dice log?">Clear my rolls</x-ui.button>
        </div>
    @endif
</div>
