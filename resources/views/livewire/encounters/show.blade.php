<div>
    <x-ui.page-header :title="$encounter->name" eyebrow="Encounter">
        @if ($encounter->gameSession)
            <x-ui.button :href="$encounter->gameSession->runUrl()" variant="secondary" size="sm" icon="play">{{ $encounter->gameSession->label() }}</x-ui.button>
        @endif
        <x-ui.button :href="route('encounters.index', $campaign)" variant="ghost" size="sm" icon="arrow-left">Encounters</x-ui.button>
        <x-ui.button
            variant="ghost"
            size="sm"
            icon="trash"
            wire:click="delete"
            wire:confirm="Delete {{ $encounter->name }} and everyone in it? This cannot be undone."
        >Delete</x-ui.button>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <livewire:encounters.tracker :campaign="$campaign" :encounter="$encounter" :wire:key="'tracker-'.$encounter->id" />
    </x-ui.card>

    <x-ui.card title="Rename" class="mt-6">
        <form wire:submit="rename" class="flex flex-wrap items-end gap-2">
            <div class="min-w-56 flex-1">
                <x-ui.input name="name" wire:model="name" label="Name" />
            </div>
            <x-ui.button type="submit" variant="secondary">Save</x-ui.button>
        </form>
    </x-ui.card>
</div>
