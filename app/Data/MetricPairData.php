<?php

namespace App\Data;

readonly class MetricPairData
{
    public function __construct(
        public int $qty = 0,
        public int $revenue = 0,
    ) {}

    /** @return array{qty: int, revenue: int} */
    public function toArray(): array
    {
        return [
            'qty' => $this->qty,
            'revenue' => $this->revenue,
        ];
    }

    public static function zero(): self
    {
        return new self(0, 0);
    }

    public function add(self $other): self
    {
        return new self(
            $this->qty + $other->qty,
            $this->revenue + $other->revenue,
        );
    }
}
