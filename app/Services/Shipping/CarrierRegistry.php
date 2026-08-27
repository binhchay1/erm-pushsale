<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
use App\Models\Shipment;
use App\Services\Shipping\Gateways\NetShip\NetShipGateway;
use App\Services\Shipping\Support\PartnerCredentialResolver;
use InvalidArgumentException;

class CarrierRegistry
{
    /** @var array<string, ShippingCarrierInterface> */
    private array $carriers = [];

    /** @param  iterable<ShippingCarrierInterface>  $carriers */
    public function __construct(
        iterable $carriers,
        private readonly PartnerCredentialResolver $credentials,
        private readonly NetShipGateway $netShipGateway,
    ) {
        foreach ($carriers as $carrier) {
            // Gateway NetShip không đăng ký như ĐVVC chọn trên đơn.
            if ($carrier->provider() === 'netship') {
                continue;
            }
            $this->carriers[$carrier->provider()] = $carrier;
        }
    }

    /**
     * Resolve ĐVVC nghiệp vụ: ưu tiên driver direct nếu đã setup;
     * không thì proxy NetShip nếu gateway sẵn sàng và provider được map.
     * Đơn đã tạo qua NetShip → luôn giữ proxy khi sync/hủy.
     */
    public function get(string $provider, ?Shipment $shipment = null): ShippingCarrierInterface
    {
        if ($provider === 'netship') {
            throw new InvalidArgumentException('NetShip là cổng trung gian, không phải ĐVVC trên đơn.');
        }

        if (! isset($this->carriers[$provider])) {
            throw new InvalidArgumentException("Carrier [{$provider}] chưa được đăng ký.");
        }

        $direct = $this->carriers[$provider];

        if ($provider === 'manual') {
            return $direct;
        }

        if ($this->netShipGateway->shipmentUsedGateway($shipment) && $this->netShipGateway->canProxy($provider)) {
            return $this->netShipGateway->proxyFor($provider);
        }

        if ($direct->isReady()) {
            return $direct;
        }

        if ($this->netShipGateway->canProxy($provider)) {
            return $this->netShipGateway->proxyFor($provider);
        }

        return $direct;
    }

    public function has(string $provider): bool
    {
        if ($provider === 'netship') {
            return false;
        }

        return isset($this->carriers[$provider]);
    }

    /** @return list<string> */
    public function providers(): array
    {
        return array_keys($this->carriers);
    }

    /** @return list<string> */
    public function readyProviders(): array
    {
        return collect($this->carriers)
            ->filter(function (ShippingCarrierInterface $c, string $key): bool {
                if ($c->isReady()) {
                    return true;
                }

                return $this->netShipGateway->canProxy($key);
            })
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<array{provider: string, label: string, ready: bool, via_netship: bool}> */
    public function summary(): array
    {
        return collect($this->carriers)
            ->map(function (ShippingCarrierInterface $c, string $key): array {
                $directReady = $c->isReady();
                $viaNetship = ! $directReady && $this->netShipGateway->canProxy($key);

                return [
                    'provider' => $key,
                    'label' => $c->label(),
                    'ready' => $directReady || $viaNetship,
                    'via_netship' => $viaNetship,
                ];
            })
            ->values()
            ->all();
    }

    public function resolveForOrder(?string $preferred = null): ?ShippingCarrierInterface
    {
        if ($preferred && $this->has($preferred)) {
            $carrier = $this->get($preferred);
            if ($carrier->isReady()) {
                return $carrier;
            }
        }

        foreach ($this->readyProviders() as $provider) {
            return $this->get($provider);
        }

        return null;
    }

    public function netShipGateway(): NetShipGateway
    {
        return $this->netShipGateway;
    }
}
