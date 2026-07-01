<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\User;

class CeoReportService
{
    public function __construct(
        private readonly ReportQueryService $queries,
    ) {}

    /** @return array<string, mixed> */
    public function build(ReportFilterData $filter, ?User $viewer = null): array
    {
        $viewer ??= User::query()->where('role', UserRole::Admin)->firstOrFail();
        $orders = $this->queries->orders($viewer, $filter)->with('items');

        $statusSummary = [];
        foreach (DeliveryStatus::ceoSummaryMap() as $key => $status) {
            $statusSummary[$key] = (clone $orders)->where('delivery_status', $status->value)->count();
        }

        $saleRows = User::query()
            ->where('role', UserRole::Sales)
            ->get()
            ->map(function (User $user, int $index) use ($orders) {
                $mine = (clone $orders)->where('sale_user_id', $user->id)->get();
                $newCustomers = $mine->where('is_returning_customer', false);
                $oldCustomers = $mine->where('is_returning_customer', true);
                $newContact = (int) $newCustomers->sum('contact_count');
                $oldContact = (int) $oldCustomers->sum('contact_count');
                $newClosed = $newCustomers->count();
                $oldClosed = $oldCustomers->count();
                $totalRev = (int) $mine->sum(fn ($o) => $o->netRevenue());
                $kpi = (float) ($user->id === 1 ? 50_000_000 : 30_000_000);

                return [
                    'stt' => $index + 1,
                    'saleStaffId' => (string) $user->id,
                    'saleStaffName' => $user->name,
                    'saleUsername' => strstr($user->email, '@', true) ?: $user->email,
                    'newContact' => $newContact,
                    'newClosed' => $newClosed,
                    'newCloseRate' => $newContact > 0 ? round($newClosed / $newContact * 100, 1) : 0,
                    'newProductQty' => (int) $newCustomers->sum(fn ($o) => $o->items->sum('quantity')),
                    'newEstRevenue' => (int) $newCustomers->sum(fn ($o) => $o->netRevenue()),
                    'oldContact' => $oldContact,
                    'oldClosed' => $oldClosed,
                    'oldCloseRate' => $oldContact > 0 ? round($oldClosed / $oldContact * 100, 1) : 0,
                    'oldProductQty' => (int) $oldCustomers->sum(fn ($o) => $o->items->sum('quantity')),
                    'oldEstRevenue' => (int) $oldCustomers->sum(fn ($o) => $o->netRevenue()),
                    'totalEstRevenue' => $totalRev,
                    'codFee' => (int) $mine->sum('cod_fee'),
                    'codSupport' => (int) $mine->sum('cod_support'),
                    'bankTransfer' => (int) $mine->sum('discount'),
                    'deposit' => (int) $mine->sum('deposit'),
                    'salesKpi' => $kpi,
                    'achievementRate' => $kpi > 0 ? round($totalRev / $kpi * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();

        $marketingRows = MarketingSource::query()
            ->whereNull('parent_id')
            ->with('children')
            ->get()
            ->map(function (MarketingSource $source, int $index) use ($orders) {
                $sourceOrders = (clone $orders)->where('marketing_source_id', $source->id)->get();
                $budget = $source->budget;
                $contacts = max($source->contacts, (int) $sourceOrders->sum('contact_count'));
                $newRev = (int) $sourceOrders->where('is_returning_customer', false)->sum(fn ($o) => $o->netRevenue());
                $totalRev = (int) $sourceOrders->sum(fn ($o) => $o->netRevenue());

                return [
                    'stt' => $index + 1,
                    'marketerId' => (string) $source->id,
                    'marketerName' => $source->name,
                    'budget' => $budget,
                    'contactPrice' => $contacts > 0 ? (int) round($budget / $contacts) : 0,
                    'budgetRevenueRatioNew' => $newRev > 0 ? round($budget / $newRev * 100, 1) : 0,
                    'budgetRevenueRatioTotal' => $totalRev > 0 ? round($budget / $totalRev * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();

        return [
            'statusSummary' => $statusSummary,
            'saleRows' => $saleRows,
            'marketingRows' => $marketingRows,
        ];
    }
}
