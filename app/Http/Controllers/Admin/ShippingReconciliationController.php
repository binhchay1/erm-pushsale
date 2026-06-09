<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingWebhookEvent;
use App\Repositories\ShippingWebhookEventRepository;
use Inertia\Inertia;
use Inertia\Response;

class ShippingReconciliationController extends Controller
{
    public function __invoke(ShippingWebhookEventRepository $events): Response
    {
        $stats = $events->todayStats();

        $issues = $events->latestIssues()
            ->map(fn (ShippingWebhookEvent $event) => [
                'id' => $event->id,
                'provider' => $event->provider,
                'tracking_number' => $event->tracking_number,
                'partner_order_code' => $event->partner_order_code,
                'raw_status' => $event->raw_status,
                'mapped_status' => $event->mapped_status,
                'partner_cod' => $event->partner_cod,
                'system_cod' => $event->system_cod,
                'is_cod_mismatch' => $event->is_cod_mismatch,
                'result' => $event->result,
                'note' => $event->note,
                'order_code' => $event->order?->order_code,
                'received_at' => $event->received_at?->format('d/m/Y H:i:s'),
            ])
            ->values();

        return Inertia::render('Admin/Shipping/Reconciliation', [
            'stats' => $stats,
            'issues' => $issues,
        ]);
    }
}
