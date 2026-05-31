<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'ERM SaleOps') }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <script>
            (function () {
                try {
                    const themes = @json(config('saleops.themes'));
                    const root = document.documentElement;
                    const appearance = localStorage.getItem('saleops-appearance');
                    const themeId = localStorage.getItem('saleops-theme');
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (appearance === 'dark' || (appearance === 'system' && prefersDark)) {
                        root.classList.add('dark');
                    } else if (appearance === 'light') {
                        root.classList.remove('dark');
                    }

                    if (themeId && themes[themeId]) {
                        const theme = themes[themeId];
                        root.dataset.theme = themeId;
                        root.style.setProperty('--primary', theme.primary);
                        root.style.setProperty('--primary-foreground', theme.primary_foreground);
                        root.style.setProperty('--sidebar-primary', theme.primary);
                        root.style.setProperty('--ring', theme.primary);

                        if (theme.chart && theme.chart.length) {
                            root.style.setProperty('--chart-1', theme.chart[0]);
                            root.style.setProperty('--chart-2', theme.chart[1] ?? theme.chart[0]);
                            root.style.setProperty('--chart-3', theme.chart[2] ?? theme.chart[0]);
                        }
                    }
                } catch (e) {}
            })();
        </script>
        @inertia
    </body>
</html>
