<?php

namespace App\Http\Middleware;

use App\Services\Shops\ShopProvisioningService;
use App\Support\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentShop
{
    public const SESSION_KEY = 'current_shop_id';

    public function __construct(
        private readonly TenantManager $tenant,
        private readonly ShopProvisioningService $shops,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Platform admin không gắn company → không có shop context (all / none).
        if ($user->isPlatformAdmin() && $user->company_id === null) {
            $this->tenant->setShop(null);

            return $next($request);
        }

        if (! $user->company_id) {
            $this->tenant->clearShop();

            return $next($request);
        }

        $sessionShopId = $request->session()->get(self::SESSION_KEY);
        $sessionShopId = is_numeric($sessionShopId) ? (int) $sessionShopId : null;

        $shopId = $this->shops->resolveCurrentShopId($user, $sessionShopId);

        if ($shopId !== null) {
            $request->session()->put(self::SESSION_KEY, $shopId);
            $this->tenant->setShop($shopId);
        } else {
            $this->tenant->clearShop();
        }

        return $next($request);
    }
}
