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
    </head>
    <body class="min-h-dvh">
        <div class="relative flex min-h-dvh items-center justify-center overflow-hidden px-4 py-10">
            <x-ui.logo class="pointer-events-none absolute -top-32 -right-32 size-[40rem] text-ember opacity-[0.05]" />

            <div class="relative w-full max-w-sm animate-rise">
                <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-2.5">
                    <x-ui.logo class="size-7 text-ember" />
                    <span class="font-display text-2xl font-semibold tracking-tight">demgem</span>
                </a>

                <div class="rounded-xl border border-line bg-panel p-6 shadow-2xl shadow-black/30">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <p class="mt-6 text-center text-sm text-ink-muted">{{ $footer }}</p>
                @endisset
            </div>
        </div>
    </body>
</html>
