<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Biến nhật ký hoạt động (action key + properties dạng máy) thành nội dung
 * người dùng nghiệp vụ đọc hiểu: hành động gì, trên đối tượng nào, kết quả ra sao.
 */
class ActivityLogPresenter
{
    /** Tên hành động thân thiện (tránh lỗi key có dấu chấm khi tra __()). */
    public static function actionLabel(string $action): string
    {
        $labels = trans('activity.actions');

        return is_array($labels) && isset($labels[$action]) ? $labels[$action] : $action;
    }

    /**
     * Câu tóm tắt 1 dòng: "Ghi nhận cuộc gọi đơn PS... — lần liên hệ thứ 3".
     */
    public static function summary(ActivityLog $log): string
    {
        $label = self::actionLabel($log->action);
        $subject = $log->subject_label;
        $props = is_array($log->properties) ? $log->properties : [];

        $suffix = match ($log->action) {
            'order.call_logged' => isset($props['contact_count'])
                ? __('activity.summary.contact_nth', ['n' => $props['contact_count']])
                : null,
            'order.closed' => self::joinParts([
                isset($props['amount_to_collect']) ? __('activity.summary.collect', ['amount' => self::money($props['amount_to_collect'])]) : null,
                isset($props['delivery_status']) ? self::deliveryLabel($props['delivery_status']) : null,
            ]),
            'order.updated' => self::joinParts([
                isset($props['total']) ? __('activity.summary.total', ['amount' => self::money($props['total'])]) : null,
                isset($props['items']) ? __('activity.summary.items', ['n' => $props['items']]) : null,
            ]),
            'inventory.movement_approved' => isset($props['quantity'])
                ? __('activity.summary.quantity', ['n' => $props['quantity']])
                : null,
            'lead.ingested' => self::joinParts([
                ! empty($props['upsell']) ? __('activity.summary.upsell') : null,
                isset($props['order_total']) ? __('activity.summary.total', ['amount' => self::money($props['order_total'])]) : null,
            ]),
            'campaign.rejected' => isset($props['reason'])
                ? __('activity.summary.reason', ['reason' => $props['reason']])
                : null,
            'user.created', 'user.updated' => isset($props['role'])
                ? self::roleLabel($props['role'])
                : null,
            default => null,
        };

        $parts = array_filter([
            $label,
            $subject !== null && $subject !== '' ? '· '.$subject : null,
            $suffix !== null && $suffix !== '' ? '— '.$suffix : null,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Danh sách chi tiết đã dịch nhãn + định dạng giá trị cho modal xem chi tiết.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function details(ActivityLog $log): array
    {
        $props = is_array($log->properties) ? $log->properties : [];
        $rows = [];

        foreach ($props as $key => $value) {
            [$label, $display] = self::describeProperty((string) $key, $value);

            if ($display === null || $display === '') {
                continue;
            }

            $rows[] = ['label' => $label, 'value' => $display];
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: ?string} [nhãn, giá trị hiển thị]
     */
    private static function describeProperty(string $key, mixed $value): array
    {
        $label = self::propertyLabel($key);

        $display = match ($key) {
            'amount_to_collect', 'total', 'order_total', 'discount', 'total_budget' => self::money($value),
            'delivery_status' => self::deliveryLabel((string) $value),
            'role' => self::roleLabel((string) $value),
            'type' => \App\Models\WarehouseInventoryMovement::typeLabel((string) $value),
            'warehouse_id' => self::warehouseName($value),
            'product_id' => self::productName($value),
            'marketer_user_id', 'created_by_user_id', 'approved_by_user_id' => self::userName($value),
            'upsell' => $value ? __('activity.yes') : __('activity.no'),
            // ID nội bộ không cần khoe ra cho người dùng nghiệp vụ.
            'order_id', 'campaign_id' => null,
            default => self::scalar($value),
        };

        return [$label, $display];
    }

    private static function propertyLabel(string $key): string
    {
        $map = trans('activity.properties');
        if (is_array($map) && isset($map[$key])) {
            return $map[$key];
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    private static function scalar(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? __('activity.yes') : __('activity.no');
        }

        if (is_array($value)) {
            return (string) count($value);
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').'đ';
    }

    private static function deliveryLabel(string $value): string
    {
        return DeliveryStatus::tryFrom($value)?->label() ?? $value;
    }

    private static function roleLabel(string $value): string
    {
        return UserRole::tryFrom($value)?->label() ?? $value;
    }

    private static function warehouseName(mixed $id): ?string
    {
        if (! $id) {
            return null;
        }

        return Warehouse::query()->whereKey($id)->value('name') ?? ('#'.$id);
    }

    private static function productName(mixed $id): ?string
    {
        if (! $id) {
            return null;
        }

        return Product::query()->whereKey($id)->value('name') ?? ('#'.$id);
    }

    private static function userName(mixed $id): ?string
    {
        if (! $id) {
            return null;
        }

        return User::query()->whereKey($id)->value('name') ?? ('#'.$id);
    }

    /** @param  array<int, ?string>  $parts */
    private static function joinParts(array $parts): ?string
    {
        $clean = array_filter($parts, fn ($p) => $p !== null && $p !== '');

        return $clean === [] ? null : implode(', ', $clean);
    }
}
