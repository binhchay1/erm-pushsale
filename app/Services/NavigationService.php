<?php

namespace App\Services;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Marketing\CampaignApprovalService;

class NavigationService
{
    public function __construct(
        private readonly CampaignApprovalService $campaignApproval,
    ) {}

    /** @return list<array{label_key?: string, items: list<array{title_key: string, url: string, icon: string}>}> */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = match ($user->role) {
            UserRole::Admin => $this->adminNavigation($user),
            UserRole::Sales => $this->grouped([
                $this->group(null, $this->salesItems()),
            ]),
            UserRole::Marketing => $this->grouped([
                $this->group(null, $this->marketingItems($user)),
            ]),
            UserRole::Warehouse => $this->grouped([
                $this->group(null, $this->warehouseItems($user)),
            ]),
            UserRole::Accounting => $this->grouped([
                $this->group(null, $this->accountingItems()),
            ]),
            UserRole::Allocator => $this->grouped([
                $this->group(null, $this->allocatorItems($user)),
            ]),
        };

        return $this->filterByPermission($user, $groups);
    }

    /**
     * Ẩn item mà user không đủ quyền xem; bỏ group rỗng. Admin luôn thấy hết.
     *
     * @param  list<array{label_key?: string, items: list<array<string, string>>}>  $groups
     * @return list<array{label_key?: string, items: list<array<string, string>>}>
     */
    private function filterByPermission(User $user, array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            $items = array_values(array_filter($group['items'], function (array $item) use ($user) {
                if (empty($item['area'])) {
                    return true;
                }

                return $user->allows($item['area']);
            }));

            if ($items === []) {
                continue;
            }

            $group['items'] = $items;
            $result[] = $group;
        }

        return $result;
    }

    /** @return list<array{label_key?: string, items: list<array{title_key: string, url: string, icon: string}>}> */
    private function adminNavigation(User $user): array
    {
        $groups = [
            $this->group('operations', [
                $this->item('executive_dashboard', '/admin/dashboard', 'home'),
                $this->item('rankings', '/admin/rankings', 'trophy'),
            ]),
            $this->group('reports_executive', [
                $this->item('business_overview', '/admin/reports/business', 'activity'),
                $this->item('ceo_report', '/admin/reports/ceo', 'bar-chart-3'),
            ]),
            $this->group('reports_sales', [
                $this->item('sales_performance', '/admin/sales/performance', 'gauge'),
                $this->item('sale_report_1', '/admin/reports/extra/sale-1', 'phone-call'),
                $this->item('sale_report_2', '/admin/reports/extra/sale-2', 'table-2'),
                $this->item('sale_report_3', '/admin/reports/extra/sale-3', 'trending-up'),
                $this->item('sale_report_4', '/admin/reports/extra/sale-4', 'award'),
                $this->item('sale_report_5', '/admin/reports/extra/sale-5', 'calendar-clock'),
            ]),
            $this->group('reports_marketing', [
                $this->item('marketing_report_1', '/admin/reports/extra/marketing-1', 'circle-dollar-sign'),
                $this->item('campaign_report', '/admin/marketing/campaign-report', 'pie-chart'),
                $this->item('marketing_report_2', '/admin/reports/extra/marketing-2', 'percent'),
                $this->item('marketing_report_3', '/admin/reports/extra/marketing-3', 'clipboard-list'),
                $this->item('upsale_report', '/admin/reports/extra/marketing-4', 'trending-up'),
                $this->item('team_leader_stats', '/admin/reports/team-leaders', 'network'),
                $this->item('hourly_stats', '/admin/reports/hourly', 'clock'),
            ]),
            $this->group('reports_warehouse', [
                $this->item('warehouse_report_1', '/admin/reports/extra/kho-1', 'store'),
                $this->item('warehouse_report_2', '/admin/reports/extra/kho-2', 'landmark'),
            ]),
            $this->group('marketing', [
                $this->item('marketing_dashboard', '/admin/marketing/dashboard', 'megaphone'),
                $this->item('landing_approvals', '/admin/landing-approvals', 'layout-template'),
            ]),
            $this->group('telesale', [
                $this->item('leads_log', '/admin/leads', 'inbox'),
            ]),
            $this->group('connections', [
                $this->item('integrations', '/admin/integrations', 'plug'),
                $this->item('shipping_partners', '/admin/shipping-partners', 'truck'),
                $this->item('shipping_orders', '/admin/shipping/orders', 'package'),
                $this->item('shipping_reconciliation', '/admin/shipping/reconciliation', 'file-check'),
            ]),
            $this->group('hr_catalog', [
                $this->item('users', '/admin/users', 'users'),
                $this->item('teams', '/admin/teams', 'network'),
                $this->item('activity_logs', '/admin/activity-logs', 'scroll-text'),
                $this->item('org_chart', '/org-chart', 'git-branch'),
                $this->item('products', '/admin/products', 'box'),
            ]),
            $this->group('warehouse_finance', [
                $this->item('accounting', '/admin/accounting', 'wallet'),
                $this->item('warehouses', '/admin/warehouses', 'warehouse'),
                $this->item('inventory', '/admin/warehouse/inventory', 'boxes'),
                $this->item('movements', '/admin/warehouse/movements', 'arrow-right-left'),
                $this->item('failed_orders', '/admin/orders/failed', 'alert-triangle'),
            ]),
        ];

        if ($user->canManagePlatform()) {
            $groups[] = $this->group('platform', [
                $this->item('platform_companies', '/platform/companies', 'building-2'),
                $this->item('platform_settings', '/platform/settings', 'settings-2'),
                $this->item('system_monitor', '/admin/system-monitor', 'activity'),
            ]);
        }

        $groups[] = $this->group(null, [
            $this->item('settings', '/settings', 'settings'),
        ]);

        return $this->grouped($groups);
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function salesItems(): array
    {
        return [
            $this->item('overview', '/sales/dashboard', 'home'),
            $this->item('workspace_sales', '/sales/workspace', 'phone-call', 'telesale'),
            $this->item('performance_report', '/sales/performance', 'bar-chart-3', 'reports'),
            $this->item('extra_report', '/sales/reports/sale-1', 'file-bar-chart', 'reports'),
            $this->item('rankings', '/sales/rankings', 'trophy', 'reports'),
            $this->item('customers', '/sales/customers', 'book-user', 'customers'),
            $this->item('users', '/admin/users', 'users', 'hr'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ];
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function marketingItems(User $user): array
    {
        $items = [
            $this->item('overview', '/marketing/dashboard', 'home'),
            $this->item('marketing_workspace', '/marketing/workspace', 'share2', 'marketing'),
            $this->item('campaign_report', '/marketing/campaign-report', 'pie-chart', 'reports'),
            $this->item('campaigns', '/marketing/campaigns', 'layout-template', 'marketing'),
            $this->item('revenue_report', '/marketing/revenue', 'trending-up', 'reports'),
            $this->item('extra_report', '/marketing/reports/marketing-1', 'file-bar-chart', 'reports'),
            $this->item('marketing_report_3', '/marketing/reports/marketing-3', 'clipboard-list', 'reports'),
            $this->item('upsale_report', '/marketing/reports/marketing-4', 'trending-up', 'reports'),
            $this->item('team_leader_stats', '/marketing/reports/team-leaders', 'network', 'reports'),
            $this->item('hourly_stats', '/marketing/reports/hourly', 'clock', 'reports'),
            $this->item('rankings', '/marketing/rankings', 'trophy', 'reports'),
        ];

        if ($this->campaignApproval->canViewApprovals($user)) {
            $items[] = $this->item('landing_approvals', '/marketing/landing-approvals', 'layout-template', 'marketing');
        }

        return array_merge($items, [
            $this->item('users', '/admin/users', 'users', 'hr'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ]);
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function warehouseItems(User $user): array
    {
        $items = [
            $this->item('overview', '/warehouse/dashboard', 'home'),
            $this->item('warehouse_workspace', '/warehouse/workspace', 'truck', 'warehouse'),
            $this->item('shipping_orders', '/warehouse/shipping/orders', 'package', 'shipping'),
            $this->item('inventory', '/warehouse/inventory', 'boxes', 'warehouse'),
        ];

        if ($user->is_team_leader || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true)) {
            $items[] = $this->item('warehouse_report', '/warehouse/reports/kho-1', 'chart-area', 'reports');
        }

        return array_merge($items, [
            $this->item('users', '/admin/users', 'users', 'hr'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ]);
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function accountingItems(): array
    {
        return [
            $this->item('overview', '/accounting/dashboard', 'home'),
            $this->item('accounting_workspace', '/accounting/workspace', 'wallet', 'accounting'),
            $this->item('business_report', '/accounting/reports/kho-1', 'receipt', 'reports'),
            $this->item('users', '/admin/users', 'users', 'hr'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ];
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function allocatorItems(User $user): array
    {
        $items = [
            $this->item('overview', '/allocator/dashboard', 'home'),
            $this->item('allocator_workspace', '/allocator/workspace', 'user-plus', 'leads'),
            $this->item('allocation_report', '/allocator/reports/allocation', 'file-bar-chart', 'reports'),
        ];

        if ($user->is_team_leader || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true)) {
            $items[] = $this->item('allocator_load_report', '/allocator/reports/load', 'gauge', 'reports');
        }

        return array_merge($items, [
            $this->item('users', '/admin/users', 'users', 'hr'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ]);
    }

    /** @return array{title_key: string, url: string, icon: string, area?: string} */
    private function item(string $titleKey, string $url, string $icon, ?string $area = null): array
    {
        $item = ['title_key' => $titleKey, 'url' => $url, 'icon' => $icon];

        if ($area !== null) {
            $item['area'] = $area;
        }

        return $item;
    }

    /**
     * @param  list<array{title_key: string, url: string, icon: string}>  $items
     * @return array{label_key?: string, items: list<array{title_key: string, url: string, icon: string}>}
     */
    private function group(?string $labelKey, array $items): array
    {
        $group = ['items' => $items];

        if ($labelKey !== null) {
            $group['label_key'] = $labelKey;
        }

        return $group;
    }

    /**
     * @param  list<array{label_key?: string, items: list<array{title_key: string, url: string, icon: string}>}>  $groups
     * @return list<array{label_key?: string, items: list<array{title_key: string, url: string, icon: string}>}>
     */
    private function grouped(array $groups): array
    {
        return $groups;
    }
}
