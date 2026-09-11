<div>
    <x-ui.page-header
        title="Decisions"
        :eyebrow="$campaign->name"
        :description="$role->isDm()
            ? 'What the party chose, and what it cost them. Reveal a row when they should know it was noted.'
            : 'The choices the party made, and what came of them.'"
    >
        <x-ui.button :href="route('story', $campaign)" variant="secondary" size="sm" icon="book-open">Story</x-ui.button>
    </x-ui.page-header>

    <div class="max-w-3xl">
        <livewire:decisions.log :campaign="$campaign" :wire:key="'decisions-'.$campaign->id" />
    </div>
</div>
