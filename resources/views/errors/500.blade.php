<x-layouts.guest title="Something broke">
    <p class="eyebrow">500</p>
    <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">Something broke</h1>
    <p class="mt-2 text-sm text-ink-muted">The server hit an error. Try again in a moment.</p>
    <x-ui.button :href="url('/')" variant="secondary" class="mt-6 w-full">Back to demgem</x-ui.button>
</x-layouts.guest>
