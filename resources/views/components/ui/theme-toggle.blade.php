<button
    type="button"
    x-data="{ theme: document.documentElement.dataset.theme || 'dark' }"
    @click="theme = theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = theme; try { localStorage.setItem('demgem.theme', theme) } catch (e) {}"
    :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
    {{ $attributes->merge(['class' => 'inline-flex size-9 items-center justify-center rounded-md text-ink-muted hover:bg-raised hover:text-ink']) }}
>
    <span x-show="theme === 'dark'"><x-ui.icon name="sun" class="size-4" /></span>
    <span x-show="theme !== 'dark'" x-cloak><x-ui.icon name="moon" class="size-4" /></span>
</button>
