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
            <x-ui.input label="Currency" name="currency" wire:model="currency" placeholder="gp" hint="What the party ledger counts in. One unit; silver and copper are your arithmetic." />
            <x-ui.input label="Session length" name="sessionLengthMinutes" type="number" min="30" max="720" step="15" wire:model="sessionLengthMinutes" hint="In minutes. Calendar feeds use it for the end of each session." />
            <x-ui.select label="Reminder email" name="reminderLeadHours" wire:model="reminderLeadHours" hint="One email to every member who wants one, before each session with a date. Needs a mailer configured on this install.">
                @foreach ($reminderLeadOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
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
                {{-- What the campaign holds, of what the install allows: the cover, every
                     image, every handout file. Summed on every read. --}}
                <div class="rounded-md border border-line bg-canvas px-3 py-2.5">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="eyebrow">Storage</span>
                        <span class="text-ink-muted">
                            @if ($storageLimited)
                                {{ $storageUsedLabel }} of {{ $storageLimitLabel }}
                            @else
                                {{ $storageUsedLabel }} &middot; no limit on this install
                            @endif
                        </span>
                    </div>
                    @if ($storageLimited)
                        @php($share = $storageLimit > 0 ? min(100, (int) round($storageUsed / $storageLimit * 100)) : 0)
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-raised" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $share }}" aria-label="Storage used">
                            <div class="h-full rounded-full {{ $share >= 90 ? 'bg-danger' : 'bg-ember' }}" style="width: {{ $share }}%"></div>
                        </div>
                    @endif
                </div>

                <x-ui.field label="Cover image" for="cover" :error="$errors->first('cover')" hint="Wide images work best. Up to 8 MB.">
                    <input type="file" id="cover" wire:model="cover" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-md file:border file:border-line-strong file:bg-raised file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink hover:file:border-ink-faint">
                </x-ui.field>
            </div>
            <div class="flex justify-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">Save</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Discord">
        <p class="text-sm text-ink-muted">
            When a recap is published and when a reminder goes out, the channel gets one line and a link.
            Never the recap itself: the channel's members are not always the campaign's.
        </p>
        <form wire:submit="saveDiscord" class="mt-4 space-y-3">
            <x-ui.input label="Webhook URL" name="discordWebhookUrl" wire:model="discordWebhookUrl" placeholder="https://discord.com/api/webhooks/…" autocomplete="off" hint="In Discord: channel settings, Integrations, Webhooks, New Webhook, Copy Webhook URL. Anyone holding it can post to the channel, so it is stored encrypted and never exported. Leave it empty to disconnect." />
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                @if ($campaign->discord_webhook_url !== null)
                    <x-ui.button type="button" variant="ghost" icon="zap" wire:click="sendDiscordTest" wire:loading.attr="disabled">Send a test message</x-ui.button>
                @endif
                <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled">Save</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Entity templates">
        <p class="text-sm text-ink-muted">Reusable starting bodies for characters, locations, quests, and other pages.</p>
        <div class="mt-4"><x-ui.button :href="route('entity-templates.index', $campaign)" variant="secondary">Manage templates</x-ui.button></div>
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
