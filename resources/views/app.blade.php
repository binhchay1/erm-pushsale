<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php($seo = app(\App\Support\Seo::class)->resolved())
        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @if(!empty($seo['keywords']))<meta name="keywords" content="{{ $seo['keywords'] }}">@endif
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta name="theme-color" content="#2563eb">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $seo['site_name'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta property="og:locale" content="{{ $seo['locale'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $seo['image'] }}">
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $seo['site_name'],
                'url' => url('/'),
                'logo' => url('/favicon.svg'),
                'description' => $seo['description'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
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
