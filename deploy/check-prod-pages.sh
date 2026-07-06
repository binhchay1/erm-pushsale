#!/usr/bin/env bash
# Kiểm tra render backend các trang chính (actingAs từng role).
set -euo pipefail
cd /var/www/erm-pushsale

php artisan tinker --execute="
\$checks = [];
\$roles = [
  'admin' => ['/admin/dashboard', '/admin/leads', '/admin/system-monitor', '/admin/marketing/dashboard'],
  'marketing' => ['/marketing/dashboard', '/marketing/leads', '/marketing/workspace', '/marketing/campaigns'],
  'sales' => ['/sales/dashboard', '/sales/workspace', '/sales/customers'],
  'allocator' => ['/allocator/dashboard', '/allocator/workspace'],
];
foreach (\$roles as \$role => \$paths) {
  \$user = App\Models\User::query()->where('role', \$role)->where('is_active', true)->first();
  if (!\$user) { \$checks[] = 'skip_' . \$role; continue; }
  foreach (\$paths as \$path) {
    try {
      \$req = Illuminate\Http\Request::create(\$path, 'GET');
      \$req->setUserResolver(fn () => \$user);
      Illuminate\Support\Facades\Auth::login(\$user);
      \$route = app('router')->getRoutes()->match(\$req);
      \$req->setRouteResolver(fn () => \$route);
      \$action = \$route->getAction('controller');
      if (!\$action || !str_contains(\$action, '@')) {
        \$checks[] = 'fail:' . \$role . \$path . '=no_action';
        continue;
      }
      [\$class, \$method] = explode('@', \$action, 2);
      \$ctrl = app(\$class);
      \$params = ['request' => \$req];
      foreach (\$route->parameterNames() as \$name) {
        \$val = \$route->parameter(\$name);
        if (\$val !== null) {
          \$params[\$name] = \$val;
        }
      }
      \$resp = app()->call([\$ctrl, \$method], \$params);
      \$ok = \$resp instanceof \Inertia\Response
        || \$resp instanceof Illuminate\Http\Response
        || \$resp instanceof Illuminate\Http\RedirectResponse;
      \$checks[] = (\$ok ? 'pass' : 'fail') . ':' . \$role . \$path;
    } catch (Throwable \$e) {
      \$checks[] = 'fail:' . \$role . \$path . '=' . str_replace([\"\\n\", \"\\r\"], ' ', \$e->getMessage());
    }
  }
}
foreach (\$checks as \$c) {
  echo \$c . PHP_EOL;
}
"
