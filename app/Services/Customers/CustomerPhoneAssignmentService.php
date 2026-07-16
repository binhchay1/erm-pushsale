<?php

namespace App\Services\Customers;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\CustomerPhoneLock;
use App\Models\Order;
use App\Models\User;
use App\Support\TenantManager;
use App\Support\VietnamesePhone;
use Illuminate\Support\Facades\DB;

/**
 * Bảo vệ một SĐT khỏi bị nhiều Sale tác nghiệp song song.
 *
 * Duplicate policy vẫn cho phép cùng khách có nhiều đơn ở nhiều landing connection
 * khác nhau, nhưng operational ownership phải theo SĐT: nếu một SĐT đang có Sale
 * phụ trách thì đơn mới của SĐT đó tự đi về cùng Sale đó. Như vậy báo cáo vẫn
 * đếm đúng từng đơn/nguồn, còn khách không bị hai Sale gọi cùng lúc.
 */
class CustomerPhoneAssignmentService
{
    /** @var list<string> */
    private array $terminalDeliveryStatuses = [
        'delivered',
        'paid',
        'returned',
        'cancel_waybill',
        'cancelled',
        'canceled',
    ];

    public function __construct(private readonly TenantManager $tenant) {}

    public function phoneKey(?string $phone): ?string
    {
        $normalized = VietnamesePhone::normalize($phone);

        if ($normalized) {
            return $normalized;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return $digits !== '' ? mb_substr($digits, 0, 30) : null;
    }

    /**
     * Sale đang sở hữu SĐT trong công ty hiện tại.
     */
    public function activeOwnerForPhone(?string $phone, ?int $companyId = null): ?User
    {
        $phoneKey = $this->phoneKey($phone);
        if (! $phoneKey) {
            return null;
        }

        $companyId ??= $this->tenant->id();

        $lock = CustomerPhoneLock::withoutTenant()
            ->with('ownerSale')
            ->where('phone_key', $phoneKey)
            ->where('status', 'active')
            ->whereNull('released_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'))
            ->lockForUpdate()
            ->first();

        if ($lock?->ownerSale && $lock->ownerSale->role === UserRole::Sales) {
            return $lock->ownerSale;
        }

        // Fallback cho dữ liệu trước V20: nếu chưa có lock nhưng đang có đơn active
        // cùng SĐT thì sale của đơn đó trở thành owner tiếp theo.
        $order = Order::withoutTenant()
            ->with('saleUser')
            ->where('customer_phone', $phoneKey)
            ->whereNotNull('sale_user_id')
            ->where(function ($query): void {
                $query->whereNull('delivery_status')
                    ->orWhereNotIn('delivery_status', $this->terminalDeliveryStatuses);
            })
            ->where(function ($query): void {
                $query->whereNull('operation_result')
                    ->orWhereNotIn('operation_result', ['cancelled', 'canceled', 'deleted']);
            })
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'))
            ->latest('id')
            ->lockForUpdate()
            ->first();

        return $order?->saleUser && $order->saleUser->role === UserRole::Sales
            ? $order->saleUser
            : null;
    }

    public function resolveSaleForNewOrder(?string $phone, ?User $requestedSale, ?User $candidateSale, ?int $companyId = null): ?User
    {
        return DB::transaction(function () use ($phone, $requestedSale, $candidateSale, $companyId): ?User {
            $owner = $this->activeOwnerForPhone($phone, $companyId);

            if ($owner) {
                return $owner;
            }

            return $requestedSale ?: $candidateSale;
        });
    }

    public function isConflict(?string $phone, ?User $requestedSale, User $resolvedSale, ?int $companyId = null): bool
    {
        if (! $requestedSale || (int) $requestedSale->id === (int) $resolvedSale->id) {
            return false;
        }

        $owner = $this->activeOwnerForPhone($phone, $companyId);

        return $owner !== null && (int) $owner->id === (int) $resolvedSale->id;
    }

    public function attachOrder(Order $order, User $saleUser, string $reason = 'lead_allocated'): CustomerPhoneLock
    {
        $phoneKey = $this->phoneKey($order->customer_phone);

        if (! $phoneKey) {
            throw new \InvalidArgumentException('Cannot attach phone lock without a valid customer phone.');
        }

        return DB::transaction(function () use ($order, $saleUser, $phoneKey, $reason): CustomerPhoneLock {
            /** @var CustomerPhoneLock $lock */
            $lock = CustomerPhoneLock::withoutTenant()
                ->where('phone_key', $phoneKey)
                ->when($order->company_id !== null, fn ($query) => $query->where('company_id', $order->company_id))
                ->when($order->company_id === null, fn ($query) => $query->whereNull('company_id'))
                ->lockForUpdate()
                ->first();

            if (! $lock) {
                $lock = new CustomerPhoneLock([
                    'company_id' => $order->company_id,
                    'phone_key' => $phoneKey,
                    'acquired_at' => now(),
                ]);
            }

            $lock->forceFill([
                'owner_sale_user_id' => $saleUser->id,
                'active_order_id' => $order->id,
                'status' => 'active',
                'lock_reason' => $reason,
                'last_activity_at' => now(),
                'expires_at' => now()->addDays((int) config('saleops.customer_phone_lock.active_days', 30)),
                'released_at' => null,
                'meta' => array_filter([
                    'landing_connection_id' => $order->landing_connection_id,
                    'marketing_source_id' => $order->marketing_source_id,
                    'order_code' => $order->order_code,
                ], fn ($value) => $value !== null),
            ])->save();

            return $lock->fresh(['ownerSale', 'activeOrder']);
        });
    }

    public function releaseIfTerminal(Order $order, string $reason = 'order_terminal'): void
    {
        $phoneKey = $this->phoneKey($order->customer_phone);
        if (! $phoneKey || ! in_array((string) $order->delivery_status, $this->terminalDeliveryStatuses, true)) {
            return;
        }

        CustomerPhoneLock::withoutTenant()
            ->where('phone_key', $phoneKey)
            ->where('active_order_id', $order->id)
            ->when($order->company_id !== null, fn ($query) => $query->where('company_id', $order->company_id))
            ->when($order->company_id === null, fn ($query) => $query->whereNull('company_id'))
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'lock_reason' => $reason,
                'last_activity_at' => now(),
            ]);
    }
}
