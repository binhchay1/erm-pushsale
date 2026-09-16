<?php

namespace App\Http\Controllers\Admin\Pushsale\Warehouse;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class WarehouseVoucherEntryController extends BasePushsalePageController
{
    protected string $pageCode = '5.3.1';

    public function index(Request $request): Response|StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $isExport = $request->boolean('export')
            || in_array((string) $request->query('export'), ['1', 'xls', 'excel', 'csv'], true);
        if ($isExport) {
            return parent::index($request);
        }

        $this->authorizePage($request);
        $schema = $this->pages->schema($this->pageCode);
        $component = (string) ($schema['component'] ?? 'Admin/Warehouse/VoucherEntry');
        $pageRuntimeError = null;
        $filterOptions = [];
        $result = [
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0, 'from' => 0, 'to' => 0],
            'summary' => [],
        ];

        try {
            $filterOptions = $this->pages->filterOptions($this->pageCode);
        } catch (Throwable $exception) {
            report($exception);
            $filterOptions = [];
            $pageRuntimeError = (bool) config('app.debug')
                ? 'Không tải được dữ liệu bộ lọc: '.$exception->getMessage()
                : 'Không tải được dữ liệu bộ lọc.';
        }

        $voucherId = (int) ($request->query('id') ?: $request->query('record') ?: 0);
        $voucher = null;
        if ($voucherId > 0) {
            try {
                /** @var WarehouseVoucher $model */
                $model = $this->resources->find('5.3.1', $voucherId);
                $voucher = $this->resources->serializeWarehouseVoucher($model);
            } catch (Throwable $exception) {
                report($exception);
                $pageRuntimeError = (bool) config('app.debug')
                    ? $exception->getMessage()
                    : 'Không tải được phiếu kho.';
            }
        }

        $defaults = [
            'warehouse_id' => (int) ($request->query('warehouse_id') ?: ($voucher['warehouse_id'] ?? 0)) ?: null,
            'product_id' => (int) ($request->query('product_id') ?: 0) ?: null,
        ];

        return Inertia::render($component, [
            'schema' => array_merge($schema, [
                'form_fields' => $schema['form_fields'] ?? $this->safeFormFields('5.3.1'),
                'dialog_resource_schemas' => [],
            ]),
            'rows' => $voucher['lines'] ?? [],
            'pagination' => $result['meta'],
            'summary' => [
                'voucher_id' => $voucher['id'] ?? null,
                'status' => $voucher['status'] ?? 'draft',
            ],
            'filterOptions' => $filterOptions,
            'routeUrl' => '/'.$request->path(),
            'templateHtml' => '',
            'dialogTemplates' => [],
            'activeMenuCode' => $this->activeMenuCodeFromRequest($request),
            'pageRuntimeError' => $pageRuntimeError,
            'voucher' => $voucher,
            'defaults' => $defaults,
            'canTesterTools' => (bool) ($request->user()?->isAdmin() || $request->user()?->isPlatformAdmin()),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->authorizePage($request);
            $resourceKey = $this->mainResourceKey();
            abort_unless($resourceKey, 405);
            $payload = $this->payload($request);
            /** @var WarehouseVoucher $record */
            $record = $this->resources->create($resourceKey, $payload, $request->user());

            return $this->voucherSavedResponse($request, $record, 201, 'Đã lưu phiếu tạm.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->voucherFailureResponse($request, $exception, 'Không lưu được phiếu kho. Kiểm tra kho, sản phẩm và số lượng rồi thử lại.');
        }
    }

    public function update(Request $request, int $record): RedirectResponse|JsonResponse
    {
        try {
            $this->authorizePage($request);
            $resourceKey = $this->mainResourceKey();
            abort_unless($resourceKey, 405);
            $model = $this->resources->find($resourceKey, $record);
            /** @var WarehouseVoucher $model */
            $model = $this->resources->update($resourceKey, $model, $this->payload($request), $request->user());

            return $this->voucherSavedResponse($request, $model, 200, 'Đã cập nhật phiếu tạm.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->voucherFailureResponse($request, $exception, 'Không cập nhật được phiếu kho. Phiếu có thể đã hoàn thành hoặc dữ liệu không hợp lệ.');
        }
    }

    public function complete(Request $request, int $record): RedirectResponse|JsonResponse
    {
        try {
            $this->authorizePage($request);
            /** @var WarehouseVoucher $voucher */
            $voucher = $this->resources->find('5.3.1', $record);
            /** @var User $actor */
            $actor = $request->user();
            $voucher = $this->resources->completeWarehouseVoucher($voucher, $actor);

            return $this->voucherSavedResponse($request, $voucher, 200, 'Đã hoàn thành phiếu kho.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->voucherFailureResponse($request, $exception, 'Không hoàn thành được phiếu. Kiểm tra tồn kho / trạng thái phiếu rồi thử lại.');
        }
    }

    public function import(Request $request): JsonResponse
    {
        try {
            $this->authorizePage($request);
            $request->validate([
                'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
                'voucher_id' => ['nullable', 'integer', 'exists:warehouse_vouchers,id'],
            ]);

            $lines = $this->resources->parseWarehouseVoucherImport($request->file('file'));
            $voucherId = (int) $request->input('voucher_id', 0);
            $voucherPayload = null;

            if ($voucherId > 0) {
                /** @var WarehouseVoucher $voucher */
                $voucher = $this->resources->find('5.3.1', $voucherId);
                if ($voucher->status === 'confirmed') {
                    throw ValidationException::withMessages([
                        'voucher_id' => 'Phiếu đã hoàn thành không thể import. Hãy tạo phiếu mới hoặc mở phiếu tạm.',
                    ]);
                }
                $payload = [
                    'warehouse_id' => $voucher->warehouse_id,
                    'code' => $voucher->code,
                    'type' => $voucher->type,
                    'document_date' => $voucher->document_date?->toDateString(),
                    'partner' => $voucher->partner,
                    'note' => $voucher->note,
                    'lines' => $lines,
                ];
                /** @var WarehouseVoucher $voucher */
                $voucher = $this->resources->update('5.3.1', $voucher, $payload, $request->user());
                $voucherPayload = $this->resources->serializeWarehouseVoucher($voucher);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Đã import dòng sản phẩm.',
                'lines' => $lines,
                'voucher' => $voucherPayload,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $message = (bool) config('app.debug')
                ? $exception->getMessage()
                : 'Không import được file. Kiểm tra định dạng CSV và dữ liệu sản phẩm.';

            return response()->json(['ok' => false, 'message' => $message], 422);
        }
    }

    public function boostStock(Request $request): JsonResponse
    {
        try {
            $this->authorizePage($request);
            $data = $request->validate([
                'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
                'below_quantity' => ['nullable', 'integer'],
                'add_quantity' => ['required', 'integer', 'min:1'],
            ]);

            /** @var User $actor */
            $actor = $request->user();
            $result = $this->resources->boostWarehouseStock(
                (int) $data['warehouse_id'],
                (int) ($data['below_quantity'] ?? 0),
                (int) $data['add_quantity'],
                $actor,
            );

            $updated = (int) ($result['updated'] ?? 0);
            $message = $updated > 0
                ? "Đã cộng tồn cho {$updated} sản phẩm."
                : 'Không có sản phẩm nào khớp điều kiện để cộng tồn.';

            return response()->json([
                'ok' => true,
                'message' => $message,
                'updated' => $updated,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => (bool) config('app.debug') ? $exception->getMessage() : 'Không cộng được tồn kho. Kiểm tra kho và thử lại.',
            ], 422);
        }
    }

    public function resetStock(Request $request): JsonResponse
    {
        try {
            $this->authorizePage($request);
            $data = $request->validate([
                'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            ]);

            /** @var User $actor */
            $actor = $request->user();
            $result = $this->resources->resetNegativeWarehouseStock((int) $data['warehouse_id'], $actor);
            $updated = (int) ($result['updated'] ?? 0);
            $message = $updated > 0
                ? "Đã reset tồn về 0 cho {$updated} sản phẩm."
                : 'Không có sản phẩm tồn âm để reset.';

            return response()->json([
                'ok' => true,
                'message' => $message,
                'updated' => $updated,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => (bool) config('app.debug') ? $exception->getMessage() : 'Không reset được tồn kho. Kiểm tra kho và thử lại.',
            ], 422);
        }
    }

    private function voucherSavedResponse(Request $request, WarehouseVoucher $voucher, int $status, string $message): RedirectResponse|JsonResponse
    {
        $serialized = $this->resources->serializeWarehouseVoucher($voucher) ?? [];

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message, 'record' => $serialized, 'voucher' => $serialized], $status)
            : redirect()
                ->to('/admin/warehouse/vouchers/entry?id='.$voucher->id)
                ->with('success', $message);
    }

    private function voucherFailureResponse(Request $request, Throwable $exception, string $fallback): RedirectResponse|JsonResponse
    {
        report($exception);
        $message = (bool) config('app.debug') ? $exception->getMessage() : $fallback;

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        throw ValidationException::withMessages(['voucher' => $message]);
    }
}
