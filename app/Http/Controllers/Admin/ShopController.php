<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Shops\ShopProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __construct(private readonly ShopProvisioningService $shops) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        $company = $user->company;
        abort_unless($company, 404);

        $shops = Shop::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->withCount('users')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Shop $shop) => [
                ...$shop->toFrontendArray(),
                'users_count' => (int) $shop->users_count,
            ]);

        $users = User::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'default_shop_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role?->value,
                'default_shop_id' => $u->default_shop_id,
            ]);

        return Inertia::render('Admin/Shops/Index', [
            'shops' => $shops,
            'users' => $users,
            'activeMenuCode' => '1.1.3',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        $company = $user->company;
        abort_unless($company, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('shops', 'code')->where('company_id', $company->id)],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('company_id', $company->id)],
        ]);

        $this->shops->createShop(
            $company,
            $data['name'],
            $data['code'] ?? null,
            (bool) ($data['is_default'] ?? false),
            (bool) ($data['is_active'] ?? true),
            $data['user_ids'] ?? [],
        );

        return back()->with('success', __('messages.shops.created'));
    }

    public function update(Request $request, Shop $shop): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        abort_unless((int) $shop->company_id === (int) $user->company_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('shops', 'code')->where('company_id', $user->company_id)->ignore($shop->id)],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('company_id', $user->company_id)],
        ]);

        if ($data['is_default'] ?? false) {
            Shop::query()
                ->withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->where('is_default', true)
                ->whereKeyNot($shop->id)
                ->update(['is_default' => false]);
        }

        $shop->fill([
            'name' => $data['name'],
            'code' => $data['code'] ?: $shop->code,
            'is_default' => (bool) ($data['is_default'] ?? $shop->is_default),
            'is_active' => (bool) ($data['is_active'] ?? $shop->is_active),
        ])->save();

        if (array_key_exists('user_ids', $data)) {
            $shop->users()->sync(array_values(array_unique(array_map('intval', $data['user_ids'] ?? []))));
        }

        return back()->with('success', __('messages.shops.updated'));
    }

    public function destroy(Request $request, Shop $shop): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        abort_unless((int) $shop->company_id === (int) $user->company_id, 404);

        if ($shop->is_default) {
            return back()->with('error', __('messages.shops.cannot_delete_default'));
        }

        if (Order::query()->withoutGlobalScopes()->where('shop_id', $shop->id)->exists()) {
            return back()->with('error', __('messages.shops.cannot_delete_with_orders'));
        }

        $shop->users()->detach();
        $shop->delete();

        return back()->with('success', __('messages.shops.deleted'));
    }
}
