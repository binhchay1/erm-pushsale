<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReconciliationStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Repositories\ShippingWebhookEventRepository;
use App\Services\Shipping\ShippingReconciliationService;
use App\Services\Reporting\ReportSnapshotStore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingReconciliationController extends Controller
{
    /** Nhóm trạng thái → trạng thái dòng tiền để đối soát. */
    private const MONEY_STATE = [
        'paid' => 'paid',
        'delivered' => 'pending',
        'delivery_complete' => 'pending',
        'returned' => 'returned',
        'returning' => 'returned',
        'refund' => 'returned',
        'cancel_waybill' => 'cancelled',
        'cancel_closing' => 'cancelled',
        'cannot_deliver' => 'cancelled',
        'cannot_pickup' => 'cancelled',
    ];

    public function __invoke(Request $request, ShippingReconciliationService $service): Response
    {
        $filter = [
            'tab' => $request->input('tab', 'overview'),
            'period_type' => $request->input('period_type', 'month'),
            'month' => $request->input('month', now()->format('Y-m')),
            'quarter' => $request->integer('quarter') ?: (int) ceil(now()->month / 3),
            'year' => $request->integer('year') ?: now()->year,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'provider' => $request->input('provider') ?: null,
            'recon_status' => $request->input('recon_status') ?: null,
            'delivery_status' => $request->input('delivery_status') ?: null,
            'search' => $request->input('search') ?: null,
        ];

        [$from, $to] = $service->resolveRange($filter);
        $webhookStats = app(ShippingWebhookEventRepository::class)->todayStats();
        $webhookIssues = app(ShippingWebhookEventRepository::class)
            ->latestIssues(100)
            ->map(fn (ShippingWebhookEvent $event) => [
                'id' => $event->id,
                'received_at' => $event->received_at?->format('d/m/Y H:i') ?? $event->created_at?->format('d/m/Y H:i'),
                'provider' => $event->provider,
                'tracking_number' => $event->tracking_number,
                'partner_order_code' => $event->partner_order_code,
                'order_code' => $event->order?->order_code,
                'delivery_status' => $event->order?->delivery_status,
                'is_cod_mismatch' => (bool) $event->is_cod_mismatch,
                'partner_cod' => $event->partner_cod,
                'system_cod' => $event->system_cod,
                'note' => $event->note,
            ])
            ->values()
            ->all();

        $snapshot = app(ReportSnapshotStore::class)->rememberPayload(
            'shipping-reconciliation',
            $request->user(),
            $filter,
            $from,
            $to,
            'delivery_update_date',
            function () use ($filter, $service): array {
                $orders = $filter['tab'] === 'unmatched'
                    ? new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25)
                    : $service->paginate($filter);

                return [
                    'summary' => $service->summary($filter),
                    'statusBreakdown' => $service->statusBreakdown($filter),
                    'returnsByProduct' => $service->returnsByProduct($filter),
                    'unmatchedSettlements' => $service->unmatchedSettlements($filter),
                    'orders' => [
                        'data' => collect($orders->items())->map(fn (Order $o) => $this->presentRow($o))->values()->all(),
                        'meta' => [
                            'current_page' => $orders->currentPage(),
                            'last_page' => $orders->lastPage(),
                            'per_page' => $orders->perPage(),
                            'total' => $orders->total(),
                        ],
                    ],
                ];
            },
            $request->boolean('refresh'),
        );
        $report = $snapshot['data'];

        return Inertia::render('Admin/Shipping/Reconciliation', [
            'summary' => $report['summary'],
            'stats' => $webhookStats,
            'webhookStats' => $webhookStats,
            'webhookIssues' => $webhookIssues,
            'statusBreakdown' => $report['statusBreakdown'],
            'returnsByProduct' => $report['returnsByProduct'],
            'unmatchedSettlements' => $report['unmatchedSettlements'],
            'orders' => $report['orders'],
            'filters' => [
                'tab' => $filter['tab'],
                'period_type' => $filter['period_type'],
                'month' => $filter['month'],
                'quarter' => $filter['quarter'],
                'year' => $filter['year'],
                'date_from' => $filter['date_from'],
                'date_to' => $filter['date_to'],
                'provider' => $filter['provider'],
                'recon_status' => $filter['recon_status'],
                'delivery_status' => $filter['delivery_status'],
                'search' => $filter['search'],
            ],
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'providerOptions' => $this->providerOptions(),
            'syncProviders' => ['viettel_post', 'ghtk'],
            'yearOptions' => range(now()->year, now()->year - 4),
            'reportCache' => ['cachedAt' => $snapshot['cachedAt'], 'fromCache' => $snapshot['fromCache'], 'storage' => $snapshot['storage'], 'isFinal' => $snapshot['isFinal']],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentRow(Order $order): array
    {
        $provider = $order->shipping_provider;
        $codToCollect = (int) $order->amount_to_collect;
        $partnerCod = $order->getAttribute('partner_cod');
        $moneyState = $this->moneyStateFromRecon($order->reconciliation_status, $order->delivery_status);

        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'provider' => $provider,
            'provider_label' => $provider
                ? config("shipping_partners.providers.{$provider}.label", strtoupper($provider))
                : ($order->carrier_name ?: '—'),
            'tracking_number' => $order->tracking_number,
            'product_name' => $order->product?->name ?? '—',
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'closed_at' => $order->closed_at?->format('d/m/Y'),
            'delivery_status' => $order->delivery_status,
            'reconciliation_status' => $order->reconciliation_status,
            'reconciliation_label' => ReconciliationStatus::tryFrom($order->reconciliation_status)?->label()
                ?? $order->reconciliation_status,
            'cod_to_collect' => $codToCollect,
            'settled_cod' => (int) $order->settled_cod_amount,
            'partner_cod' => $partnerCod !== null ? (int) $partnerCod : null,
            'has_callback' => (bool) $order->getAttribute('has_callback'),
            'cod_gap' => $partnerCod !== null ? (int) $partnerCod - $codToCollect : null,
            'money_state' => $moneyState,
            'total' => (int) $order->total,
        ];
    }

    private function moneyStateFromRecon(string $recon, string $delivery): string
    {
        return match ($recon) {
            ReconciliationStatus::Settled->value, ReconciliationStatus::Reconciled->value => 'paid',
            ReconciliationStatus::ShortPaid->value, ReconciliationStatus::MissingSettlement->value => 'pending',
            ReconciliationStatus::Returned->value => 'returned',
            ReconciliationStatus::Mismatch->value, ReconciliationStatus::OverPaid->value => 'mismatch',
            default => self::MONEY_STATE[$delivery] ?? 'transit',
        };
    }

    /** @return list<array{value:string, label:string}> */
    private function providerOptions(): array
    {
        return collect(config('shipping_partners.providers', []))
            ->map(fn ($cfg, $key) => [
                'value' => (string) $key,
                'label' => (string) ($cfg['label'] ?? strtoupper((string) $key)),
            ])
            ->values()
            ->all();
    }
}
