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

        <x-ui.card title="API keys">
            <p class="text-sm text-ink-muted">A key lets a script or an assistant read what you can read, in every campaign you belong to, and write what you can write if you tick the box. Treat one like a password.</p>

            @if ($newToken !== null)
                <div class="mt-4 rounded-md border border-ember/40 bg-ember/5 p-3" x-data="{ copied: false }">
                    <p class="text-sm font-medium text-ink">Copy it now. It is not shown again.</p>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" readonly value="{{ $newToken }}" class="ui-input min-w-0 flex-1 font-mono text-xs" aria-label="New API key" x-ref="key" x-on:focus="$el.select()">
                        <x-ui.button type="button" variant="secondary" size="sm" icon="copy" x-on:click="navigator.clipboard.writeText($refs.key.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                        </x-ui.button>
                    </div>
                </div>
            @endif

            <form wire:submit="createToken" class="mt-4 space-y-3">
                <x-ui.input label="Name" name="tokenName" wire:model="tokenName" placeholder="My assistant" autocomplete="off" hint="So you know which one to revoke later." />
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <x-ui.checkbox label="Can write" name="tokenCanWrite" wire:model="tokenCanWrite" />
                    <x-ui.button type="submit" variant="secondary" icon="key" wire:loading.attr="disabled">Create key</x-ui.button>
                </div>
            </form>

            @if ($tokens->isNotEmpty())
                <ul class="mt-4 divide-y divide-line border-t border-line">
                    @foreach ($tokens as $token)
                        <li class="flex items-center gap-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink">{{ $token->name }}</p>
                                <p class="text-xs text-ink-faint">
                                    {{ $token->can('write') ? 'Read and write' : 'Read only' }}
                                    · created {{ $token->created_at?->diffForHumans() }}
                                    · {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}
                                </p>
                            </div>
                            <x-ui.button type="button" variant="ghost" size="sm" icon="trash" wire:click="revokeToken({{ $token->id }})" wire:confirm="Revoke this key? Anything that is using it stops working.">Revoke</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>
