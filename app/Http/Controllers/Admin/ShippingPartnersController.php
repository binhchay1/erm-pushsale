<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingPartnerRequest;
use App\Services\Shipping\ShippingPartnerConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShippingPartnersController extends Controller
{
    public function index(Request $request, ShippingPartnerConfigService $service): Response
    {
        return Inertia::render('Admin/ShippingPartners/Index', [
            'providers' => $service->listForAdmin(),
            'defaultConfig' => $service->defaultConfig($request->user()?->company_id),
        ]);
    }

    public function update(UpdateShippingPartnerRequest $request, string $provider, ShippingPartnerConfigService $service): RedirectResponse
    {
        abort_unless(array_key_exists($provider, config('shipping_partners.providers', [])), 404);
        $service->update($provider, $request->validated());

        return back()->with('success', __('messages.shipping_saved'));
    }

    public function updateDefault(Request $request, ShippingPartnerConfigService $service): RedirectResponse
    {
        $providers = array_keys(config('shipping_partners.providers', []));
        $validated = $request->validate([
            'provider' => ['required', Rule::in($providers)],
            'method' => ['nullable', 'string', 'max:80'],
        ]);
        $service->updateDefault($request->user()?->company_id, $validated['provider'], $validated['method'] ?? null);
        return back()->with('success', 'Đã lưu đơn vị giao hàng mặc định.');
    }
}
