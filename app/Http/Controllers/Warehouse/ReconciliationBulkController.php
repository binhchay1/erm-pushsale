<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationImportBatch;
use App\Services\FilterOptionsService;
use App\Services\Warehouse\ReconciliationBulkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReconciliationBulkController extends Controller
{
    public function meta(Request $request, ReconciliationBulkService $service, FilterOptionsService $filters): JsonResponse
    {
        $options = $filters->forReports($request->user());

        return response()->json([
            'reconciliationStatuses' => $options['reconciliationStatuses'] ?? $service->statusCatalog(),
            'codeTypes' => [
                ['value' => 'MHT', 'label' => 'Mã đơn PUSHSALE'],
                ['value' => 'MGV', 'label' => 'Mã vận đơn'],
            ],
            'template_headers' => $service->templateHeaders(),
            'max_excel_rows' => ReconciliationBulkService::MAX_EXCEL_ROWS,
        ]);
    }

    public function inspect(Request $request, ReconciliationBulkService $service): JsonResponse
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

    public function updateByCodes(Request $request, ReconciliationBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'string', 'min:1'],
            'code_type' => ['required', Rule::in(['MHT', 'MGV'])],
            'is_ghtk' => ['sometimes', 'boolean'],
            'reconciliation_status' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($service->updateByCodes(
            $data['codes'],
            $data['reconciliation_status'],
            $data['code_type'],
            (bool) ($data['is_ghtk'] ?? false),
            $data['note'] ?? null,
            $request->user(),
        ));
    }

    public function template(ReconciliationBulkService $service): StreamedResponse
    {
        return $service->downloadTemplate();
    }

    public function upload(Request $request, ReconciliationBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'is_ghtk' => ['sometimes', 'boolean'],
            'match_total' => ['sometimes', 'boolean'],
            'match_cod' => ['sometimes', 'boolean'],
            'update_dsnb_if_match' => ['sometimes', 'boolean'],
        ]);

        return response()->json($service->uploadExcel(
            $data['file'],
            $request->boolean('is_ghtk'),
            $request->user(),
            [
                'match_total' => $request->boolean('match_total'),
                'match_cod' => $request->boolean('match_cod'),
                'update_dsnb_if_match' => $request->boolean('update_dsnb_if_match'),
            ],
        ));
    }

    public function apply(Request $request, ReconciliationImportBatch $batch, ReconciliationBulkService $service): JsonResponse
    {
        $data = $request->validate([
            'match_total' => ['sometimes', 'boolean'],
            'match_cod' => ['sometimes', 'boolean'],
            'update_dsnb_if_match' => ['sometimes', 'boolean'],
        ]);

        return response()->json($service->applyBatch($batch, $request->user(), [
            'match_total' => array_key_exists('match_total', $data) ? (bool) $data['match_total'] : null,
            'match_cod' => array_key_exists('match_cod', $data) ? (bool) $data['match_cod'] : null,
            'update_dsnb_if_match' => array_key_exists('update_dsnb_if_match', $data) ? (bool) $data['update_dsnb_if_match'] : null,
        ]));
    }

    public function clear(ReconciliationImportBatch $batch, ReconciliationBulkService $service): JsonResponse
    {
        $service->clearBatch($batch);

        return response()->json(['message' => 'Đã xóa dữ liệu upload.']);
    }

    public function history(Request $request, ReconciliationBulkService $service): JsonResponse
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
