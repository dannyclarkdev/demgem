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

        <x-ui.card title="Calendar">
            @if ($calendarUrl === null)
                <p class="text-sm text-ink-muted">Every session you can see, in every campaign you belong to, as a feed your calendar app keeps up to date. Times land in your own timezone.</p>
                <div class="mt-4 flex justify-end">
                    <x-ui.button type="button" icon="calendar" wire:click="getCalendarLink" wire:loading.attr="disabled">Get a calendar link</x-ui.button>
                </div>
            @else
                <p class="text-sm text-ink-muted">Subscribe to this address in Google Calendar, Apple Calendar, or Outlook. Anyone holding it can see your session dates, so treat it like a password.</p>
                <div class="mt-4 flex items-center gap-2" x-data="{ copied: false }">
                    <input type="text" readonly value="{{ $calendarUrl }}" class="ui-input min-w-0 flex-1 font-mono text-xs" aria-label="Calendar feed address" x-ref="feed" x-on:focus="$el.select()">
                    <x-ui.button type="button" variant="secondary" size="sm" icon="copy" x-on:click="navigator.clipboard.writeText($refs.feed.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                        <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                    </x-ui.button>
                </div>
                <div class="mt-4 flex justify-end">
                    <x-ui.button type="button" variant="ghost" size="sm" icon="refresh" wire:click="resetCalendarLink" wire:confirm="Reset the link? Any calendar subscribed to the old one stops updating.">Reset the link</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
