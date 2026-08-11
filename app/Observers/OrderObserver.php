<?php

namespace App\Observers;

use App\Models\Order;
use App\Observers\Concerns\DispatchesSqlDailyFacts;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    use DispatchesSqlDailyFacts;

    public function created(Order $order): void
    {
        $this->dispatchAffectedDates($order);
    }

    public function updated(Order $order): void
    {
        $watched = [
            'status',
            'closing_status',
            'delivery_status',
            'warehouse_care_status',
            'operation_result',
            'reconciliation_status',
            'total',
            'subtotal',
            'discount',
            'deposit',
            'amount_to_collect',
            'settled_cod_amount',
            'marketing_source_id',
            'landing_connection_id',
            'landing_connection_source_id',
            'marketer_user_id',
            'sale_user_id',
            'team_id',
            'warehouse_id',
            'created_at',
            'data_arrived_at',
            'closed_at',
            'assigned_at',
        ];

        if (! $order->wasChanged($watched)) {
            return;
        }

        $this->dispatchAffectedDates($order);
    }

    public function deleted(Order $order): void
    {
        $this->dispatchAffectedDates($order);
    }

    private function dispatchAffectedDates(Order $order): void
    {
        $companyId = (int) ($order->company_id ?: $order->getOriginal('company_id'));
        if ($companyId <= 0) {
            return;
        }

        $this->dispatchCurrentAndOriginalDate($order, $companyId, 'created_at');

        $dates = DB::table('lead_ingestions')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($order): void {
                $query->where('order_id', $order->getKey())
                    ->orWhere('related_order_id', $order->getKey());
            })
            ->selectRaw('DATE(created_at) as fact_date')
            ->distinct()
            ->pluck('fact_date');

        foreach ($dates as $date) {
            $this->dispatchSqlFactRefresh($companyId, $date);
        }
    }
}
