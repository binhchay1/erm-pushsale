<?php

namespace App\Services\Shipping\Gateways\NetShip;

use App\Models\Order;
use App\Models\ShippingGatewayTrace;
use App\Support\TenantManager;
use Throwable;

/**
 * Lưu response NetShip để truy lại theo externalCode (cột Mã đơn của file đối soát).
 */
class ShippingGatewayTraceRecorder
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function record(string $action, array $response, ?int $orderId, ?string $lookupCode = null): void
    {
        try {
            $raw = is_array($response['raw'] ?? null) ? $response['raw'] : [];
            $external = $this->firstScalar($raw, 'externalCode');
            $customer = $this->firstScalar($raw, 'customerCode');
            $gatewayId = $this->firstScalar($raw, 'id');
            $lookup = $lookupCode !== null && trim($lookupCode) !== '' ? trim($lookupCode) : $external;

            ShippingGatewayTrace::query()->create([
                'company_id' => $this->companyId($orderId),
                'order_id' => $orderId,
                'gateway' => 'netship',
                'action' => $action,
                'external_code' => $external,
                'partner_order_code' => $customer,
                'gateway_order_id' => $gatewayId,
                'lookup_code' => $lookup,
                'http_status' => $response['http_status'] ?? null,
                'success' => (bool) ($response['success'] ?? false),
                'request_payload' => null,
                'response_payload' => $raw !== [] ? $raw : ($response['data'] ?? null),
            ]);
        } catch (Throwable) {
            // Vết truy không được làm fail tạo đơn.
        }
    }

    public function attachShipment(int $orderId, int $shipmentId, string $externalCode): void
    {
        $trace = ShippingGatewayTrace::query()
            ->where('order_id', $orderId)
            ->where('action', 'create_order')
            ->latest('id')
            ->first();
        if ($trace === null) {
            return;
        }
        $trace->forceFill([
            'shipment_id' => $shipmentId,
            'external_code' => $trace->external_code ?: ($externalCode !== '' ? $externalCode : null),
        ])->save();
    }

    private function companyId(?int $orderId): ?int
    {
        if ($orderId) {
            $companyId = Order::query()->whereKey($orderId)->value('company_id');
            if ($companyId) {
                return (int) $companyId;
            }
        }

        $tenantId = app(TenantManager::class)->id();

        return $tenantId !== null ? (int) $tenantId : null;
    }

    /** @param  array<mixed>  $node */
    private function firstScalar(array $node, string $key): ?string
    {
        $found = null;
        $walk = function (mixed $current) use (&$walk, &$found, $key): void {
            if ($found !== null || ! is_array($current)) {
                return;
            }
            foreach ($current as $name => $value) {
                if (is_string($name) && strcasecmp($name, $key) === 0 && is_scalar($value)) {
                    $text = trim((string) $value);
                    if ($text !== '' && ($key !== 'id' || strlen($text) >= 3)) {
                        $found = $text;

                        return;
                    }
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($node);

        return $found;
    }
}
