<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

        <script>
            (function () {
                var theme = 'dark';
                try {
                    var stored = localStorage.getItem('demgem.theme');
                    if (stored === 'light' || stored === 'dark') theme = stored;
                } catch (e) {}
                document.documentElement.dataset.theme = theme;
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh" x-data="{ nav: false }" @keydown.slash.window="if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && $refs.search) { $event.preventDefault(); $refs.search.focus() }">
        <div class="flex min-h-dvh">
            <div
                x-show="nav"
                x-cloak
                x-transition.opacity
                @click="nav = false"
                class="fixed inset-0 z-30 bg-black/60 md:hidden"
            ></div>

            <aside
                :class="nav ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
                class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-line bg-panel transition-transform duration-200 md:sticky md:top-0 md:h-dvh md:translate-x-0"
            >
                @include('partials.sidebar')
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-canvas/85 px-4 backdrop-blur md:px-6">
                    <button type="button" class="text-ink-muted hover:text-ink md:hidden" @click="nav = true" aria-label="Open navigation">
                        <x-ui.icon name="menu" />
                    </button>

                    @php $headerCampaign = app(\App\Support\CurrentCampaign::class)->get(); @endphp
                    @if ($headerCampaign)
                        <form method="get" action="{{ route('search', $headerCampaign) }}" class="relative hidden w-72 sm:block">
                            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-faint" />
                            <input x-ref="search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search {{ $headerCampaign->name }}" class="ui-input h-8 pr-8 pl-8 text-sm" aria-label="Search the campaign">
                            <x-ui.kbd class="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2">/</x-ui.kbd>
                        </form>
                    @endif

                    <div class="ml-auto flex items-center gap-1">
                        <x-ui.theme-toggle />
                        <x-ui.user-menu />
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 md:px-8 md:py-8">
                    <div class="mx-auto w-full max-w-6xl animate-rise">
                        <x-ui.flash />
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
