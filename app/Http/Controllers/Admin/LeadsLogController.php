<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIngestionStatus;
use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadsLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $platform = $request->query('platform');
        $status = $request->query('status');

        $leads = LeadIngestion::query()
            ->with('order:id,order_code,customer_name,sale_user_id')
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LeadIngestion $lead) => [
                'id' => $lead->id,
                'platform' => $lead->platform,
                'external_id' => $lead->external_id,
                'status' => $lead->status->value,
                'status_label' => $lead->status->label(),
                'customer_name' => $lead->customer_name,
                'customer_phone' => $lead->customer_phone,
                'product_interest' => $lead->product_interest,
                'utm_campaign' => $lead->utm_campaign,
                'order_code' => $lead->order?->order_code,
                'error_message' => $lead->error_message,
                'created_at' => $lead->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'filters' => [
                'platform' => $platform,
                'status' => $status,
            ],
            'platforms' => array_keys(config('integrations.platforms', [])),
            'statuses' => collect(LeadIngestionStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
        ]);
    }
}
