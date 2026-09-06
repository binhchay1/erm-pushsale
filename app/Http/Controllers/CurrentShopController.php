<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetCurrentShop;
use App\Models\Shop;
use App\Services\Shops\ShopProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentShopController extends Controller
{
    public function __invoke(Request $request, ShopProvisioningService $shops): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $shopId = (int) $request->validate([
            'shop_id' => ['required', 'integer'],
        ])['shop_id'];

        $shop = Shop::query()
            ->withoutGlobalScopes()
            ->whereKey($shopId)
            ->firstOrFail();

        abort_unless($shops->canAccessShop($user, $shop), 403);

        $request->session()->put(SetCurrentShop::SESSION_KEY, $shop->id);

        if ($request->boolean('remember_default')) {
            $user->forceFill(['default_shop_id' => $shop->id])->save();
        }

        return back();
    }
}
