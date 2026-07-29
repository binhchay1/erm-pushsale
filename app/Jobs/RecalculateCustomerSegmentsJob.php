<?php

namespace App\Jobs;

use App\Services\Customers\CustomerSegmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateCustomerSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?int $companyId = null,
    ) {}

    public function handle(CustomerSegmentService $segments): void
    {
        $segments->recalculate($this->companyId);
    }
}
