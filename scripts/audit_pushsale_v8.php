<?php

$root = dirname(__DIR__);
$pages = require $root.'/config/pushsale_pages.php';
$routesRegistry = require $root.'/config/pushsale_routes.php';
$merges = require $root.'/config/pushsale_page_merges.php';
$pushsaleRoutes = file_get_contents($root.'/routes/pushsale_pages.php');
$errors = [];

foreach ($pages as $code => $page) {
    $template = ($page['template_alias'] ?? $code).'.html';

    if (! is_file($root.'/public/pushsale-templates/'.$template)) {
        $errors[] = "Missing template: {$code} ({$template})";
    }

    if (! isset($routesRegistry[$code])) {
        $errors[] = "Missing semantic route registry: {$code}";
    }

    if (isset($merges[$code])) {
        $merge = $merges[$code];
        foreach (['controller', 'component', 'route_file'] as $field) {
            if (! isset($merge[$field]) || ! is_file($root.'/'.$merge[$field])) {
                $errors[] = "Missing merged {$field}: {$code}";
            }
        }

        if (isset($merge['route_file'], $merge['route_marker']) && is_file($root.'/'.$merge['route_file'])) {
            $routeContents = file_get_contents($root.'/'.$merge['route_file']);
            if (! str_contains($routeContents, $merge['route_marker'])) {
                $errors[] = "Missing merged route marker: {$code}";
            }
        }

        $uri = ltrim((string) ($merge['uri'] ?? ''), '/');
        $registryUri = 'admin/'.ltrim((string) ($routesRegistry[$code]['uri'] ?? ''), '/');
        if ($uri !== '' && $uri !== $registryUri) {
            $errors[] = "Merged URI mismatch: {$code} ({$uri} != {$registryUri})";
        }

        continue;
    }

    $component = $page['component'] ?? 'Page_'.str_replace('.', '_', $code);
    $controller = 'Page'.str_replace('.', '_', $code).'Controller.php';
    $componentPath = $root.'/resources/js/pages/Pushsale/Pages/'.$component.'.jsx';
    $controllerPath = $root.'/app/Http/Controllers/Admin/Pushsale/Pages/'.$controller;

    if (! is_file($componentPath)) {
        $errors[] = "Missing component: {$code}";
    }
    if (! is_file($controllerPath)) {
        $errors[] = "Missing controller: {$code}";
    }

    $controllerClass = substr($controller, 0, -4);
    if (! str_contains($pushsaleRoutes, $controllerClass.'::class')) {
        $errors[] = "Missing route controller: {$code}";
    }
    if (($page['source'] ?? 'generic') === 'generic') {
        $errors[] = "Generic source: {$code}";
    }
}

foreach ($merges as $code => $merge) {
    if (! isset($pages[$code])) {
        $errors[] = "Merged code not found in page registry: {$code}";
    }
}

$runtimeFiles = [
    $root.'/app/Services/Pushsale/PushsalePageService.php',
    $root.'/app/Services/Pushsale/PushsaleLiveDataService.php',
];
$runtime = '';
foreach ($runtimeFiles as $file) {
    if (is_file($file)) {
        $runtime .= file_get_contents($file);
    }
}
foreach (['legacy_module_records', 'pushsale_module_records', "quota' => max(10", "'wave' => 1", "call_duration_seconds' => 0"] as $forbidden) {
    if (str_contains($runtime, $forbidden)) {
        $errors[] = "Forbidden runtime placeholder: {$forbidden}";
    }
}

$requiredAssets = [
    'public/vendor/adminlte2/bootstrap/css/bootstrap.min.css',
    'public/vendor/adminlte2/dist/css/AdminLTE.min.css',
    'public/vendor/adminlte2/dist/css/skins/skin-blue-light.min.css',
    'public/vendor/adminlte2/plugins/select2/select2.min.css',
    'public/vendor/adminlte2/plugins/datepicker/datepicker3.css',
    'public/vendor/font-awesome/css/font-awesome.min.css',
];
foreach ($requiredAssets as $asset) {
    if (! is_file($root.'/'.$asset)) {
        $errors[] = "Missing AdminLTE asset: {$asset}";
    }
}

$templates = glob($root.'/public/pushsale-templates/*.html') ?: [];
foreach ($templates as $templateFile) {
    $html = file_get_contents($templateFile);
    if (preg_match('/<script\b/i', $html)) {
        $errors[] = 'Executable script remains in template: '.basename($templateFile);
    }
}

printf("Pages: %d\n", count($pages));
printf("Merged existing modules: %d\n", count($merges));
printf("Sanitized templates: %d\n", count($templates));
printf("Errors: %d\n", count($errors));
foreach ($errors as $error) {
    echo "- {$error}\n";
}

exit($errors === [] ? 0 : 1);
