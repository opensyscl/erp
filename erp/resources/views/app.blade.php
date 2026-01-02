<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="cozy-cream">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- React Scan (dev only) -->
        @if(config('app.debug'))
            <!-- <script crossorigin="anonymous" src="//unpkg.com/react-scan/dist/auto.global.js"></script>-->
        @endif

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Theme initialization (blocking to prevent FOUC) -->
        <script>
            (function() {
                const storageKey = 'app-theme';
                const validThemes = ['default', 'cozzy', 'cozy-cream', 'midnight', 'nord', 'sakura', 'emerald', 'cyber'];
                const stored = localStorage.getItem(storageKey);
                if (stored && validThemes.includes(stored)) {
                    document.documentElement.setAttribute('data-theme', stored);
                }
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=nunito:200,300,400,500,600,700,800,900|fredoka:300,400,500,600,700|manrope:200,300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-Manrope antialiased">
        @inertia
    </body>
</html>

