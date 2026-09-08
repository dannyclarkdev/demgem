@if ($canManage)
    <span class="flex shrink-0 items-center gap-1">
        @if ($relation->player_visible)
            <x-ui.badge variant="success" icon="eye">Shown</x-ui.badge>
        @else
            <x-ui.badge variant="dm" icon="eye-off">Hidden</x-ui.badge>
        @endif
        <x-ui.button size="icon" variant="ghost" icon="x" wire:click="remove('{{ $relation->id }}')" wire:confirm="Remove this relationship?" aria-label="Remove this relationship" />
    </span>
@endif
