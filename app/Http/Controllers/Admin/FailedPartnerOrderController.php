<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FailedPartnerOrder;
use Illuminate\Http\RedirectResponse;

class FailedPartnerOrderController extends Controller
{
    public function destroy(FailedPartnerOrder $failedPartnerOrder): RedirectResponse
    {
        $failedPartnerOrder->delete();

        return back()->with('success', __('messages.failed_order_deleted'));
    }
}
