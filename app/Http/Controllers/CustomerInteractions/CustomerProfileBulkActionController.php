<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Customers\CustomerReallocationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CustomerProfileBulkActionController extends Controller
{
    public function reallocateNow(
        Request $request,
        CustomerReallocationService $reallocation,
    ): JsonResponse|RedirectResponse {
        $this->authorizeFull($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'sale_user_id' => ['required', 'integer'],
            'hide_locked_sales' => ['sometimes', 'boolean'],
            'hide_sales_not_receiving' => ['sometimes', 'boolean'],
            'delete_operation_history' => ['sometimes', 'boolean'],
            'delete_internal_messages' => ['sometimes', 'boolean'],
            'operation_stage' => ['nullable', 'string', 'max:64'],
        ]);

        $orders = $this->selectedOrders($request);
        if ($orders->isEmpty()) {
            return $this->failure($request, __('messages.customers.reallocate_empty'));
        }

        try {
            $count = DB::transaction(fn (): int => $reallocation->reallocate(
                $orders,
                $request->user(),
                $validated,
            ));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: __('messages.customers.reallocate_failed');

            return $this->failure($request, (string) $message);
        } catch (Throwable $e) {
            report($e);

            return $this->failure($request, __('messages.customers.reallocate_failed'));
        }

        $sale = User::query()->find((int) $validated['sale_user_id']);

        return $this->success($request, __('messages.customers.reallocate_success', [
            'count' => $count,
            'sale' => $sale?->name ?? '#'.$validated['sale_user_id'],
        ]));
    }

    public function queueReallocation(
        Request $request,
        OrderOperationHistoryService $history,
    ): JsonResponse|RedirectResponse {
        $this->authorizeFull($request);
        $orders = $this->selectedOrders($request);

        try {
            DB::transaction(function () use ($orders, $history, $request): void {
                foreach ($orders as $order) {
                    $before = $history->snapshot($order);
                    $order->forceFill([
                        'sale_user_id' => null,
                        'team_id' => null,
                        'assigned_at' => null,
                    ])->save();

                    $history->record(
                        $order,
                        $request->user(),
                        'customer_reallocation_queued',
                        $before,
                        $history->snapshot($order),
                        'Chuyển về danh sách chờ phân bổ lại',
                    );
                }
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($request, 'Không chuyển được hồ sơ về danh sách chờ phân bổ.');
        }

        return $this->success($request, 'Đã chuyển '.count($orders).' hồ sơ về danh sách chờ phân bổ.');
    }

    public function recall(
        Request $request,
        OrderOperationHistoryService $history,
    ): JsonResponse|RedirectResponse {
        $this->authorizeFull($request);
        $orders = $this->selectedOrders($request);

        try {
            DB::transaction(function () use ($orders, $history, $request): void {
                foreach ($orders as $order) {
                    $before = $history->snapshot($order);
                    $order->forceFill([
                        'sale_user_id' => null,
                        'team_id' => null,
                        'assigned_at' => null,
                        'operation_stage' => 'new_customer',
                        'operation_result' => null,
                        'next_operation_at' => null,
                    ])->save();

                    $history->record(
                        $order,
                        $request->user(),
                        'customer_recalled',
                        $before,
                        $history->snapshot($order),
                        'Thu hồi hồ sơ khách hàng',
                    );
                }
            });
        } catch (Throwable $e) {
            report($e);

            return $this->failure($request, 'Không thu hồi được hồ sơ đã chọn.');
        }

        return $this->success($request, 'Đã thu hồi '.count($orders).' hồ sơ.');
    }

    public function deleteOperationHistory(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        try {
            $ids = $this->validatedIds($request);
            $orderIds = Order::query()->whereIn('id', $ids)->pluck('id');
            $deleted = OrderOperationHistory::query()->whereIn('order_id', $orderIds)->delete();
        } catch (Throwable $e) {
            report($e);

            return $this->failure($request, 'Không xóa được lịch sử tác nghiệp đã chọn.');
        }

        return $this->success($request, 'Đã xóa '.$deleted.' bản ghi lịch sử tác nghiệp.');
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if (! $request->user()?->allows(PermissionArea::Customers, PermissionLevel::View)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Bạn không có quyền xuất hồ sơ khách hàng.'], 403);
            }

            abort(403);
        }

        $rawIds = $request->input('ids', $request->query('ids', []));
        if (! is_array($rawIds)) {
            $rawIds = [$rawIds];
        }
        $ids = collect($rawIds)->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        $variant = min(4, max(1, (int) $request->input('variant', $request->integer('variant', 1))));

        if ($ids->isEmpty()) {
            return response()->json(['message' => 'Vui lòng chọn ít nhất một hồ sơ để xuất.'], 422);
        }

        $headers = match ($variant) {
            2 => ['Tên khách hàng', 'Số điện thoại', 'Địa chỉ', 'Tin nhắn', 'Nguồn dữ liệu', 'Marketing', 'Sale'],
            3 => ['Mã đơn', 'Sản phẩm', 'Số lượng', 'Thành tiền', 'Chiết khấu', 'VAT', 'Phí vận chuyển', 'Tổng tiền', 'Đặt cọc'],
            4 => ['Mã đơn', 'Tên khách hàng', 'Số điện thoại', 'Nguồn dữ liệu', 'Marketing', 'Sale', 'Tác nghiệp', 'Kết quả', 'Sản phẩm', 'Tổng tiền', 'Kho', 'PTGH', 'Mã giao vận', 'Trạng thái giao hàng', 'ĐSNB'],
            default => ['Mã đơn', 'Nguồn dữ liệu', 'Ngày data về', 'Tên khách hàng', 'Số điện thoại', 'Sale', 'Tác nghiệp', 'Kết quả', 'Sản phẩm', 'Tổng tiền', 'Trạng thái giao hàng'],
        };

        try {
            $companyId = $request->user()?->company_id;
            $orders = Order::query()
                ->with([
                    'items:id,order_id,product_name,quantity',
                    'saleUser:id,name,email',
                    'marketerUser:id,name,email',
                    'marketingSource:id,name',
                    'warehouse:id,name',
                ])
                ->whereIn('id', $ids->all())
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->latest('id')
                ->limit(max(1, $ids->count()))
                ->get();

            $rows = $orders->map(function (Order $order) use ($variant): array {
                $products = $order->items
                    ->map(fn ($item) => trim((string) ($item->product_name ?? '')).' x'.(int) $item->quantity)
                    ->filter()
                    ->implode(' | ');
                $quantity = (int) $order->items->sum('quantity');
                $arrived = $order->data_arrived_at;
                $arrivedLabel = $arrived ? $arrived->format('d/m/Y H:i:s') : '';

                return match ($variant) {
                    2 => [
                        $order->customer_name,
                        $order->customer_phone,
                        $order->effectiveShippingAddress(),
                        $order->customer_note,
                        $order->marketingSource?->name,
                        $order->marketerUser?->name,
                        $order->saleUser?->name,
                    ],
                    3 => [
                        $order->order_code,
                        $products,
                        $quantity,
                        $order->subtotal,
                        $order->discount,
                        $order->vat,
                        $order->shipping_fee_collected,
                        $order->total,
                        $order->deposit,
                    ],
                    4 => [
                        $order->order_code,
                        $order->customer_name,
                        $order->customer_phone,
                        $order->marketingSource?->name,
                        $order->marketerUser?->name,
                        $order->saleUser?->name,
                        $order->operation_stage,
                        $order->operation_result,
                        $products,
                        $order->total,
                        $order->warehouse?->name,
                        $order->shipping_method,
                        $order->tracking_number,
                        $order->delivery_status,
                        $order->internal_recon_note,
                    ],
                    default => [
                        $order->order_code,
                        $order->marketingSource?->name,
                        $arrivedLabel,
                        $order->customer_name,
                        $order->customer_phone,
                        $order->saleUser?->name,
                        $order->operation_stage,
                        $order->operation_result,
                        $products,
                        $order->total,
                        $order->delivery_status,
                    ],
                };
            })->all();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Không xuất được dữ liệu hồ sơ khách hàng. Vui lòng thử lại hoặc kiểm tra log server.',
            ], 500);
        }

        $safeRows = array_map(
            fn (array $row) => array_map(fn ($value) => $value === null ? '' : $value, $row),
            $rows,
        );

        return response()->streamDownload(function () use ($headers, $safeRows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($safeRows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'ho-so-khach-hang-'.now()->format('Ymd-His').'-kieu-'.$variant.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeFull(Request $request): void
    {
        if ($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full)) {
            return;
        }

        if ($request->expectsJson() || $request->ajax()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ], 403));
        }

        abort(403);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Order> */
    private function selectedOrders(Request $request)
    {
        return Order::query()
            ->whereIn('id', $this->validatedIds($request))
            ->when($request->user()?->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->get();
    }

    /** @return list<int> */
    private function validatedIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        return collect($validated['ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function success(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    private function failure(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->withErrors(['customers' => $message]);
    }
}
