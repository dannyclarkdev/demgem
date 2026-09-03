{{--
    The controls only. Dice\Log holds the results, because from slice 5 the log
    belongs to the campaign, and both screens that show this tray show that log
    under it.

    Every control here is 44px on its short side. It is the one thing a player taps
    on a tablet mid-game, and the slice 2 rule about tap targets was written for
    exactly this.
--}}
<div class="space-y-3 p-4">
    <div class="flex flex-wrap gap-1.5">
        @foreach ($quickDice as $sides)
            <button
                type="button"
                wire:click="rollQuick({{ $sides }})"
                class="inline-flex h-11 min-w-12 items-center justify-center rounded-md border border-line bg-canvas px-3 font-mono text-base text-ink hover:border-ember hover:text-ember"
            >d{{ $sides }}</button>
        @endforeach
    </div>

    <div class="flex gap-1.5" role="group" aria-label="Advantage">
        <button
            type="button"
            wire:click="$set('advantage', '{{ $advantage === 'kh' ? '' : 'kh' }}')"
            class="h-11 flex-1 rounded-md border px-2 text-sm font-medium {{ $advantage === 'kh' ? 'border-success bg-success/15 text-success' : 'border-line text-ink-muted hover:text-ink' }}"
            aria-pressed="{{ $advantage === 'kh' ? 'true' : 'false' }}"
        >Advantage</button>
        <button
            type="button"
            wire:click="$set('advantage', '{{ $advantage === 'kl' ? '' : 'kl' }}')"
            class="h-11 flex-1 rounded-md border px-2 text-sm font-medium {{ $advantage === 'kl' ? 'border-danger bg-danger/15 text-danger' : 'border-line text-ink-muted hover:text-ink' }}"
            aria-pressed="{{ $advantage === 'kl' ? 'true' : 'false' }}"
        >Disadvantage</button>
    </div>

    @if ($advantage !== '')
        <p class="text-sm text-ink-faint">The first die becomes 2d&hellip;{{ $advantage }}1.</p>
    @endif

    {{-- GM roles only. A player's roll is never private, and the action says so again
         for anything that reaches it without going through this button. --}}
    @if ($mayRollPrivately)
        <button
            type="button"
            wire:click="$toggle('private')"
            class="flex h-11 w-full items-center gap-2 rounded-md border px-3 text-sm font-medium {{ $private ? 'border-dm bg-dm/15 text-dm' : 'border-line text-ink-muted hover:text-ink' }}"
            aria-pressed="{{ $private ? 'true' : 'false' }}"
        >
            <x-ui.icon :name="$private ? 'eye-off' : 'eye'" class="size-4" />
            Behind the screen
            <span class="ml-auto text-sm font-normal text-ink-faint">{{ $private ? 'only you see it' : 'the table sees it' }}</span>
        </button>
    @endif

    <form wire:submit="roll" class="space-y-2">
        <x-ui.input name="formula" wire:model="formula" placeholder="2d6+3" aria-label="Formula" class="h-11 font-mono text-base" />
        <div class="flex gap-2">
            <div class="flex-1">
                <x-ui.input name="label" wire:model="label" placeholder="Goblin attack" aria-label="What for" class="h-11 text-base" />
            </div>
            <x-ui.button type="submit" size="lg" icon="dice">Roll</x-ui.button>
        </div>
    </form>
</div>
