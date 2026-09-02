@auth
    <x-ui.dropdown>
        <x-slot:trigger>
            <button type="button" class="flex items-center gap-2 rounded-md py-1 pr-2 pl-1 text-sm text-ink-muted hover:bg-raised hover:text-ink" aria-label="Account menu">
                <x-ui.avatar :name="auth()->user()->name" size="sm" />
                <span class="hidden max-w-32 truncate sm:inline">{{ auth()->user()->name }}</span>
                <x-ui.icon name="chevron-down" class="size-3.5" />
            </button>
        </x-slot:trigger>

        <div class="border-b border-line px-3 py-2">
            <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-ink-faint">{{ auth()->user()->email }}</p>
        </div>
        <x-ui.dropdown-item :href="route('profile.edit')" icon="user">Profile</x-ui.dropdown-item>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.dropdown-item icon="log-out">Log out</x-ui.dropdown-item>
        </form>
    </x-ui.dropdown>
@endauth
