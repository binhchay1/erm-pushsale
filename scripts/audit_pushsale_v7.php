<?php

$root = dirname(__DIR__);
$pages = require $root.'/config/pushsale_pages.php';
$routes = file_get_contents($root.'/routes/pushsale_pages.php');
$errors = [];

foreach ($pages as $code => $page) {
    $component = $page['component'] ?? 'Page_'.str_replace('.', '_', $code);
    $controller = 'Page'.str_replace('.', '_', $code).'Controller.php';
    $template = ($page['template_alias'] ?? $code).'.html';
    if (! is_file($root.'/resources/js/pages/Pushsale/Pages/'.$component.'.jsx')) $errors[] = "Missing component: {$code}";
    if (! is_file($root.'/app/Http/Controllers/Admin/Pushsale/Pages/'.$controller)) $errors[] = "Missing controller: {$code}";
    if (! is_file($root.'/public/pushsale-templates/'.$template)) $errors[] = "Missing template: {$code}";
    $controllerClass = substr($controller, 0, -4);
    if (! str_contains($routes, $controllerClass.'::class')) $errors[] = "Missing route controller: {$code}";
    if (($page['source'] ?? 'generic') === 'generic') $errors[] = "Generic source: {$code}";
}

$service = file_get_contents($root.'/app/Services/Pushsale/PushsalePageService.php');
$live = file_get_contents($root.'/app/Services/Pushsale/PushsaleLiveDataService.php');
foreach (['legacy_module_records', 'pushsale_module_records', "quota' => max(10", "'wave' => 1", "call_duration_seconds' => 0"] as $forbidden) {
    if (str_contains($service.$live, $forbidden)) $errors[] = "Forbidden runtime placeholder: {$forbidden}";
}

printf("Pages: %d\n", count($pages));
printf("Errors: %d\n", count($errors));
foreach ($errors as $error) echo "- {$error}\n";
exit($errors === [] ? 0 : 1);
