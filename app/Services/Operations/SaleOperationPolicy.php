<?php

namespace App\Services\Operations;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\Order;

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
}
