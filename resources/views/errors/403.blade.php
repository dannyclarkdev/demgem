<x-layouts.guest title="Not allowed">
    <p class="eyebrow">403</p>
    <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">Not allowed</h1>
    <p class="mt-2 text-sm text-ink-muted">You do not have permission to do that here.</p>
    <x-ui.button :href="url('/')" variant="secondary" class="mt-6 w-full">Back to demgem</x-ui.button>
</x-layouts.guest>
