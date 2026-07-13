<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <?php ($seo = app(\App\Support\Seo::class)->resolved()); ?>
        <title inertia><?php echo e($seo['title']); ?></title>
        <meta name="description" content="<?php echo e($seo['description']); ?>">
        <?php if(!empty($seo['keywords'])): ?><meta name="keywords" content="<?php echo e($seo['keywords']); ?>"><?php endif; ?>
        <link rel="canonical" href="<?php echo e($seo['canonical']); ?>">
        <meta name="theme-color" content="#2563eb">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="<?php echo e($seo['site_name']); ?>">
        <meta property="og:title" content="<?php echo e($seo['title']); ?>">
        <meta property="og:description" content="<?php echo e($seo['description']); ?>">
        <meta property="og:url" content="<?php echo e($seo['canonical']); ?>">
        <meta property="og:image" content="<?php echo e($seo['image']); ?>">
        <meta property="og:locale" content="<?php echo e($seo['locale']); ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo e($seo['title']); ?>">
        <meta name="twitter:description" content="<?php echo e($seo['description']); ?>">
        <meta name="twitter:image" content="<?php echo e($seo['image']); ?>">
        <script type="application/ld+json">
            <?php echo json_encode([
                '<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $seo['site_name'],
                'url' => url('/'),
                'logo' => url('/favicon.svg'),
                'description' => $seo['description'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>

        </script>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.jsx']); ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="font-sans antialiased">
        <script>
            (function () {
                try {
                    const themes = <?php echo json_encode(config('saleops.themes'), 15, 512) ?>;
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
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH D:\File of Bình\erm-pushsale\resources\views/app.blade.php ENDPATH**/ ?>