<div class="flex h-14 items-center gap-2.5 border-b border-line px-4">
    <a href="{{ route('campaigns.index') }}" class="flex items-center gap-2.5" aria-label="demgem home">
        <x-ui.logo class="size-6 text-ember" />
        @unless ($currentCampaign)
            <span class="font-display text-lg font-semibold tracking-tight">demgem</span>
        @endunless
    </a>
    @if ($currentCampaign)
        <x-ui.dropdown align="left" width="w-60" class="min-w-0 flex-1">
            <x-slot:trigger>
                <button type="button" class="flex w-full min-w-0 items-center gap-1.5 rounded-md px-1.5 py-1 text-left hover:bg-raised">
                    <span class="truncate font-display text-base font-semibold tracking-tight">{{ $currentCampaign->name }}</span>
                    <x-ui.icon name="chevron-down" class="size-3.5 shrink-0 text-ink-faint" />
                </button>
            </x-slot:trigger>
            <p class="eyebrow px-3 pt-1 pb-1">Switch campaign</p>
            @foreach ($userCampaigns as $option)
                <x-ui.dropdown-item :href="route('campaigns.show', $option)" :class="$option->is($currentCampaign) ? 'text-ink' : ''">
                    <span class="truncate">{{ $option->name }}</span>
                    @if ($option->is($currentCampaign))<x-ui.icon name="check" class="ml-auto size-3.5 text-ember" />@endif
                </x-ui.dropdown-item>
            @endforeach
            <div class="my-1 border-t border-line"></div>
            <x-ui.dropdown-item :href="route('campaigns.index')" icon="book-open">All campaigns</x-ui.dropdown-item>
            <x-ui.dropdown-item :href="route('campaigns.create')" icon="plus">New campaign</x-ui.dropdown-item>
        </x-ui.dropdown>
    @endif
    <button type="button" class="ml-auto text-ink-muted hover:text-ink md:hidden" @click="nav = false" aria-label="Close navigation">
        <x-ui.icon name="x" />
    </button>
</div>

<nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
    @if ($currentCampaign)
        <div>
            <p class="eyebrow px-2 pb-2">Campaign</p>
            <x-ui.nav-link :href="route('campaigns.show', $currentCampaign)" :active="request()->routeIs('campaigns.show')" icon="compass">Overview</x-ui.nav-link>
            <x-ui.nav-link :href="route('campaigns.members', $currentCampaign)" :active="request()->routeIs('campaigns.members')" icon="users">Members</x-ui.nav-link>
            @if ($currentRole?->isDm())
                <x-ui.nav-link :href="route('campaigns.settings', $currentCampaign)" :active="request()->routeIs('campaigns.settings')" icon="settings">Settings</x-ui.nav-link>
            @endif
        </div>
        <div>
            <p class="eyebrow px-2 pb-2">Play</p>
            {{-- Every role. It is the player's screen, and the one page they keep open
                 during a game; a co-GM watches the same thing from a second device. --}}
            <x-ui.nav-link
                :href="route('table', $currentCampaign)"
                :active="request()->routeIs('table')"
                icon="play"
            >The table</x-ui.nav-link>
            <x-ui.nav-link
                :href="route('sessions.index', $currentCampaign)"
                :active="request()->routeIs('sessions.*')"
                icon="calendar"
                :count="$sessionCount"
            >Sessions</x-ui.nav-link>
            <x-ui.nav-link
                :href="route('story', $currentCampaign)"
                :active="request()->routeIs('story')"
                icon="book-open"
            >Story</x-ui.nav-link>
            @if ($currentRole?->isDm())
                <x-ui.nav-link
                    :href="route('encounters.index', $currentCampaign)"
                    :active="request()->routeIs('encounters.*')"
                    icon="swords"
                    :count="$encounterCount"
                >Encounters</x-ui.nav-link>
                <x-ui.nav-link
                    :href="route('tables.index', $currentCampaign)"
                    :active="request()->routeIs('tables.*')"
                    icon="list"
                    :count="$tableCount"
                >Tables</x-ui.nav-link>
            @endif
        </div>
        <div>
            <p class="eyebrow px-2 pb-2">World</p>
            @foreach ($entityTypes as $entityType)
                <x-ui.nav-link
                    :href="route('entities.index', [$currentCampaign, $entityType->slug()])"
                    :active="request()->routeIs('entities.*') && request()->route('type') === $entityType->slug()"
                    :icon="$entityType->icon()"
                    :count="$entityCounts[$entityType->value] ?? 0"
                >{{ $entityType->plural() }}</x-ui.nav-link>
            @endforeach
        </div>
    @else
        <div>
            <p class="eyebrow px-2 pb-2">Library</p>
            <x-ui.nav-link :href="route('campaigns.index')" :active="request()->routeIs('campaigns.index')" icon="book-open">Campaigns</x-ui.nav-link>
        </div>
    @endif
</nav>

<div class="border-t border-line px-4 py-3 text-xs text-ink-faint">
    @if ($currentCampaign)
        <x-ui.badge :variant="$currentRole?->isDm() ? 'accent' : 'neutral'">{{ $currentRole?->label() }}</x-ui.badge>
    @else
        demgem · open source campaign manager
    @endif
</div>
