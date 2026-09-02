<div class="space-y-6">
    <x-ui.page-header title="Profile" eyebrow="Account" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Details">
            <form wire:submit="updateProfile" class="space-y-4">
                <x-ui.input label="Name" name="name" wire:model="name" required autocomplete="name" />
                <x-ui.input label="Email" name="email" type="email" wire:model="email" required autocomplete="email" />
                <div class="flex justify-end">
                    <x-ui.button type="submit" wire:loading.attr="disabled">Save</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Password">
            <form wire:submit="updatePassword" class="space-y-4">
                <x-ui.input label="Current password" name="current_password" type="password" wire:model="current_password" required autocomplete="current-password" />
                <x-ui.input label="New password" name="password" type="password" wire:model="password" required autocomplete="new-password" />
                <x-ui.input label="Confirm new password" name="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password" />
                <div class="flex justify-end">
                    <x-ui.button type="submit" wire:loading.attr="disabled">Change password</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
