<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use Illuminate\Http\RedirectResponse;

class LeadIngestionController extends Controller
{
    public function destroy(LeadIngestion $leadIngestion): RedirectResponse
    {
        $leadIngestion->delete();

        return back()->with('success', __('messages.lead_deleted'));
    }
}
