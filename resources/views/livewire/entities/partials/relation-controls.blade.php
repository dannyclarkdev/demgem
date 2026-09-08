@if ($canManage)
    <span class="flex shrink-0 items-center gap-1">
        <button
            type="button"
            wire:click="setVisibility('{{ $relation->id }}', {{ $relation->player_visible ? 'false' : 'true' }})"
            class="inline-flex items-center"
            aria-label="{{ $relation->player_visible ? 'Hide this relationship from the party' : 'Show this relationship to the party' }}"
            aria-pressed="{{ $relation->player_visible ? 'true' : 'false' }}"
        >
            @if ($relation->player_visible)
                <x-ui.badge variant="success" icon="eye">Shown</x-ui.badge>
            @else
                <x-ui.badge variant="dm" icon="eye-off">Hidden</x-ui.badge>
            @endif
        </button>
        <x-ui.button size="icon" variant="ghost" icon="x" wire:click="remove('{{ $relation->id }}')" wire:confirm="Remove this relationship?" aria-label="Remove this relationship" />
    </span>
@endif
