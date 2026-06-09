<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LeadIngestion;
use App\Repositories\LeadIngestionRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadsLogController extends Controller
{
    public function __invoke(
        Request $request,
        LeadIngestionRepository $leadRepo,
        UserRepository $users,
    ): Response {
        $platform = $request->query('platform');
        $status = $request->query('status');

        $leads = $leadRepo->paginatedLog($platform, $status)
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
            'salesUsers' => collect($users->nameOptionsByRoles([UserRole::Sales]))
                ->map(fn (array $u) => ['id' => (string) $u['id'], 'name' => $u['name']])
                ->all(),
            'allocateUrl' => $request->is('allocator/*')
                ? '/allocator/leads/allocate'
                : '/admin/leads/allocate',
            'deleteUrlPrefix' => $request->is('allocator/*')
                ? '/allocator/leads'
                : '/admin/leads',
            'listUrl' => $request->is('allocator/*')
                ? '/allocator/workspace'
                : '/admin/leads',
            'canDelete' => ! $request->is('allocator/*'),
        ]);
    }
}
