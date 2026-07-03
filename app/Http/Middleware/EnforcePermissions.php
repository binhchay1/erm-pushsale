<?php

namespace App\Http\Middleware;

use App\Support\PermissionMap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn ở server theo quyền khu vực. Admin (gồm super admin) tự bypass vì
 * User::allows() luôn true với admin. Super admin bị TenantScope giới hạn ở
 * công ty nội bộ nên không thấy dữ liệu tenant khác.
 */
class EnforcePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $required = PermissionMap::resolve($request->route()?->getName());

        if ($required !== null && ! $user->allows($required[0], $required[1])) {
            abort(403);
        }

        return $next($request);
    }
}
