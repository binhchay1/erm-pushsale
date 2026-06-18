<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingPartnerRequest;
use App\Services\Shipping\ShippingPartnerConfigService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShippingPartnersController extends Controller
{
    public function index(ShippingPartnerConfigService $service): Response
    {
        return Inertia::render('Admin/ShippingPartners/Index', [
            'providers' => $service->listForAdmin(),
        ]);
    }

    public function update(
        UpdateShippingPartnerRequest $request,
        string $provider,
        ShippingPartnerConfigService $service,
    ): RedirectResponse {
        if (! array_key_exists($provider, config('shipping_partners.providers', []))) {
            abort(404);
        }

        $service->update($provider, $request->validated());

        return back()->with('success', __('messages.shipping_saved'));
    }
}
