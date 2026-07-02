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

        return match ($user->role) {
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
    }

    /** @return list<array{label_key?: string, items: list<array{title_key: string, url: string, icon: string}>}> */
    private function adminNavigation(User $user): array
    {
        $groups = [
            $this->group('operations', [
                $this->item('executive_dashboard', '/admin/dashboard', 'home'),
                $this->item('rankings', '/admin/rankings', 'trophy'),
            ]),
            $this->group('reports', [
                $this->item('business_overview', '/admin/reports/business', 'activity'),
                $this->item('ceo_report', '/admin/reports/ceo', 'bar-chart-3'),
                $this->item('sales_performance', '/admin/sales/performance', 'gauge'),
                $this->item('sale_report_1', '/admin/reports/extra/sale-1', 'phone-call'),
                $this->item('sale_report_2', '/admin/reports/extra/sale-2', 'table-2'),
                $this->item('sale_report_3', '/admin/reports/extra/sale-3', 'trending-up'),
                $this->item('sale_report_4', '/admin/reports/extra/sale-4', 'award'),
                $this->item('sale_report_5', '/admin/reports/extra/sale-5', 'calendar-clock'),
                $this->item('marketing_report_1', '/admin/reports/extra/marketing-1', 'circle-dollar-sign'),
                $this->item('campaign_report', '/admin/marketing/campaign-report', 'pie-chart'),
                $this->item('marketing_report_2', '/admin/reports/extra/marketing-2', 'percent'),
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
            $this->item('workspace_sales', '/sales/workspace', 'phone-call'),
            $this->item('performance_report', '/sales/performance', 'bar-chart-3'),
            $this->item('extra_report', '/sales/reports/sale-1', 'file-bar-chart'),
            $this->item('rankings', '/sales/rankings', 'trophy'),
            $this->item('customers', '/sales/customers', 'book-user'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ];
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function marketingItems(User $user): array
    {
        $items = [
            $this->item('overview', '/marketing/dashboard', 'home'),
            $this->item('marketing_workspace', '/marketing/workspace', 'share2'),
            $this->item('campaign_report', '/marketing/campaign-report', 'pie-chart'),
            $this->item('campaigns', '/marketing/campaigns', 'layout-template'),
            $this->item('revenue_report', '/marketing/revenue', 'trending-up'),
            $this->item('extra_report', '/marketing/reports/marketing-1', 'file-bar-chart'),
            $this->item('rankings', '/marketing/rankings', 'trophy'),
        ];

        if ($this->campaignApproval->canViewApprovals($user)) {
            $items[] = $this->item('landing_approvals', '/marketing/landing-approvals', 'layout-template');
        }

        return array_merge($items, [
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ]);
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function warehouseItems(User $user): array
    {
        $items = [
            $this->item('overview', '/warehouse/dashboard', 'home'),
            $this->item('warehouse_workspace', '/warehouse/workspace', 'truck'),
            $this->item('shipping_orders', '/warehouse/shipping/orders', 'package'),
            $this->item('inventory', '/warehouse/inventory', 'boxes'),
        ];

        if ($user->is_team_leader || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true)) {
            $items[] = $this->item('warehouse_report', '/warehouse/reports/kho-1', 'chart-area');
        }

        return array_merge($items, [
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ]);
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function accountingItems(): array
    {
        return [
            $this->item('overview', '/accounting/dashboard', 'home'),
            $this->item('accounting_workspace', '/accounting/workspace', 'wallet'),
            $this->item('business_report', '/accounting/reports/kho-1', 'receipt'),
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ];
    }

    /** @return list<array{title_key: string, url: string, icon: string}> */
    private function allocatorItems(User $user): array
    {
        $items = [
            $this->item('overview', '/allocator/dashboard', 'home'),
            $this->item('allocator_workspace', '/allocator/workspace', 'user-plus'),
            $this->item('allocation_report', '/allocator/reports/allocation', 'file-bar-chart'),
        ];

        if ($user->is_team_leader || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true)) {
            $items[] = $this->item('allocator_load_report', '/allocator/reports/load', 'gauge');
        }

        return array_merge($items, [
            $this->item('org_chart', '/org-chart', 'git-branch'),
            $this->item('settings', '/settings', 'settings'),
        ]);
    }

    /** @return array{title_key: string, url: string, icon: string} */
    private function item(string $titleKey, string $url, string $icon): array
    {
        return ['title_key' => $titleKey, 'url' => $url, 'icon' => $icon];
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
