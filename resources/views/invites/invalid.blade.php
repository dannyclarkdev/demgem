<x-layouts.guest title="Invite not valid">
    <span class="inline-flex size-10 items-center justify-center rounded-full bg-raised text-ink-muted">
        <x-ui.icon name="lock" class="size-5" />
    </span>
    <h1 class="mt-4 font-display text-2xl font-semibold tracking-tight">This invite is not valid</h1>
    <p class="mt-2 text-sm text-ink-muted">The link may have expired, reached its limit, or been revoked. Ask the game master for a new one.</p>

    @auth
        <x-ui.button :href="route('campaigns.index')" variant="secondary" class="mt-6 w-full">Your campaigns</x-ui.button>
    @else
        <x-ui.button :href="route('login')" variant="secondary" class="mt-6 w-full">Log in</x-ui.button>
    @endauth
</x-layouts.guest>
