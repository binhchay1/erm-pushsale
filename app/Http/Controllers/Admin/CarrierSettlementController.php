<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\Settlement\CarrierSettlementApiSyncService;
use App\Services\Shipping\Settlement\CarrierSettlementImportService;
use App\Services\Shipping\ShippingReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CarrierSettlementController extends Controller
{
    public function import(
        Request $request,
        CarrierSettlementImportService $import,
    ): RedirectResponse {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:viettel_post,ghtk,ghn,jnt,spx'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'settlement_code' => ['nullable', 'string', 'max:120'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
        ]);

        $result = $import->importCsv(
            $validated['provider'],
            $request->file('file'),
            $validated['settlement_code'] ?? null,
            isset($validated['period_from']) ? Carbon::parse($validated['period_from']) : null,
            isset($validated['period_to']) ? Carbon::parse($validated['period_to']) : null,
        );

        return back()->with('success', __('messages.settlement.import_success', [
            'matched' => $result['lines_matched'],
            'total' => $result['lines_total'],
        ]));
    }

    public function syncApi(
        Request $request,
        CarrierSettlementApiSyncService $sync,
        ShippingReconciliationService $recon,
    ): RedirectResponse {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:viettel_post,ghtk'],
            'period_type' => ['nullable', 'string', 'in:month,quarter,year,custom'],
            'month' => ['nullable', 'string'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'year' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $filter = [
            'period_type' => $validated['period_type'] ?? 'month',
            'month' => $validated['month'] ?? now()->format('Y-m'),
            'quarter' => $validated['quarter'] ?? (int) ceil(now()->month / 3),
            'year' => $validated['year'] ?? now()->year,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];

        [$from, $to] = $recon->resolveRange($filter);
        $result = $sync->syncProvider($validated['provider'], $from, $to);

        return back()->with('success', __('messages.settlement.sync_success', [
            'matched' => $result['lines_matched'],
            'total' => $result['lines_total'],
        ]));
    }
}
