<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="cozy-cream" data-shell="classic">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- React Scan (dev only) -->
        @if(config('app.debug'))
           <script crossorigin="anonymous" src="//unpkg.com/react-scan/dist/auto.global.js"></script>
        @endif

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Theme initialization (blocking to prevent FOUC) -->
        <script>
            (function() {
                // Theme initialization
                const themeStorageKey = 'app-theme';
                const validThemes = ['default', 'cozzy', 'cozy-cream', 'midnight', 'nord', 'sakura', 'emerald', 'cyber'];
                const storedTheme = localStorage.getItem(themeStorageKey);
                if (storedTheme && validThemes.includes(storedTheme)) {
                    document.documentElement.setAttribute('data-theme', storedTheme);
                }

                // Shell initialization from server (will be updated by Inertia)
                const shellStorageKey = 'app-shell';
                const validShells = ['classic', 'modern', 'minimal', 'dark', 'sidebar'];
                const storedShell = localStorage.getItem(shellStorageKey);
                if (storedShell && validShells.includes(storedShell)) {
                    document.documentElement.setAttribute('data-shell', storedShell);
                }
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=nunito:200,300,400,500,600,700,800,900|fredoka:300,400,500,600,700|manrope:200,300,400,500,600,700,800|inter:200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

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

