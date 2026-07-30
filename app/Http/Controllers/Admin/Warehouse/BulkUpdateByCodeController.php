<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Services\FilterOptionsService;
use App\Services\Warehouse\BulkUpdateByCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BulkUpdateByCodeController extends Controller
{
    public function index(Request $request, FilterOptionsService $filters): Response
    {
        $options = $filters->forReports($request->user());
        $backUrl = $this->backUrl($request);
        $executeUrl = $this->executeUrl($request);
        $initialCodes = trim((string) $request->query('codes', ''));

        return Inertia::render('Admin/Warehouse/BulkUpdateByCode', [
            'pageTitle' => 'Cập nhật contact theo mã pushsale',
            'activeMenuCode' => $this->menuCode($request),
            'backUrl' => $backUrl,
            'executeUrl' => $executeUrl,
            'initialCodes' => $initialCodes,
            'actions' => collect(BulkUpdateByCodeService::ACTIONS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'filterOptions' => [
                'warehouses' => $options['warehouses'] ?? [],
                'shippingProviders' => $options['shippingProviders'] ?? [],
                'deliveryStatuses' => $options['deliveryStatuses'] ?? [],
                'reconciliationStatuses' => $options['reconciliationStatuses'] ?? [],
                'warehouseCareStatuses' => $options['warehouseCareStatuses'] ?? [],
            ],
        ]);
    }

    public function execute(Request $request, BulkUpdateByCodeService $service): JsonResponse
    {
        $data = $request->validate([
            'code_type' => ['required', Rule::in(['MHT', 'MGV'])],
            'is_ghtk' => ['sometimes', 'boolean'],
            'codes' => ['required', 'string', 'min:1'],
            'action' => ['required', Rule::in(array_keys(BulkUpdateByCodeService::ACTIONS))],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'shipping_provider' => ['nullable', 'string', 'max:100'],
            'shipping_method' => ['nullable', 'string', 'max:100'],
            'shipping_notes' => ['nullable', 'string', 'max:2000'],
            'length_cm' => ['nullable', 'integer', 'min:0', 'max:500'],
            'width_cm' => ['nullable', 'integer', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'integer', 'min:0', 'max:500'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'delivery_status' => ['nullable', 'string', 'max:50'],
            'reconciliation_status' => ['nullable', 'string', 'max:50'],
            'warehouse_care_status' => ['nullable', 'string', 'max:50'],
            'warehouse_care_note' => ['nullable', 'string', 'max:2000'],
            'accounting_note' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $service->execute($data, $request->user());

        return response()->json($result);
    }

    private function backUrl(Request $request): string
    {
        if ($request->routeIs('warehouse.*')) {
            return '/warehouse/workspace';
        }
        if ($request->routeIs('accounting.*')) {
            return '/accounting/workspace';
        }

        return '/admin/warehouse/operations';
    }

    private function executeUrl(Request $request): string
    {
        if ($request->routeIs('warehouse.*')) {
            return '/warehouse/orders/update-by-code';
        }
        if ($request->routeIs('accounting.*')) {
            return '/accounting/orders/update-by-code';
        }

        return '/admin/warehouse/orders/update-by-code';
    }

    private function menuCode(Request $request): string
    {
        if ($request->routeIs('accounting.*')) {
            return '6.1';
        }

        return '5.1';
    }
}
