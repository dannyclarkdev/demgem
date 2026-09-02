<div class="space-y-8">
    <x-ui.page-header title="Members" :eyebrow="$campaign->name">
        @can('leave', $campaign)
            <x-ui.button variant="ghost" size="sm" icon="log-out" wire:click="leave" wire:confirm="Leave {{ $campaign->name }}? You need a new invite to come back.">Leave campaign</x-ui.button>
        @endcan
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <ul class="divide-y divide-line">
            @foreach ($members as $member)
                <li class="flex items-center gap-3 px-5 py-3" wire:key="member-{{ $member->id }}">
                    <x-ui.avatar :name="$member->user->name" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $member->user->name }} @if ($member->user_id === auth()->id())<span class="text-ink-faint">(you)</span>@endif</p>
                        @if ($role->isDm())
                            <p class="truncate text-xs text-ink-faint">{{ $member->user->email }}</p>
                        @endif
                    </div>

                    @if ($role === \App\Enums\CampaignRole::Owner && ! $member->isOwner())
                        <select
                            class="ui-input w-auto py-1 text-xs"
                            wire:change="changeRole({{ $member->id }}, $event.target.value)"
                            aria-label="Role for {{ $member->user->name }}"
                        >
                            @foreach ($assignableRoles as $option)
                                <option value="{{ $option->value }}" @selected($member->role === $option)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-ui.badge :variant="$member->role->isDm() ? 'accent' : 'neutral'">{{ $member->role->label() }}</x-ui.badge>
                    @endif

                    @can('removeMember', [$campaign, $member])
                        <x-ui.button variant="ghost" size="icon" icon="x" wire:click="remove({{ $member->id }})" wire:confirm="Remove {{ $member->user->name }} from the campaign?" aria-label="Remove {{ $member->user->name }}" />
                    @endcan
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    @if ($role->isDm())
        <section id="invites" class="space-y-4">
            <div>
                <h2 class="font-display text-xl font-semibold">Invite links</h2>
                <p class="text-sm text-ink-muted">Anyone with a link can join with the role it carries. Revoke a link after you remove someone.</p>
            </div>

            <x-ui.card>
                <form wire:submit="createInvite" class="grid gap-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                    <x-ui.select label="Role" name="inviteRole" wire:model="inviteRole">
                        @foreach ($invitableRoles as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Expires" name="inviteExpiresIn" wire:model="inviteExpiresIn">
                        <option value="1">In 1 day</option>
                        <option value="7">In 7 days</option>
                        <option value="30">In 30 days</option>
                        <option value="">Never</option>
                    </x-ui.select>
                    <x-ui.select label="Max uses" name="inviteMaxUses" wire:model="inviteMaxUses">
                        <option value="">Unlimited</option>
                        <option value="1">1</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                    </x-ui.select>
                    <x-ui.button type="submit" icon="link" wire:loading.attr="disabled">Create link</x-ui.button>
                </form>
            </x-ui.card>

            @if ($invites->isNotEmpty())
                <x-ui.card :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($invites as $invite)
                            <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="invite-{{ $invite->id }}" x-data="{ copied: false }">
                                <x-ui.badge :variant="$invite->role->isDm() ? 'accent' : 'neutral'">{{ $invite->role->label() }}</x-ui.badge>
                                <code class="min-w-0 flex-1 truncate font-mono text-xs text-ink-muted">{{ $invite->url() }}</code>
                                <span class="text-xs text-ink-faint">
                                    {{ $invite->uses }}{{ $invite->max_uses ? ' / '.$invite->max_uses : '' }} used
                                    · {{ $invite->expires_at ? 'expires '.$invite->expires_at->diffForHumans() : 'never expires' }}
                                </span>
                                <x-ui.button
                                    variant="secondary"
                                    size="sm"
                                    icon="copy"
                                    @click="navigator.clipboard.writeText('{{ $invite->url() }}').then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                                >
                                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                </x-ui.button>
                                <x-ui.button variant="ghost" size="icon" icon="trash" wire:click="revokeInvite('{{ $invite->id }}')" wire:confirm="Revoke this invite link?" aria-label="Revoke invite" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </section>
    @endif
</div>
