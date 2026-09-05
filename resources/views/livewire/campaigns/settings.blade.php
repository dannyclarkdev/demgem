<div class="mx-auto max-w-2xl space-y-6">
    <x-ui.page-header title="Settings" :eyebrow="$campaign->name" />

    <x-ui.card title="Campaign">
        <form wire:submit="save" class="space-y-5">
            <x-ui.input label="Name" name="name" wire:model="name" required />
            <x-ui.textarea label="Description" name="description" wire:model="description" rows="3" hint="Players see this." />
            <x-ui.select label="Game system" name="ruleset" wire:model="ruleset">
                @foreach ($rulesets as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select label="Timezone" name="timezone" wire:model="timezone" hint="Session times are shown in this zone. The table plays in one place.">
                @foreach ($timezones as $option)
                    <option value="{{ $option }}">{{ str_replace('_', ' ', $option) }}</option>
                @endforeach
            </x-ui.select>
            <div class="space-y-3" x-data="{ removing: @entangle('removeCover') }">
                @if ($campaign->coverUrl())
                    <img src="{{ $campaign->coverUrl('card') }}" alt="" class="h-32 w-full rounded-md border border-line object-cover" :class="removing ? 'opacity-30' : ''">
                    <x-ui.checkbox label="Remove cover image" name="removeCover" wire:model="removeCover" x-model="removing" />
                @endif
                @if ($cover && $cover->isPreviewable())
                    <img src="{{ $cover->temporaryUrl() }}" alt="" class="h-32 w-full rounded-md border border-ember/40 object-cover">
                @endif
                <x-ui.field label="Cover image" for="cover" :error="$errors->first('cover')" hint="Wide images work best. Up to 8 MB.">
                    <input type="file" id="cover" wire:model="cover" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-md file:border file:border-line-strong file:bg-raised file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink hover:file:border-ink-faint">
                </x-ui.field>
            </div>
            <div class="flex justify-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">Save</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Export">
        <p class="text-sm text-ink-muted">
            Everything in this campaign: every entity with its GM notes, every session with its prep, secrets,
            and recaps, the quests, the encounters, the tables, and the dice log.
        </p>
        <p class="mt-2 text-sm text-ink-faint">
            Both leave out email addresses, invite links, and deleted things.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <x-ui.button :href="route('campaigns.archive', $campaign)" variant="secondary" icon="arrow-down">Download archive</x-ui.button>
                <p class="mt-2 text-xs text-ink-faint">
                    A zip with the pictures in it, plus a Markdown copy an Obsidian vault can open.
                    This is the one to keep, and the one to import somewhere else.
                </p>
            </div>
            <div>
                <x-ui.button :href="route('campaigns.export', $campaign)" variant="ghost" icon="arrow-down">Download JSON</x-ui.button>
                <p class="mt-2 text-xs text-ink-faint">
                    The document on its own: greppable, diffable, and readable in a browser.
                    Images travel as links rather than files.
                </p>
            </div>
        </div>
    </x-ui.card>

    @if ($role === \App\Enums\CampaignRole::Owner)
        <x-ui.card title="Transfer ownership">
            <p class="text-sm text-ink-muted">The new owner gets full control. You stay in the campaign as a co-GM.</p>
            @if ($transferCandidates->isEmpty())
                <p class="mt-3 text-sm text-ink-faint">Invite another member first.</p>
            @else
                <form wire:submit="transfer" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-ui.select label="New owner" name="newOwnerId" wire:model="newOwnerId" class="sm:flex-1">
                        <option value="">Choose a member</option>
                        @foreach ($transferCandidates as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->user->name }} ({{ $candidate->role->label() }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button type="submit" variant="secondary" wire:confirm="Transfer ownership? You keep co-GM access." wire:loading.attr="disabled">Transfer</x-ui.button>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card title="Delete campaign" class="border-danger/30">
            <p class="text-sm text-ink-muted">This removes the campaign for every member. Type <span class="font-medium text-ink">{{ $campaign->name }}</span> to confirm.</p>
            <form wire:submit="delete" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                <x-ui.input name="deleteConfirmation" wire:model="deleteConfirmation" placeholder="{{ $campaign->name }}" class="sm:flex-1" autocomplete="off" />
                <x-ui.button type="submit" variant="danger" icon="trash" wire:loading.attr="disabled">Delete campaign</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
