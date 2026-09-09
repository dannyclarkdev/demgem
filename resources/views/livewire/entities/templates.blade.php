<div class="mx-auto max-w-4xl space-y-6">
    <x-ui.page-header title="Entity templates" eyebrow="Campaign prep" />
    <p class="text-sm text-ink-muted">Starting text for new pages, shared by this campaign's GMs. Pages keep their own copy.</p>
    <x-ui.card :title="$editingId ? 'Edit template' : 'New template'">
        <form wire:submit="save" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Name" name="name" wire:model="name" maxlength="120" required />
                <x-ui.select label="Entity type" name="type" wire:model="type">
                    @foreach ($types as $entityType)
                        <option value="{{ $entityType->value }}">{{ $entityType->label() }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.textarea label="Starting body" name="body" wire:model="body" rows="12" maxlength="100000" hint="Markdown headings and prompts. Only the body is copied; visibility and other fields stay with the new page." />
            <div class="flex flex-wrap justify-end gap-3">
                @if ($editingId)
                    <x-ui.button type="button" variant="ghost" wire:click="cancel">Cancel</x-ui.button>
                @endif
                <x-ui.button type="submit" wire:loading.attr="disabled">Save template</x-ui.button>
            </div>
        </form>
    </x-ui.card>
    <x-ui.card title="This campaign's templates" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($templates as $template)
                <li wire:key="template-{{ $template->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="break-words font-medium text-ink">{{ $template->name }}</p>
                        <p class="text-xs text-ink-faint">{{ $template->type->label() }}</p>
                    </div>
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit('{{ $template->id }}')">Edit</x-ui.button>
                    <x-ui.button type="button" variant="ghost" size="sm" wire:click="delete('{{ $template->id }}')" wire:confirm="Delete this template? Pages already created from it will keep their text.">Delete</x-ui.button>
                </li>
            @empty
                <li class="px-5 py-4 text-sm text-ink-faint">No templates yet. Save an outline you want to use again.</li>
            @endforelse
        </ul>
        <div class="px-5 py-3">{{ $templates->links() }}</div>
    </x-ui.card>
</div>
