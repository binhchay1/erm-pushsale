<?php

namespace App\Services\Shipping\Gateways\NetShip;

use App\Services\Orders\OrderTracePublisher;
use Throwable;

/**
 * Đẩy response NetShip sang queue hành trình. Không ghi database trên request tạo đơn.
 */
class ShippingGatewayTraceRecorder
{
    public function __construct(private readonly OrderTracePublisher $traces) {}

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
            $payload = $raw !== [] ? $raw : ($response['data'] ?? null);

            $this->traces->publish([
                'stage' => 'gateway_response',
                'source' => 'netship',
                'action' => $action,
                'order_id' => $orderId,
                'order_code' => $customer,
                'external_code' => $external,
                'partner_order_code' => $customer,
                'gateway_order_id' => $gatewayId,
                'lookup_code' => $lookup,
                'status_code' => isset($response['http_status']) ? (string) $response['http_status'] : null,
                'http_status' => $response['http_status'] ?? null,
                'success' => (bool) ($response['success'] ?? false),
                'summary' => $action,
                'payload' => is_array($payload) ? $payload : null,
                'gateway_log' => true,
                'dedupe_key' => substr(hash('sha256', implode('|', [
                    $action,
                    (string) $orderId,
                    (string) $external,
                    (string) $gatewayId,
                    (string) ($response['http_status'] ?? ''),
                    json_encode($payload),
                ])), 0, 40),
            ]);
        } catch (Throwable) {
            // Vết truy không được làm fail tạo đơn.
        }
    }

    public function attachShipment(int $orderId, int $shipmentId, string $externalCode): void
    {
        try {
            $this->traces->publish([
                'stage' => 'shipment_link',
                'order_id' => $orderId,
                'shipment_id' => $shipmentId,
                'external_code' => $externalCode !== '' ? $externalCode : null,
                'dedupe_key' => substr('link:'.$orderId.':'.$shipmentId, 0, 40),
            ]);
        } catch (Throwable) {
            // Gắn shipment chỉ để tra cứu, không chặn vận đơn đã tạo.
        }
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
