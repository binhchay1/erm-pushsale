<?php

namespace App\Http\Controllers\Warehouse;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Concerns\AssertsOrderInteractionLock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Pushsale\ElectronicInvoiceJob;
use App\Models\Pushsale\ElectronicInvoiceConfig;
use App\Services\DataDeletion\OrderDeletionService;
use App\Services\Warehouse\WarehouseOrderActionService;
use App\Services\Warehouse\WarehouseOrderExcelExportService;
use App\Support\ActivityLogger;
use App\Support\ShippingProviders;
use App\Rules\VietnameseMobilePhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseOrderActionController extends Controller
{
    use AssertsOrderInteractionLock;

    public function __construct(private readonly WarehouseOrderActionService $service) {}



    public function destroy(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'destroy');
        $actor = $request->user();

        if (filled($order->tracking_number) || $order->shipments()->exists() || $order->inventory_deducted_at) {
            throw ValidationException::withMessages([
                'order' => 'Không thể xóa data đã phát sinh giao vận hoặc đã xuất kho.',
            ]);
        }

        $label = $order->order_code ?: '#'.$order->id;
        ActivityLogger::log(
            'warehouse_order_deleted',
            $order,
            [
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'sale_user_id' => $order->sale_user_id,
            ],
            $label,
            $actor,
        );

        app(OrderDeletionService::class)->delete($order);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã xóa data '.$label.'.']);
        }

        return back()->with('success', 'Đã xóa data '.$label.'.');
    }

    public function bulkExport(Request $request, WarehouseOrderExcelExportService $exporter): Response
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:5000'],
            'ids.*' => ['integer'],
            'type' => ['nullable', Rule::in(['standard', 'shipping', 'accounting', 'delivery-status'])],
            'filters' => ['nullable', 'array'],
        ]);

        $type = $data['type'] ?? 'standard';
        if ($type === 'delivery-status') {
            return $this->legacyDeliveryStatusExport($request, $data['ids'] ?? []);
        }

        if (is_array($data['filters'] ?? null)) {
            $request->merge($data['filters']);
        }

        return $exporter->export(
            $type,
            $data['ids'] ?? [],
            ReportFilterData::fromRequest($request, $request->user()),
            $request->user(),
        );
    }

    /**
     * @param  list<int>  $ids
     */
    private function legacyDeliveryStatusExport(Request $request, array $ids): StreamedResponse
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => 'Chọn ít nhất 1 đơn để xuất mẫu TTGH.']);
        }

        $orders = Order::query()
            ->with(['shipments' => fn ($query) => $query->latest('id')])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $filename = 'warehouse-delivery-status-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($orders): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Mã đơn', 'Mã giao vận', 'Trạng thái hiện tại', 'Trạng thái cập nhật', 'Ghi chú']);
            foreach ($orders as $order) {
                $shipment = $order->shipments->first();
                fputcsv($out, [
                    $order->order_code,
                    $shipment?->tracking_number ?: $order->tracking_number,
                    $order->delivery_status,
                    '',
                    '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function bulkInvoices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:orders,id'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $batch = 'warehouse-'.now()->format('YmdHis').'-'.substr(md5(implode(',', $data['ids'])), 0, 8);
        $userId = $request->user()?->id;
        $config = ElectronicInvoiceConfig::query()->where('is_active', true)->latest('id')->first();
        $configNote = $config
            ? sprintf(' · HĐĐT: %s/%s/MST %s', $config->invoice_template_code ?: '-', $config->invoice_series ?: '-', $config->tax_code ?: '-')
            : ' · Chưa có cấu hình HĐĐT đang sử dụng';
        $count = 0;
        foreach (array_unique($data['ids']) as $id) {
            ElectronicInvoiceJob::query()->create([
                'order_id' => $id,
                'electronic_invoice_config_id' => $config?->id,
                'code_type' => 'order_code',
                'process_type' => 'warehouse_bulk_issue',
                'status' => $config ? 'pending' : 'failed',
                'note' => 'Tạo từ màn thủ kho tác nghiệp'.(($data['source'] ?? '') ? ' - '.$data['source'] : '').$configNote,
                'batch_id' => $batch,
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ]);
            $count++;
        }

        return response()->json(['message' => "Đã tạo {$count} yêu cầu xuất HĐĐT.", 'batch_id' => $batch, 'count' => $count]);
    }

    public function bulkUpdateByCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:orders,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $orders = Order::query()->whereIn('id', $data['ids'])->get();
        foreach ($orders as $order) {
            $note = trim((string) ($data['note'] ?? 'Cập nhật nhiều đơn theo mã Pushsale'));
            $order->forceFill(['internal_recon_note' => trim(($order->internal_recon_note ? $order->internal_recon_note."\n" : '').now()->format('d/m/Y H:i').' - '.$note)])->save();
        }

        return response()->json(['message' => 'Đã ghi nhận cập nhật theo mã Pushsale cho '.$orders->count().' đơn.', 'count' => $orders->count()]);
    }

    public function changeOrderCode(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'order_code');
        $data = $request->validate([
            'order_code' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9._\\-]+$/'],
        ]);

        return response()->json([
            'message' => 'Đã đổi mã đơn.',
            'order' => $this->service->changeOrderCode($order, $data['order_code'], $request->user()),
        ]);
    }

    public function desiredDelivery(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'desired_delivery');
        $data = $request->validate(['desired_delivery_at' => ['nullable', 'date']]);
        return response()->json(['order' => $this->service->updateDesiredDelivery($order, $data['desired_delivery_at'] ?? null, $request->user())]);
    }

    public function blacklist(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'blacklist');
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30', new VietnameseMobilePhone],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->service->updateBlacklist($order, $data['phone'], $data['reason'], $request->user());
        return response()->json(['message' => 'Đã cập nhật số blacklist.']);
    }

    public function care(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'care');
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['waiting', 'calling', 'confirmed', 'reschedule', 'complaint', 'completed'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'request_redelivery' => ['sometimes', 'boolean'],
        ]);

        $order = $this->service->updateCare(
            $order,
            $data['status'] ?? null,
            $data['note'] ?? null,
            $request->user(),
            (bool) ($data['request_redelivery'] ?? false),
        );

        return response()->json([
            'message' => ($data['request_redelivery'] ?? false) ? 'Đã yêu cầu giao lại.' : 'Đã cập nhật trạng thái care đơn.',
            'order' => $order,
        ]);
    }

    public function internalMessage(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'internal_message');
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $order = $this->service->postInternalMessage($order, $data['message'], $request->user());

        return response()->json([
            'message' => 'Đã lưu tin nhắn nội bộ.',
            'order' => $order,
        ]);
    }

    public function deliveryStatus(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'delivery_status');
        $data = $request->validate([
            'delivery_status' => ['required', Rule::enum(DeliveryStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'collected_amount' => ['nullable', 'integer', 'min:0'],
        ]);
        return response()->json(['order' => $this->service->updateDeliveryStatus($order, $data['delivery_status'], $data['note'] ?? null, $data['collected_amount'] ?? null, $request->user())]);
    }

    public function updateOrder(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'edit_order');
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30', new VietnameseMobilePhone],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:30', new VietnameseMobilePhone],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'shipping_address_2' => ['nullable', 'string', 'max:500'],
            'shipping_notes' => ['nullable', 'string', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'shipping_provider' => ['nullable', Rule::in(array_keys(config('shipping_partners.providers', [])))],
            'shipping_method' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'vat' => ['nullable', 'integer', 'min:0'],
            'shipping_fee_collected' => ['nullable', 'integer', 'min:0'],
            'deposit' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.item_type' => ['nullable', Rule::in(['product', 'combo', 'upsell', 'gift'])],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
        ]);
        return response()->json(['order' => $this->service->updateOrder($order, $data, $request->user())]);
    }

    public function split(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'split');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);
        return response()->json(['message' => 'Đã tách đơn.', 'order' => $this->service->split($order, $data['items'], $request->user())]);
    }

    public function printed(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'printed');
        return response()->json(['order' => $this->service->markPrinted($order, $request->user())]);
    }

    public function receiveReturn(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderInteractionLock($request, $order, 'return_receipt');
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.order_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.received_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.restock_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.missing_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', Rule::in(['sellable', 'damaged', 'missing', 'inspection'])],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->receiveReturn($order, $data['reason'] ?? null, $data['note'] ?? null, $data['items'] ?? null, $request->user());
        return response()->json(['message' => 'Đã ghi nhận nhập hàng hoàn và cập nhật tồn kho.']);
    }
}
