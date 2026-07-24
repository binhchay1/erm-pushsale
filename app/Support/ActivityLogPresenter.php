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

    public static function subjectTypeLabel(?string $subjectType): string
    {
        if (! $subjectType) {
            return __('activity.system_actor');
        }

        $normalized = match ($subjectType) {
            'marketing_source', 'App\\Models\\MarketingSource' => 'campaign',
            'user', 'App\\Models\\User' => 'user',
            'order', 'App\\Models\\Order' => 'order',
            'lead_ingestion', 'App\\Models\\LeadIngestion' => 'lead',
            'warehouse_inventory_movement', 'App\\Models\\WarehouseInventoryMovement' => 'inventory',
            default => $subjectType,
        };

        $map = trans('activity.subjects');

        return is_array($map) && isset($map[$normalized]) ? $map[$normalized] : class_basename($subjectType);
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
                isset($props['customer_phone']) ? __('activity.summary.phone', ['phone' => $props['customer_phone']]) : null,
            ]),
            'order.updated' => self::joinParts([
                isset($props['total']) ? __('activity.summary.total', ['amount' => self::money($props['total'])]) : null,
                isset($props['items']) ? __('activity.summary.items', ['n' => $props['items']]) : null,
                isset($props['changed_fields']) ? __('activity.summary.changed_fields', ['fields' => self::scalar($props['changed_fields'])]) : null,
            ]),
            'inventory.movement_approved' => self::joinParts([
                isset($props['quantity']) ? __('activity.summary.quantity', ['n' => $props['quantity']]) : null,
                isset($props['warehouse_id']) ? self::warehouseName($props['warehouse_id']) : null,
            ]),
            'lead.ingested' => self::joinParts([
                isset($props['customer_phone']) ? __('activity.summary.phone', ['phone' => $props['customer_phone']]) : null,
                ! empty($props['upsell']) ? __('activity.summary.upsell') : null,
                isset($props['order_total']) ? __('activity.summary.total', ['amount' => self::money($props['order_total'])]) : null,
            ]),
            'campaign.rejected' => isset($props['reason'])
                ? __('activity.summary.reason', ['reason' => $props['reason']])
                : null,
            'user.created', 'user.updated' => self::joinParts([
                isset($props['role']) ? self::roleLabel($props['role']) : null,
                isset($props['email']) ? $props['email'] : null,
            ]),
            'data_filter.searched' => self::joinParts([
                isset($props['page_title']) ? $props['page_title'] : null,
                isset($props['filter_label']) ? $props['filter_label'] : null,
                isset($props['result_count']) ? __('activity.summary.result_count', ['n' => $props['result_count']]) : null,
            ]),
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
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            [$label, $display] = self::describeProperty((string) $key, $value);

            if ($display === null || $display === '') {
                continue;
            }

            $rows[] = ['label' => $label, 'value' => $display];
        }

        return $rows;
    }

    /** @return list<array{label: string, value: string}> */
    public static function metaDetails(ActivityLog $log): array
    {
        $props = is_array($log->properties) ? $log->properties : [];
        $request = is_array($props['_request'] ?? null) ? $props['_request'] : [];

        $rows = [
            ['label' => __('activity.action_key'), 'value' => $log->action],
            ['label' => __('activity.subject_type'), 'value' => self::subjectTypeLabel((string) $log->subject_type)],
        ];

        if ($log->subject_id) {
            $rows[] = ['label' => __('activity.subject_id'), 'value' => (string) $log->subject_id];
        }

        if ($log->actor?->email) {
            $rows[] = ['label' => __('activity.actor_email'), 'value' => $log->actor->email];
        }

        foreach ([
            'method' => __('activity.request_method'),
            'path' => __('activity.request_path'),
            'route_name' => __('activity.request_route'),
            'referer' => __('activity.request_referer'),
        ] as $key => $label) {
            if (! empty($request[$key])) {
                $rows[] = ['label' => $label, 'value' => (string) $request[$key]];
            }
        }

        if ($log->user_agent) {
            $rows[] = ['label' => __('activity.user_agent'), 'value' => (string) $log->user_agent];
        }

        return $rows;
    }

    /**
     * Raw properties are shown in the detail dialog as a compact audit fallback.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function rawProperties(ActivityLog $log): array
    {
        $props = is_array($log->properties) ? $log->properties : [];
        $rows = [];

        foreach ($props as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $rows[] = [
                'label' => (string) $key,
                'value' => self::scalar($value) ?? '',
            ];
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
            'amount_to_collect', 'total', 'order_total', 'discount', 'total_budget', 'cod_fee', 'shipping_fee', 'revenue', 'upsell_revenue' => self::money($value),
            'delivery_status' => self::deliveryLabel((string) $value),
            'role' => self::roleLabel((string) $value),
            'type' => \App\Models\WarehouseInventoryMovement::typeLabel((string) $value),
            'warehouse_id' => self::warehouseName($value),
            'product_id' => self::productName($value),
            'marketer_user_id', 'created_by_user_id', 'approved_by_user_id', 'sale_user_id', 'user_id' => self::userName($value),
            'upsell' => $value ? __('activity.yes') : __('activity.no'),
            // ID nội bộ ít hữu ích trong khối nghiệp vụ, đã show ở meta nếu cần.
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
            if ($value === []) {
                return null;
            }

            if (array_is_list($value)) {
                return implode(', ', array_map(static fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $value));
            }

            return collect($value)
                ->map(static fn ($item, $key) => $key.': '.(is_scalar($item) || $item === null ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))
                ->implode(' · ');
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' ₫';
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
