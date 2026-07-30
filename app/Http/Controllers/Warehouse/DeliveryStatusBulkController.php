<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\DeliveryStatusImportBatch;
use App\Services\FilterOptionsService;
use App\Services\Warehouse\DeliveryStatusBulkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryStatusBulkController extends Controller
{
    public function meta(Request $request, DeliveryStatusBulkService $service, FilterOptionsService $filters): JsonResponse
    {
        $options = $filters->forReports($request->user());

        return response()->json([
            'deliveryStatuses' => $options['deliveryStatuses'] ?? $service->statusCatalog(),
            'codeTypes' => [
                ['value' => 'MHT', 'label' => 'Mã đơn PUSHSALE'],
                ['value' => 'MGV', 'label' => 'Mã vận đơn'],
            ],
            'template_headers' => $service->templateHeaders(),
            'max_excel_rows' => DeliveryStatusBulkService::MAX_EXCEL_ROWS,
        ]);
    }

    public function inspect(Request $request, DeliveryStatusBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'string', 'min:1'],
            'code_type' => ['required', Rule::in(['MHT', 'MGV'])],
            'is_ghtk' => ['sometimes', 'boolean'],
        ]);

        return response()->json($service->inspect(
            $data['codes'],
            $data['code_type'],
            (bool) ($data['is_ghtk'] ?? false),
        ));
    }

    public function updateByCodes(Request $request, DeliveryStatusBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'string', 'min:1'],
            'code_type' => ['required', Rule::in(['MHT', 'MGV'])],
            'is_ghtk' => ['sometimes', 'boolean'],
            'delivery_status' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($service->updateByCodes(
            $data['codes'],
            $data['delivery_status'],
            $data['code_type'],
            (bool) ($data['is_ghtk'] ?? false),
            $data['note'] ?? null,
            $request->user(),
        ));
    }

    public function template(DeliveryStatusBulkService $service): StreamedResponse
    {
        return $service->downloadTemplate();
    }

    public function upload(Request $request, DeliveryStatusBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'is_ghtk' => ['sometimes', 'boolean'],
        ]);

        return response()->json($service->uploadExcel(
            $data['file'],
            $request->boolean('is_ghtk'),
            $request->user(),
        ));
    }

    public function apply(Request $request, DeliveryStatusImportBatch $batch, DeliveryStatusBulkService $service): JsonResponse
    {
        return response()->json($service->applyBatch($batch, $request->user()));
    }

    public function clear(DeliveryStatusImportBatch $batch, DeliveryStatusBulkService $service): JsonResponse
    {
        $service->clearBatch($batch);

        return response()->json(['message' => 'Đã xóa dữ liệu upload.']);
    }

    public function history(Request $request, DeliveryStatusBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'batch_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
            'process_status' => ['nullable', 'string', 'max:30'],
            'result_status' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($service->history(
            isset($data['batch_id']) ? (int) $data['batch_id'] : null,
            $data,
            $request->user(),
        ));
    }
}
