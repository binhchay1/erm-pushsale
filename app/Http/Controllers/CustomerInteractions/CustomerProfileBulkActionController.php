<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Leads\LeadRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerProfileBulkActionController extends Controller
{
    public function reallocateNow(
        Request $request,
        LeadRoutingService $routing,
        OrderOperationHistoryService $history,
    ): JsonResponse|RedirectResponse {
        $this->authorizeFull($request);
        $orders = $this->selectedOrders($request);

        DB::transaction(function () use ($orders, $routing, $history, $request): void {
            foreach ($orders as $order) {
                $before = $history->snapshot($order);
                $sale = $routing->assignSalesUser();
                if (! $sale) {
                    continue;
                }

                $order->forceFill([
                    'sale_user_id' => $sale->id,
                    'team_id' => $sale->team_id,
                    'assigned_at' => now(),
                ])->save();

                $history->record(
                    $order,
                    $request->user(),
                    'customer_reallocated_now',
                    $before,
                    $history->snapshot($order),
                    'Phân bổ lại ngay từ Hồ sơ khách hàng',
                    ['sale_user_id' => $sale->id],
                );
            }
        });

        return $this->success($request, 'Đã phân bổ lại '.count($orders).' hồ sơ.');
    }

    public function queueReallocation(
        Request $request,
        OrderOperationHistoryService $history,
    ): JsonResponse|RedirectResponse {
        $this->authorizeFull($request);
        $orders = $this->selectedOrders($request);

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

        return $this->success($request, 'Đã chuyển '.count($orders).' hồ sơ về danh sách chờ phân bổ.');
    }

    public function recall(
        Request $request,
        OrderOperationHistoryService $history,
    ): JsonResponse|RedirectResponse {
        $this->authorizeFull($request);
        $orders = $this->selectedOrders($request);

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

        return $this->success($request, 'Đã thu hồi '.count($orders).' hồ sơ.');
    }

    public function deleteOperationHistory(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $ids = $this->validatedIds($request);
        $orderIds = Order::query()->whereIn('id', $ids)->pluck('id');
        $deleted = OrderOperationHistory::query()->whereIn('order_id', $orderIds)->delete();

        return $this->success($request, 'Đã xóa '.$deleted.' bản ghi lịch sử tác nghiệp.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::View), 403);
        $ids = collect($request->input('ids', []))->filter()->map(fn ($id) => (int) $id)->values();
        $variant = min(4, max(1, $request->integer('variant', 1)));

        $orders = Order::query()
            ->with(['items', 'saleUser:id,name,email', 'marketerUser:id,name,email', 'marketingSource:id,name', 'warehouse:id,name'])
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))
            ->latest('id')
            ->limit($ids->isNotEmpty() ? max(1, $ids->count()) : 5000)
            ->get();

        $headers = match ($variant) {
            2 => ['Tên khách hàng', 'Số điện thoại', 'Địa chỉ', 'Tin nhắn', 'Nguồn dữ liệu', 'Marketing', 'Sale'],
            3 => ['Mã đơn', 'Sản phẩm', 'Số lượng', 'Thành tiền', 'Chiết khấu', 'VAT', 'Phí vận chuyển', 'Tổng tiền', 'Đặt cọc'],
            4 => ['Mã đơn', 'Tên khách hàng', 'Số điện thoại', 'Nguồn dữ liệu', 'Marketing', 'Sale', 'Tác nghiệp', 'Kết quả', 'Sản phẩm', 'Tổng tiền', 'Kho', 'PTGH', 'Mã giao vận', 'Trạng thái giao hàng', 'ĐSNB'],
            default => ['Mã đơn', 'Nguồn dữ liệu', 'Ngày data về', 'Tên khách hàng', 'Số điện thoại', 'Sale', 'Tác nghiệp', 'Kết quả', 'Sản phẩm', 'Tổng tiền', 'Trạng thái giao hàng'],
        };

        return response()->streamDownload(function () use ($orders, $headers, $variant): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($orders as $order) {
                $products = $order->items->map(fn ($item) => $item->product_name.' x'.$item->quantity)->implode(' | ');
                $quantity = (int) $order->items->sum('quantity');
                $row = match ($variant) {
                    2 => [$order->customer_name, $order->customer_phone, $order->effectiveShippingAddress(), $order->customer_note, $order->marketingSource?->name, $order->marketerUser?->name, $order->saleUser?->name],
                    3 => [$order->order_code, $products, $quantity, $order->subtotal, $order->discount, $order->vat, $order->shipping_fee_collected, $order->total, $order->deposit],
                    4 => [$order->order_code, $order->customer_name, $order->customer_phone, $order->marketingSource?->name, $order->marketerUser?->name, $order->saleUser?->name, $order->operation_stage, $order->operation_result, $products, $order->total, $order->warehouse?->name, $order->shipping_method, $order->tracking_number, $order->delivery_status, $order->internal_recon_note],
                    default => [$order->order_code, $order->marketingSource?->name, $order->data_arrived_at?->format('d/m/Y H:i:s'), $order->customer_name, $order->customer_phone, $order->saleUser?->name, $order->operation_stage, $order->operation_result, $products, $order->total, $order->delivery_status],
                };
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'ho-so-khach-hang-'.now()->format('Ymd-His').'-kieu-'.$variant.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeFull(Request $request): void
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Order> */
    private function selectedOrders(Request $request)
    {
        return Order::query()->whereIn('id', $this->validatedIds($request))->get();
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
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
