<div class="mx-auto max-w-xl">
    <x-ui.page-header title="New campaign" eyebrow="Library" />

    <x-ui.card>
        <form wire:submit="save" class="space-y-5">
            <x-ui.input label="Name" name="name" wire:model="name" required autofocus placeholder="Curse of the Ember Throne" />
            <x-ui.textarea label="Description" name="description" wire:model="description" rows="3" hint="A short pitch. Players see this." />
            <x-ui.select label="Game system" name="ruleset" wire:model="ruleset" hint="Rulesets add stat blocks and encounter math later. You can change this.">
                @foreach ($rulesets as $ruleset)
                    <option value="{{ $ruleset->value }}">{{ $ruleset->label() }}</option>
                @endforeach
            </x-ui.select>
            <div class="flex items-center justify-end gap-2">
                <x-ui.button :href="route('campaigns.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled">Create campaign</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
