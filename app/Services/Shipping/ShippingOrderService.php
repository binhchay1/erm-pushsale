<?php

namespace App\Services\Shipping;

use App\Data\ReportFilterData;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingApiLog;
use App\Services\Operations\OrderOperationPresenter;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShippingOrderService
{
    public function __construct(
        private readonly CarrierRegistry $registry,
        private readonly PartnerCredentialResolver $credentials,
        private readonly ShipmentActionResolver $actions,
    ) {}

    /** @return LengthAwarePaginator<int, Order> */
    public function paginate(ReportFilterData $filter, int $perPage = 20): LengthAwarePaginator
    {
        return Order::query()
            ->with(['saleUser', 'warehouse', 'shipments' => fn ($q) => $q->latest('id')])
            ->whereNotNull('closed_at')
            ->applyReportFilter($filter)
            ->orderByDesc('closed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<string, mixed> */
    public function detail(Order $order): array
    {
        $order->load([
            'items', 'warehouse', 'saleUser', 'shipments',
            'shippingApiLogs' => fn ($q) => $q->latest('id')->limit(50),
        ]);

        $provider = $order->shipping_provider ?? $order->shipments->sortByDesc('id')->first()?->provider;
        $shipment = $provider
            ? $order->shipments->firstWhere('provider', $provider)
            : $order->shipments->first();

        return [
            'order' => OrderOperationPresenter::toArray($order),
            'shipment' => $shipment ? $this->presentShipment($shipment) : null,
            'shipments' => $order->shipments->map(fn ($s) => $this->presentShipment($s))->values()->all(),
            'tracking' => $this->buildTrackingTimeline($order, $shipment),
            'carriers' => $this->registry->summary(),
            'activeProvider' => $provider,
            'actions' => $this->actions->forShipment($shipment, $order),
            'trackingUrl' => $shipment?->tracking_number && $shipment->provider
                ? $this->credentials->trackingUrl($shipment->provider, $shipment->tracking_number)
                : null,
        ];
    }

    /**
     * Build a unified tracking timeline from API log entries (carrier-agnostic).
     *
     * @return list<array{at: string, provider: string, statusText: string, note: ?string, isCurrent: bool}>
     */
    private function buildTrackingTimeline(Order $order, ?Shipment $activeShipment): array
    {
        $events = [];

        // Seed with shipment creation event if exists
        if ($activeShipment?->submitted_at) {
            $events[] = [
                'at' => $activeShipment->submitted_at->toIso8601String(),
                'provider' => $activeShipment->provider,
                'statusText' => __('messages.shipping_actions.waybill_created_status'),
                'note' => $activeShipment->tracking_number ? __('messages.shipping_actions.waybill_code').': '.$activeShipment->tracking_number : null,
                'isCurrent' => false,
            ];
        }

        // Extract status events from API sync logs
        foreach ($order->shippingApiLogs->whereIn('action', ['order_status', 'create_order'])->sortBy('id') as $log) {
            /** @var ShippingApiLog $log */
            if (! $log->success) {
                continue;
            }

            $resp = $log->response_payload ?? [];
            $statusText = $this->extractStatusTextFromPayload($log->provider, $resp);

            if (! filled($statusText)) {
                continue;
            }

            // Deduplicate consecutive identical statuses
            $lastEvent = end($events);
            if ($lastEvent !== false && $lastEvent['provider'] === $log->provider && $lastEvent['statusText'] === $statusText) {
                // Update timestamp only — keep most recent
                $events[array_key_last($events)]['at'] = $log->created_at?->toIso8601String() ?? $lastEvent['at'];

                continue;
            }

            $events[] = [
                'at' => $log->created_at?->toIso8601String(),
                'provider' => $log->provider,
                'statusText' => $statusText,
                'note' => $log->message && $log->message !== $statusText ? $log->message : null,
                'isCurrent' => false,
            ];
        }

        // Mark last event as current
        if ($events !== []) {
            $events[array_key_last($events)]['isCurrent'] = true;
        }

        return $events;
    }

    /**
     * Extract human-readable status text from a carrier API response payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractStatusTextFromPayload(string $provider, array $payload): ?string
    {
        return match ($provider) {
            'ghtk' => $payload['data']['status_text']
                ?? $payload['order']['status_text']
                ?? null,
            'ghn' => $payload['data']['status']
                ?? $payload['data']['StatusName']
                ?? null,
            'viettel_post' => $payload['data']['ORDER_STATUS_NAME']
                ?? $payload['data']['STATUS_NAME']
                ?? $payload['data']['status_name']
                ?? null,
            'jnt' => $payload['data']['status_desc']
                ?? $payload['data']['statusDesc']
                ?? null,
            default => $payload['data']['status_text']
                ?? $payload['data']['status']
                ?? null,
        };
    }

    /** @return array<string, mixed> */
    public function presentRow(Order $order): array
    {
        $base = OrderOperationPresenter::toArray($order);
        $provider = $order->shipping_provider ?? $order->shipments->first()?->provider;
        $shipment = $provider
            ? $order->shipments->firstWhere('provider', $provider)
            : $order->shipments->first();

        $providerLabel = $provider
            ? config("shipping_partners.providers.{$provider}.label", strtoupper($provider))
            : null;

        $shipmentActions = $this->actions->forShipment($shipment, $order);

        return array_merge($base, [
            'shippingProvider' => $provider,
            'shippingProviderLabel' => $providerLabel,
            'shipmentState' => $shipment?->state,
            'shipmentStatus' => $shipment?->status_text,
            'shipmentError' => $shipment?->error_message,
            'carrierFee' => $shipment?->fee,
            'canCreateShipment' => (bool) $order->closed_at && $shipmentActions['canCreate'],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentShipment($shipment): array
    {
        return [
            'id' => $shipment->id,
            'provider' => $shipment->provider,
            'providerLabel' => config("shipping_partners.providers.{$shipment->provider}.label", $shipment->provider),
            'partnerOrderId' => $shipment->partner_order_id,
            'trackingNumber' => $shipment->tracking_number,
            'trackingId' => $shipment->tracking_id,
            'statusId' => $shipment->status_id,
            'statusText' => $shipment->status_text,
            'fee' => $shipment->fee,
            'insuranceFee' => $shipment->insurance_fee,
            'transport' => $shipment->transport,
            'state' => $shipment->state,
            'errorMessage' => $shipment->error_message,
            'submittedAt' => $shipment->submitted_at?->toIso8601String(),
            'lastSyncedAt' => $shipment->last_synced_at?->toIso8601String(),
            'cancelledAt' => $shipment->cancelled_at?->toIso8601String(),
            'requestPayload' => $shipment->request_payload,
            'responsePayload' => $shipment->response_payload,
        ];
    }
}
