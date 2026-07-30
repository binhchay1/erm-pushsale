<?php

namespace App\Services\Warehouse;

use App\Enums\DeliveryStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\CreateShipmentService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkUpdateByCodeService
{
    public const ACTIONS = [
        'CAP_NHAT_DON' => 'Cập nhật đơn',
        'CAP_NHAT_TTGH' => 'Cập nhật TTGH',
        'CAP_NHAT_GHI_CHU_KE_TOAN' => 'Cập nhật ghi chú kho vận (kế toán)',
        'CAP_NHAT_TTDS' => 'Cập nhật trạng thái đối soát',
        'DANG_DON' => 'Đăng đơn',
        'HUY_DANG_DON' => 'Hủy đăng đơn',
        'HUY_DANG_DON_WITHOUT_API' => 'Hủy đăng đơn (without API)',
        'DOI_MA_DON_PUSHSALE' => 'Đổi mã đơn Pushsale',
        'CAP_NHAT_TT_CARE_DON' => 'Cập nhật trạng thái care đơn',
    ];

    public function __construct(
        private readonly CreateShipmentService $shipments,
        private readonly WarehouseOrderActionService $warehouseActions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, success_count: int, failed_count: int, results: list<array<string, mixed>>}
     */
    public function execute(array $payload, ?User $actor): array
    {
        $action = (string) ($payload['action'] ?? '');
        if (! isset(self::ACTIONS[$action])) {
            throw ValidationException::withMessages(['action' => 'Tiêu chí cập nhật không hợp lệ.']);
        }

        $codes = $this->parseCodes((string) ($payload['codes'] ?? ''));
        if ($codes === []) {
            throw ValidationException::withMessages(['codes' => 'Nhập danh sách mã đơn (cách nhau bằng ";" hoặc xuống dòng).']);
        }

        $codeType = (string) ($payload['code_type'] ?? 'MHT');
        $results = [];
        $success = 0;

        foreach ($codes as $code) {
            try {
                $order = $this->resolveOrder($code, $codeType, (bool) ($payload['is_ghtk'] ?? false));
                if (! $order) {
                    $results[] = ['code' => $code, 'ok' => false, 'message' => 'Không tìm thấy đơn.'];
                    continue;
                }

                $message = $this->applyAction($order, $action, $payload, $actor);
                $success++;
                $results[] = [
                    'code' => $code,
                    'order_code' => $order->fresh()->order_code,
                    'ok' => true,
                    'message' => $message,
                ];
            } catch (Throwable $e) {
                $results[] = ['code' => $code, 'ok' => false, 'message' => $e->getMessage() ?: 'Lỗi xử lý.'];
            }
        }

        return [
            'message' => sprintf(
                'Đã xử lý %d/%d mã (%s).',
                $success,
                count($codes),
                self::ACTIONS[$action],
            ),
            'success_count' => $success,
            'failed_count' => count($codes) - $success,
            'results' => $results,
        ];
    }

    /** @return list<string> */
    public function parseCodes(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/u', trim($raw)) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => trim($code),
            $parts,
        ), static fn (string $code): bool => $code !== '')));
    }

    private function resolveOrder(string $code, string $codeType, bool $isGhtk): ?Order
    {
        $query = Order::query()->with(['shipments', 'items', 'warehouse']);

        if ($codeType === 'MGV') {
            return $query->where(function ($builder) use ($code) {
                $builder->where('tracking_number', $code)
                    ->orWhereHas('shipments', function ($shipments) use ($code) {
                        $shipments->where('tracking_number', $code)
                            ->orWhere('partner_order_id', $code)
                            ->orWhere('tracking_id', $code);
                    });
            })->when($isGhtk, function ($builder) {
                $builder->where(function ($inner) {
                    $inner->where('shipping_provider', 'ghtk')
                        ->orWhereHas('shipments', fn ($shipments) => $shipments->where('provider', 'ghtk'));
                });
            })->first();
        }

        return $query->where('order_code', $code)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyAction(Order $order, string $action, array $payload, ?User $actor): string
    {
        return match ($action) {
            'CAP_NHAT_DON' => $this->updateOrderFields($order, $payload, $actor),
            'CAP_NHAT_TTGH' => $this->updateDeliveryStatus($order, $payload, $actor),
            'CAP_NHAT_GHI_CHU_KE_TOAN' => $this->updateAccountingNote($order, $payload, $actor),
            'CAP_NHAT_TTDS' => $this->updateReconciliation($order, $payload, $actor),
            'DANG_DON' => $this->registerShipment($order, $actor),
            'HUY_DANG_DON' => $this->cancelShipment($order, false, $actor),
            'HUY_DANG_DON_WITHOUT_API' => $this->cancelShipment($order, true, $actor),
            'DOI_MA_DON_PUSHSALE' => $this->regenerateOrderCode($order, $actor),
            'CAP_NHAT_TT_CARE_DON' => $this->updateCare($order, $payload, $actor),
            default => throw ValidationException::withMessages(['action' => 'Tiêu chí không hỗ trợ.']),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateOrderFields(Order $order, array $payload, ?User $actor): string
    {
        if ($this->isPostedOrReconciled($order)) {
            throw ValidationException::withMessages(['order' => 'Đơn đã đăng hoặc đã đối soát — không cập nhật đơn.']);
        }

        $updates = [];
        if (array_key_exists('warehouse_id', $payload) && filled($payload['warehouse_id'])) {
            $updates['warehouse_id'] = (int) $payload['warehouse_id'];
        }
        if (array_key_exists('shipping_provider', $payload) && filled($payload['shipping_provider'])) {
            $updates['shipping_provider'] = (string) $payload['shipping_provider'];
        }
        if (array_key_exists('shipping_method', $payload) && filled($payload['shipping_method'])) {
            $updates['shipping_method'] = (string) $payload['shipping_method'];
        }
        if (array_key_exists('shipping_notes', $payload) && $payload['shipping_notes'] !== null && $payload['shipping_notes'] !== '') {
            $updates['shipping_notes'] = trim((string) $payload['shipping_notes']);
        }

        $geo = is_array($order->shipping_geo) ? $order->shipping_geo : [];
        $changedGeo = false;
        foreach (['length_cm' => 'package_length_cm', 'width_cm' => 'package_width_cm', 'height_cm' => 'package_height_cm', 'weight_grams' => 'package_weight_grams'] as $input => $geoKey) {
            if (array_key_exists($input, $payload) && filled($payload[$input])) {
                $geo[$geoKey] = (int) $payload[$input];
                $changedGeo = true;
            }
        }
        if ($changedGeo) {
            $updates['shipping_geo'] = $geo;
        }

        if ($updates === []) {
            throw ValidationException::withMessages(['order' => 'Không có trường nào được nhập để cập nhật.']);
        }

        $order->update($updates);
        ActivityLogger::log('warehouse_bulk_update_order', $order, [
            'action' => 'CAP_NHAT_DON',
            'updates' => array_keys($updates),
        ], $order->order_code, $actor);

        return 'Đã cập nhật đơn.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateDeliveryStatus(Order $order, array $payload, ?User $actor): string
    {
        if ($this->isReconciled($order)) {
            throw ValidationException::withMessages(['order' => 'Đơn đã đối soát — không cập nhật TTGH.']);
        }

        $status = (string) ($payload['delivery_status'] ?? '');
        if ($status === '' || DeliveryStatus::tryFrom($status) === null) {
            throw ValidationException::withMessages(['delivery_status' => 'Chọn trạng thái giao hàng.']);
        }

        $from = (string) ($order->delivery_status ?: DeliveryStatus::WaitingWaybill->value);
        $pre = [
            DeliveryStatus::WaitingWaybill->value,
            DeliveryStatus::DeliverNow->value,
            DeliveryStatus::CancelClosing->value,
        ];
        if (in_array($from, $pre, true) !== in_array($status, $pre, true)) {
            throw ValidationException::withMessages([
                'delivery_status' => 'Không thể chuyển trạng thái trước đăng đơn sang sau đăng đơn (hoặc ngược lại).',
            ]);
        }

        $this->warehouseActions->updateDeliveryStatus(
            $order,
            $status,
            filled($payload['note'] ?? null) ? (string) $payload['note'] : null,
            null,
            $actor,
        );

        return 'Đã cập nhật TTGH.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateAccountingNote(Order $order, array $payload, ?User $actor): string
    {
        $note = trim((string) ($payload['accounting_note'] ?? $payload['note'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages(['accounting_note' => 'Nhập ghi chú kho vận (kế toán).']);
        }

        $line = now()->format('d/m/Y H:i').' - '.$note;
        $order->forceFill([
            'internal_recon_note' => trim(($order->internal_recon_note ? $order->internal_recon_note."\n" : '').$line),
            'accounting_notes' => trim(($order->accounting_notes ? $order->accounting_notes."\n" : '').$line),
        ])->save();

        ActivityLogger::log('warehouse_bulk_accounting_note', $order, ['note' => $note], $order->order_code, $actor);

        return 'Đã cập nhật ghi chú kho vận.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateReconciliation(Order $order, array $payload, ?User $actor): string
    {
        $status = (string) ($payload['reconciliation_status'] ?? '');
        if ($status === '' || ReconciliationStatus::tryFrom($status) === null) {
            throw ValidationException::withMessages(['reconciliation_status' => 'Chọn trạng thái đối soát.']);
        }

        $order->update(['reconciliation_status' => $status]);
        ActivityLogger::log('warehouse_bulk_reconciliation', $order, ['status' => $status], $order->order_code, $actor);

        return 'Đã cập nhật trạng thái đối soát.';
    }

    private function registerShipment(Order $order, ?User $actor): string
    {
        if (! $order->closed_at) {
            throw ValidationException::withMessages(['order' => 'Đơn chưa chốt — không đăng được.']);
        }

        $this->shipments->createForOrder($order->fresh(['items', 'warehouse']));
        ActivityLogger::log('warehouse_bulk_register_shipment', $order, [], $order->order_code, $actor);

        return 'Đã đăng đơn.';
    }

    private function cancelShipment(Order $order, bool $withoutApi, ?User $actor): string
    {
        $shipment = $order->shipments()->latest('id')->first();
        if (! $shipment) {
            throw ValidationException::withMessages(['order' => 'Đơn chưa có vận đơn để hủy.']);
        }

        if ($withoutApi) {
            $shipment->update([
                'state' => Shipment::STATE_CANCELLED,
                'cancelled_at' => now(),
                'status_text' => 'Hủy đăng đơn (bỏ qua API)',
                'last_synced_at' => now(),
            ]);
            $order->update(['delivery_status' => DeliveryStatus::CancelWaybill->value]);
        } else {
            $this->shipments->cancel($order);
        }

        ActivityLogger::log('warehouse_bulk_cancel_shipment', $order, [
            'without_api' => $withoutApi,
        ], $order->order_code, $actor);

        return $withoutApi ? 'Đã hủy đăng đơn (bỏ qua API).' : 'Đã hủy đăng đơn.';
    }

    private function regenerateOrderCode(Order $order, ?User $actor): string
    {
        return DB::transaction(function () use ($order, $actor): string {
            $before = (string) $order->order_code;
            do {
                $newCode = 'PS'.str_pad((string) random_int(1, 99_999_999_999), 11, '0', STR_PAD_LEFT).'PS';
            } while (
                $newCode === $before
                || Order::query()->where('order_code', $newCode)->exists()
            );

            $order->update(['order_code' => $newCode]);
            ActivityLogger::log('warehouse_bulk_change_order_code', $order, [
                'from' => $before,
                'to' => $newCode,
            ], $newCode, $actor);

            return "Đã đổi mã: {$before} → {$newCode}";
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateCare(Order $order, array $payload, ?User $actor): string
    {
        $status = $payload['warehouse_care_status'] ?? null;
        $note = $payload['warehouse_care_note'] ?? $payload['note'] ?? null;
        if (! filled($status) && ! filled($note)) {
            throw ValidationException::withMessages(['warehouse_care_status' => 'Chọn trạng thái care hoặc nhập ghi chú.']);
        }

        $this->warehouseActions->updateCare(
            $order,
            filled($status) ? (string) $status : null,
            filled($note) ? (string) $note : null,
            $actor,
        );

        return 'Đã cập nhật trạng thái care đơn.';
    }

    private function isPostedOrReconciled(Order $order): bool
    {
        if ($this->isReconciled($order)) {
            return true;
        }

        $activeShipment = $order->shipments
            ->first(fn (Shipment $shipment): bool => $shipment->state !== Shipment::STATE_CANCELLED
                && $shipment->state !== Shipment::STATE_FAILED);

        return $activeShipment !== null
            || in_array($order->delivery_status, [
                DeliveryStatus::Posted->value,
                DeliveryStatus::PickingUp->value,
                DeliveryStatus::Delivering->value,
                DeliveryStatus::Delivered->value,
                DeliveryStatus::Paid->value,
                DeliveryStatus::DeliveryComplete->value,
            ], true);
    }

    private function isReconciled(Order $order): bool
    {
        return in_array((string) $order->reconciliation_status, ReconciliationStatus::settledStatuses(), true);
    }
}
