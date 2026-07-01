<?php

namespace App\Services\Shipping\Settlement;

use App\Contracts\Shipping\CarrierSettlementAdapterInterface;
use App\Services\Shipping\Settlement\Adapters\GhtkSettlementAdapter;
use App\Services\Shipping\Settlement\Adapters\ViettelPostSettlementAdapter;
use InvalidArgumentException;

class CarrierSettlementAdapterRegistry
{
    /** @var array<string, CarrierSettlementAdapterInterface> */
    private array $adapters = [];

    public function __construct(
        ViettelPostSettlementAdapter $viettelPost,
        GhtkSettlementAdapter $ghtk,
    ) {
        foreach ([$viettelPost, $ghtk] as $adapter) {
            $this->adapters[$adapter->provider()] = $adapter;
        }
    }

    public function get(string $provider): CarrierSettlementAdapterInterface
    {
        if (! isset($this->adapters[$provider])) {
            throw new InvalidArgumentException("Settlement adapter not found for [{$provider}].");
        }

        return $this->adapters[$provider];
    }

    /** @return list<string> */
    public function providers(): array
    {
        return array_keys($this->adapters);
    }
}
