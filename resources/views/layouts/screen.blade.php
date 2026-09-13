{{--
    The layout for the wall. No sidebar, no header, no search, no flash: the page is
    on a television at the end of the table and the only person who ever touches it
    is the GM, from another screen.

    The theme script and the Echo settings stay, because the page is live.
--}}
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

        <script>window.demgem = @json(['reverb' => config('broadcasting.client')]);</script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh bg-canvas text-ink">
        {{ $slot }}

        @livewireScripts
    </body>
</html>
