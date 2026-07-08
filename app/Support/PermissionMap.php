<?php

namespace App\Support;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;

/**
 * Ánh xạ tên route -> [khu vực, mức tối thiểu] để middleware chặn ở server.
 * Route không có trong map -> mặc định cho qua (không phá luồng cũ).
 */
final class PermissionMap
{
    /**
     * Key kết thúc bằng '.' là prefix (khớp tiền tố), còn lại là khớp chính xác.
     * Giá trị: "area:level".
     *
     * @var array<string, string>
     */
    private const MAP = [
        // --- Admin ---
        'admin.users.index' => 'hr:view',
        'admin.users.create' => 'hr:full',
        'admin.users.edit' => 'hr:full',
        'admin.users.store' => 'hr:full',
        'admin.users.update' => 'hr:full',
        'admin.users.destroy' => 'hr:full',
        'admin.teams.' => 'hr:full',
        'admin.products.' => 'products:full',
        'admin.reports.' => 'reports:view',
        'admin.sales.' => 'reports:view',
        'admin.rankings' => 'reports:view',
        'admin.marketing.' => 'marketing:view',
        'admin.landing-approvals.' => 'marketing:full',
        'admin.activity-logs.' => 'activity:view',
        'admin.integrations.' => 'integrations:full',
        'admin.shipping-partners.' => 'shipping:full',
        'admin.shipping.' => 'shipping:full',
        'admin.warehouses.' => 'warehouse:full',
        'admin.warehouse-inventories.' => 'warehouse:full',
        'admin.warehouse.' => 'warehouse:full',
        'admin.accounting' => 'accounting:full',
        'admin.leads.' => 'leads:full',
        'admin.leads' => 'leads:full',
        'admin.orders.' => 'orders:full',
        'admin.failed-orders.' => 'orders:full',

        // --- Sales ---
        'sales.reports.' => 'reports:view',
        'sales.performance' => 'reports:view',
        'sales.rankings' => 'reports:view',
        'customers.index' => 'customers:view',
        'customers.orders.operation-history' => 'customers:view',
        'customers.orders.purchase-history' => 'customers:view',
        'customers.orders.messages.index' => 'customers:view',
        'customers.orders.messages.store' => 'customers:full',
        'sales.customers' => 'customers:view',
        'sales.workspace' => 'telesale:full',
        'sales.orders.' => 'telesale:full',

        // --- Marketing ---
        'marketing.reports.' => 'reports:view',
        'marketing.revenue' => 'reports:view',
        'marketing.campaign-report' => 'reports:view',
        'marketing.rankings' => 'reports:view',
        'marketing.campaigns.' => 'marketing:full',
        'marketing.landing-approvals.' => 'marketing:full',
        'marketing.workspace' => 'marketing:full',

        // --- Warehouse ---
        'warehouse.reports.' => 'reports:view',
        'warehouse.inventory' => 'warehouse:full',
        'warehouse.shipping.' => 'shipping:full',
        'warehouse.workspace' => 'warehouse:full',

        // --- Accounting ---
        'accounting.reports.' => 'reports:view',
        'accounting.workspace' => 'accounting:full',

        // --- Allocator ---
        'allocator.reports' => 'reports:view',
        'allocator.workspace' => 'leads:full',
        'allocator.leads.' => 'leads:full',
    ];

    /**
     * @return array{0: PermissionArea, 1: PermissionLevel}|null
     */
    public static function resolve(?string $routeName): ?array
    {
        if (! $routeName) {
            return null;
        }

        if (isset(self::MAP[$routeName])) {
            return self::parse(self::MAP[$routeName]);
        }

        $bestPrefix = null;
        foreach (self::MAP as $pattern => $spec) {
            if (! str_ends_with($pattern, '.')) {
                continue;
            }
            if (str_starts_with($routeName, $pattern)
                && (($bestPrefix === null) || strlen($pattern) > strlen($bestPrefix))) {
                $bestPrefix = $pattern;
            }
        }

        return $bestPrefix ? self::parse(self::MAP[$bestPrefix]) : null;
    }

    /**
     * @return array{0: PermissionArea, 1: PermissionLevel}
     */
    private static function parse(string $spec): array
    {
        [$area, $level] = explode(':', $spec, 2);

        return [PermissionArea::from($area), PermissionLevel::from($level)];
    }
}
