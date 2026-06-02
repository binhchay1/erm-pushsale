<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingCarrierInterface;
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
    ) {
        foreach ($carriers as $carrier) {
            $this->carriers[$carrier->provider()] = $carrier;
        }
    }

    public function get(string $provider): ShippingCarrierInterface
    {
        if (! isset($this->carriers[$provider])) {
            throw new InvalidArgumentException("Carrier [{$provider}] chưa được đăng ký.");
        }

        return $this->carriers[$provider];
    }

    public function has(string $provider): bool
    {
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
            ->filter(fn (ShippingCarrierInterface $c) => $c->isReady())
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<array{provider: string, label: string, ready: bool}> */
    public function summary(): array
    {
        return collect($this->carriers)
            ->map(fn (ShippingCarrierInterface $c) => [
                'provider' => $c->provider(),
                'label' => $c->label(),
                'ready' => $c->isReady(),
            ])
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
}
