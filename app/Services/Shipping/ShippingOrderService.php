<?php

namespace App\Services\Shipping;

use App\Data\ReportFilterData;
use App\Models\Order;
use App\Models\ShippingApiLog;
use App\Services\Operations\OrderOperationPresenter;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShippingOrderService
{
    public function __construct(
        private readonly CarrierRegistry $registry,
        private readonly PartnerCredentialResolver $credentials,
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
        $order->load(['items', 'warehouse', 'saleUser', 'shipments', 'shippingApiLogs' => fn ($q) => $q->latest('id')->limit(30)]);

        $provider = $order->shipping_provider ?? $order->shipments->sortByDesc('id')->first()?->provider;
        $shipment = $provider
            ? $order->shipments->firstWhere('provider', $provider)
            : $order->shipments->first();

        return [
            'order' => OrderOperationPresenter::toArray($order),
            'shipment' => $shipment ? $this->presentShipment($shipment) : null,
            'shipments' => $order->shipments->map(fn ($s) => $this->presentShipment($s))->values()->all(),
            'apiLogs' => $order->shippingApiLogs->map(fn (ShippingApiLog $log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'action' => $log->action,
                'method' => $log->method,
                'endpoint' => $log->endpoint,
                'httpStatus' => $log->http_status,
                'success' => $log->success,
                'message' => $log->message,
                'logId' => $log->log_id,
                'createdAt' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
            'carriers' => $this->registry->summary(),
            'activeProvider' => $provider,
            'trackingUrl' => $shipment?->tracking_number && $shipment->provider
                ? $this->credentials->trackingUrl($shipment->provider, $shipment->tracking_number)
                : null,
        ];
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

        return array_merge($base, [
            'shippingProvider' => $provider,
            'shippingProviderLabel' => $providerLabel,
            'shipmentState' => $shipment?->state,
            'shipmentStatus' => $shipment?->status_text,
            'shipmentError' => $shipment?->error_message,
            'carrierFee' => $shipment?->fee,
            'canCreateShipment' => $order->closed_at && ! $shipment?->tracking_number,
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
