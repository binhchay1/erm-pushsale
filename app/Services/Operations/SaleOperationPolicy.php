<?php

namespace App\Services\Operations;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

final class SaleOperationPolicy
{
    public static function isOpen(Order $order): bool
    {
        if ($order->closed_at) {
            return false;
        }

        if (in_array($order->closing_status, [ClosingStatus::Closed->value, ClosingStatus::Cancelled->value], true)) {
            return false;
        }

        if ($order->delivery_status === DeliveryStatus::CancelClosing->value) {
            return false;
        }

        return true;
    }

    /**
     * Sale được hủy chốt khi đơn còn CHỜ VẬN ĐƠN và chưa đẩy sang ĐVVC / chưa trừ kho.
     */
    public static function canUnclose(Order $order): bool
    {
        if (! $order->closed_at) {
            return false;
        }

        if ($order->delivery_status !== DeliveryStatus::WaitingWaybill->value) {
            return false;
        }

        if (filled($order->tracking_number)) {
            return false;
        }

        if ($order->inventory_deducted_at) {
            return false;
        }

        if ($order->relationLoaded('shipments')) {
            if ($order->shipments->contains(fn ($shipment) => filled($shipment->tracking_number))) {
                return false;
            }
        } elseif ($order->shipments()->whereNotNull('tracking_number')->exists()) {
            return false;
        }

        return true;
    }

    public static function hasCallablePhone(Order $order): bool
    {
        $phone = preg_replace('/\s+/u', '', (string) $order->customer_phone) ?: '';

        return strlen($phone) >= 9;
    }

    public static function isTerminal(Order $order): bool
    {
        if (! self::isOpen($order)) {
            return true;
        }

        if ($order->operation_stage === OperationStage::Skipped->value) {
            return true;
        }

        $result = OperationResult::tryFromStored($order->operation_result);

        return $result?->isTerminal() ?? false;
    }

    public static function canCall(Order $order): bool
    {
        return self::isOpen($order)
            && ! self::isTerminal($order)
            && self::hasCallablePhone($order);
    }

    public static function canChangeStatus(Order $order): bool
    {
        return self::isOpen($order) && ! self::isTerminal($order);
    }

    /**
     * Chỉ được chốt đơn khi đơn còn đang tác nghiệp (chưa chốt, chưa hủy).
     * Dùng chung cho nút "Đóng đơn" (UI) và validate backend để luồng đồng nhất.
     */
    public static function canClose(Order $order): bool
    {
        return self::isOpen($order) && ! self::isTerminal($order);
    }

    /**
     * Xóa data / xóa trùng số khi check số (4.1, 4.2).
     * Admin (kể cả tài khoản SALE SUPPORT/SALE ADMIN đăng nhập quyền quản trị) được xóa.
     * Sale thường (role sales, gồm sales01) không xóa được — kể cả khi mặc định
     * vẫn có quyền Khách hàng: Toàn quyền để ghi chú / xem hồ sơ.
     */
    public static function canDeleteData(Order $order, ?User $actor = null): bool
    {
        unset($order);

        return self::actorCanDeleteCustomerData($actor);
    }

    public static function actorCanDeleteCustomerData(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->role === UserRole::Sales) {
            return false;
        }

        return $actor->allows(PermissionArea::Customers, PermissionLevel::Full);
    }
}
