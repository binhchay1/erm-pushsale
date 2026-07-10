<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Services\Leads\LeadSupplementReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadReviewController extends Controller
{
    public function update(
        Request $request,
        LeadIngestion $leadIngestion,
        LeadSupplementReviewService $reviewService,
    ): RedirectResponse {
        $validated = $request->validate([
            'resolution' => ['sometimes', 'string', Rule::in(LeadSupplementReviewService::resolutions())],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewService->resolve(
            $leadIngestion,
            $request->user(),
            $validated['resolution'] ?? LeadSupplementReviewService::ACKNOWLEDGE,
            $validated['note'] ?? null,
        );

        return back()->with('success', __('messages.lead_intake.review_marked'));
    }
}
