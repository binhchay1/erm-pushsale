<?php

namespace App\Services;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\User;

class NavigationService
{
    /** @return list<array{label?: string, items: list<array{title: string, url: string, icon: string}>}> */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return match ($user->role) {
            UserRole::Admin => $this->adminNavigation(),
            UserRole::Sales => $this->grouped([
                $this->group(null, $this->salesItems()),
            ]),
            UserRole::Marketing => $this->grouped([
                $this->group(null, $this->marketingItems()),
            ]),
            UserRole::Warehouse => $this->grouped([
                $this->group(null, $this->warehouseItems($user)),
            ]),
            UserRole::Accounting => $this->grouped([
                $this->group(null, $this->accountingItems()),
            ]),
            UserRole::Allocator => $this->grouped([
                $this->group(null, $this->allocatorItems()),
            ]),
        };
    }

    /** @return list<array{label?: string, items: list<array{title: string, url: string, icon: string}>}> */
    private function adminNavigation(): array
    {
        return $this->grouped([
            $this->group('Điều hành', [
                ['title' => 'Tổng quan điều hành', 'url' => '/admin/dashboard', 'icon' => 'home'],
                ['title' => 'Xếp hạng doanh thu', 'url' => '/admin/rankings', 'icon' => 'trophy'],
            ]),
            // Gom toàn bộ báo cáo về một menu — tránh trùng lặp giữa các nhóm
            $this->group('Báo cáo', [
                ['title' => 'Toàn cảnh vận hành', 'url' => '/admin/reports/business', 'icon' => 'activity'],
                ['title' => 'Báo cáo điều hành', 'url' => '/admin/reports/ceo', 'icon' => 'bar-chart-3'],
                ['title' => 'Hiệu suất Telesale', 'url' => '/admin/sales/performance', 'icon' => 'gauge'],
                ['title' => 'Báo cáo công việc sale', 'url' => '/admin/reports/extra/sale-1', 'icon' => 'phone-call'],
                ['title' => 'Bảng tổng hợp chốt đơn', 'url' => '/admin/reports/extra/sale-2', 'icon' => 'clipboard-list'],
                ['title' => 'Doanh số chi tiết sale', 'url' => '/admin/reports/extra/sale-3', 'icon' => 'trending-up'],
                ['title' => 'Sale KPI', 'url' => '/admin/reports/extra/sale-4', 'icon' => 'target'],
                ['title' => 'Lịch hẹn telesales', 'url' => '/admin/reports/extra/sale-5', 'icon' => 'history'],
                ['title' => 'Doanh số marketing', 'url' => '/admin/reports/extra/marketing-1', 'icon' => 'megaphone'],
                ['title' => 'Báo cáo chiến dịch', 'url' => '/admin/marketing/campaign-report', 'icon' => 'pie-chart'],
                ['title' => 'Tỉ lệ chốt đơn sản phẩm', 'url' => '/admin/reports/extra/marketing-2', 'icon' => 'box'],
                ['title' => 'Doanh số theo kho', 'url' => '/admin/reports/extra/kho-1', 'icon' => 'warehouse'],
                ['title' => 'Kinh doanh hệ thống', 'url' => '/admin/reports/extra/kho-2', 'icon' => 'line-chart'],
            ]),
            $this->group('Marketing', [
                ['title' => 'Tổng quan Marketing', 'url' => '/admin/marketing/dashboard', 'icon' => 'megaphone'],
                ['title' => 'Duyệt trang Landing', 'url' => '/admin/landing-approvals', 'icon' => 'target'],
            ]),
            $this->group('Telesale', [
                ['title' => 'Nhật ký lead về', 'url' => '/admin/leads', 'icon' => 'history'],
            ]),
            $this->group('Kết nối & Đối soát', [
                ['title' => 'Kết nối nền tảng', 'url' => '/admin/integrations', 'icon' => 'plug'],
                ['title' => 'Đối tác vận chuyển', 'url' => '/admin/shipping-partners', 'icon' => 'truck'],
                ['title' => 'Đơn vận chuyển', 'url' => '/admin/shipping/orders', 'icon' => 'package'],
                ['title' => 'Đối soát vận chuyển', 'url' => '/admin/shipping/reconciliation', 'icon' => 'file-check'],
            ]),
            $this->group('Nhân sự & Danh mục', [
                ['title' => 'Nhân viên', 'url' => '/admin/users', 'icon' => 'users'],
                ['title' => 'Phòng ban & Team', 'url' => '/admin/teams', 'icon' => 'network'],
                ['title' => 'Sơ đồ nhân sự', 'url' => '/org-chart', 'icon' => 'git-branch'],
                ['title' => 'Sản phẩm', 'url' => '/admin/products', 'icon' => 'box'],
            ]),
            $this->group('Kho & Tài chính', [
                ['title' => 'Kế toán', 'url' => '/admin/accounting', 'icon' => 'wallet'],
                ['title' => 'Danh sách kho', 'url' => '/admin/warehouses', 'icon' => 'warehouse'],
                ['title' => 'Tồn kho sản phẩm', 'url' => '/admin/warehouse/inventory', 'icon' => 'boxes'],
                ['title' => 'Lịch sử nhập xuất kho', 'url' => '/admin/warehouse/movements', 'icon' => 'clipboard-list'],
                ['title' => 'Đơn lỗi', 'url' => '/admin/orders/failed', 'icon' => 'alert-triangle'],
            ]),
            $this->group(null, [
                ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
            ]),
        ]);
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function salesItems(): array
    {
        return [
            ['title' => 'Tổng quan', 'url' => '/sales/dashboard', 'icon' => 'home'],
            ['title' => 'Gọi & chốt đơn', 'url' => '/sales/workspace', 'icon' => 'phone-call'],
            ['title' => 'Báo cáo hiệu suất', 'url' => '/sales/performance', 'icon' => 'bar-chart-3'],
            ['title' => 'Báo cáo nghiệp vụ', 'url' => '/sales/reports/sale-1', 'icon' => 'line-chart'],
            ['title' => 'Xếp hạng doanh thu', 'url' => '/sales/rankings', 'icon' => 'trophy'],
            ['title' => 'Hồ sơ khách hàng', 'url' => '/sales/customers', 'icon' => 'users'],
            ['title' => 'Sơ đồ nhân sự', 'url' => '/org-chart', 'icon' => 'git-branch'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function marketingItems(): array
    {
        return [
            ['title' => 'Tổng quan', 'url' => '/marketing/dashboard', 'icon' => 'home'],
            ['title' => 'Báo cáo nguồn quảng cáo', 'url' => '/marketing/workspace', 'icon' => 'megaphone'],
            ['title' => 'Báo cáo chiến dịch', 'url' => '/marketing/campaign-report', 'icon' => 'pie-chart'],
            ['title' => 'Trang Landing', 'url' => '/marketing/campaigns', 'icon' => 'target'],
            ['title' => 'Báo cáo doanh số', 'url' => '/marketing/revenue', 'icon' => 'trending-up'],
            ['title' => 'Báo cáo nghiệp vụ', 'url' => '/marketing/reports/marketing-1', 'icon' => 'line-chart'],
            ['title' => 'Xếp hạng doanh thu', 'url' => '/marketing/rankings', 'icon' => 'trophy'],
            ['title' => 'Sơ đồ nhân sự', 'url' => '/org-chart', 'icon' => 'git-branch'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function warehouseItems(User $user): array
    {
        $items = [
            ['title' => 'Tổng quan', 'url' => '/warehouse/dashboard', 'icon' => 'home'],
            ['title' => 'Xuất kho & vận đơn', 'url' => '/warehouse/workspace', 'icon' => 'truck'],
            ['title' => 'Đơn vận chuyển', 'url' => '/warehouse/shipping/orders', 'icon' => 'package'],
            ['title' => 'Tồn kho sản phẩm', 'url' => '/warehouse/inventory', 'icon' => 'boxes'],
        ];

        // Báo cáo doanh số kho là báo cáo tổng hợp — chỉ trưởng kho được xem
        if ($user->is_team_leader || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true)) {
            $items[] = ['title' => 'Báo cáo kho', 'url' => '/warehouse/reports/kho-1', 'icon' => 'line-chart'];
        }

        return array_merge($items, [
            ['title' => 'Sơ đồ nhân sự', 'url' => '/org-chart', 'icon' => 'git-branch'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ]);
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function accountingItems(): array
    {
        return [
            ['title' => 'Tổng quan', 'url' => '/accounting/dashboard', 'icon' => 'home'],
            ['title' => 'Theo dõi đơn & dòng tiền', 'url' => '/accounting/workspace', 'icon' => 'wallet'],
            ['title' => 'Báo cáo kinh doanh', 'url' => '/accounting/reports/kho-1', 'icon' => 'line-chart'],
            ['title' => 'Sơ đồ nhân sự', 'url' => '/org-chart', 'icon' => 'git-branch'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function allocatorItems(): array
    {
        return [
            ['title' => 'Tổng quan', 'url' => '/allocator/dashboard', 'icon' => 'home'],
            ['title' => 'Chia số cho sale', 'url' => '/allocator/workspace', 'icon' => 'refresh-cw'],
            ['title' => 'Sơ đồ nhân sự', 'url' => '/org-chart', 'icon' => 'git-branch'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /**
     * @param  list<array{title: string, url: string, icon: string}>  $items
     * @return array{label?: string, items: list<array{title: string, url: string, icon: string}>}
     */
    private function group(?string $label, array $items): array
    {
        $group = ['items' => $items];

        if ($label !== null) {
            $group['label'] = $label;
        }

        return $group;
    }

    /**
     * @param  list<array{label?: string, items: list<array{title: string, url: string, icon: string}>}>  $groups
     * @return list<array{label?: string, items: list<array{title: string, url: string, icon: string}>}>
     */
    private function grouped(array $groups): array
    {
        return $groups;
    }
}
