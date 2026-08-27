<?php

namespace App\Services\Shipping\Gateways\NetShip;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Services\Shipping\Support\PartnerCredentialResolver;

/**
 * Quyết định khi nào đi NetShip thay vì driver direct.
 *
 * Ưu tiên: ĐVVC đã setup credential trên UI → direct.
 * Không sẵn sàng + nằm trong routed_providers + NetShip ready → proxy.
 */
final class NetShipGateway
{
    public function __construct(
        private readonly PartnerCredentialResolver $credentials,
        private readonly NetShipApiClient $client,
        private readonly NetShipAddressResolver $addresses,
    ) {}

    public function isReady(): bool
    {
        return $this->credentials->isReady('netship');
    }

    /** @return array<string, string> provider => NetShip carrier code */
    public function routedProviders(): array
    {
        $map = config('shipping_partners.providers.netship.routed_providers', []);

        return is_array($map) ? array_map('strval', $map) : [];
    }

    public function canProxy(string $businessProvider): bool
    {
        if ($businessProvider === 'manual' || $businessProvider === 'netship') {
            return false;
        }

        return $this->isReady() && array_key_exists($businessProvider, $this->routedProviders());
    }

    public function carrierCodeFor(string $businessProvider): ?string
    {
        $code = $this->routedProviders()[$businessProvider] ?? null;

        return filled($code) ? (string) $code : null;
    }

    public function proxyFor(string $businessProvider): ShippingCarrierInterface
    {
        $code = $this->carrierCodeFor($businessProvider);
        if ($code === null) {
            throw new \InvalidArgumentException("NetShip không map được provider [{$businessProvider}].");
        }

        return new NetShipProxyCarrier(
            $businessProvider,
            $code,
            $this->client,
            $this->addresses,
            $this->credentials,
        );
    }

    public function shipmentUsedGateway(?\App\Models\Shipment $shipment): bool
    {
        if (! $shipment) {
            return false;
        }

        $response = is_array($shipment->response_payload) ? $shipment->response_payload : [];
        $request = is_array($shipment->request_payload) ? $shipment->request_payload : [];

        return ($response['gateway'] ?? null) === 'netship'
            || ($request['gateway'] ?? null) === 'netship'
            || filled($response['netship_order_id'] ?? null);
    }
}
