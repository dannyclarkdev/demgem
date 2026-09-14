<x-layouts.guest title="Invite only">
    <h1 class="font-display text-xl font-semibold">demgem is invite only</h1>
    <p class="mt-1 text-sm text-ink-muted">An account comes with an invite. Ask your GM for the campaign's invite link, open it, and create your account from there.</p>

    <x-ui.button :href="route('login')" class="mt-6 w-full">Log in</x-ui.button>

    <x-slot:footer>Already have an invite link? Open it again; it brings you here with the door unlocked.</x-slot:footer>
</x-layouts.guest>
