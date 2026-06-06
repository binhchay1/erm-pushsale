<?php

namespace App\Services;

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
                $this->group(null, $this->warehouseItems()),
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
                ['title' => 'Dashboard CEO', 'url' => '/admin/dashboard', 'icon' => 'home'],
                ['title' => 'Tổng hợp vận hành', 'url' => '/admin/reports/business', 'icon' => 'activity'],
                ['title' => 'Báo cáo CEO', 'url' => '/admin/reports/ceo', 'icon' => 'bar-chart-3'],
                ['title' => 'Xếp hạng doanh thu', 'url' => '/admin/rankings', 'icon' => 'trophy'],
            ]),
            $this->group('Marketing', [
                ['title' => 'Dashboard MKT', 'url' => '/admin/marketing/dashboard', 'icon' => 'megaphone'],
                ['title' => 'Duyệt Landing', 'url' => '/admin/landing-approvals', 'icon' => 'target'],
                ['title' => 'BC doanh số MKT', 'url' => '/admin/marketing/revenue', 'icon' => 'line-chart'],
            ]),
            $this->group('Telesale', [
                ['title' => 'BC doanh số Sale', 'url' => '/admin/sales/revenue', 'icon' => 'phone-call'],
                ['title' => 'Nhật ký lead', 'url' => '/admin/leads', 'icon' => 'refresh-cw'],
            ]),
            $this->group('Hệ thống', [
                ['title' => 'Tích hợp nền tảng', 'url' => '/admin/integrations', 'icon' => 'plug'],
                ['title' => 'API vận chuyển', 'url' => '/admin/shipping-partners', 'icon' => 'truck'],
                ['title' => 'Đơn vận chuyển', 'url' => '/admin/shipping/orders', 'icon' => 'package'],
                ['title' => 'Đối soát vận chuyển', 'url' => '/admin/shipping/reconciliation', 'icon' => 'file-check'],
            ]),
            $this->group('Danh mục', [
                ['title' => 'Nhân viên', 'url' => '/admin/users', 'icon' => 'users'],
                ['title' => 'Phòng ban', 'url' => '/admin/teams', 'icon' => 'network'],
                ['title' => 'Sản phẩm', 'url' => '/admin/products', 'icon' => 'package'],
                ['title' => 'Danh sách kho', 'url' => '/admin/warehouses', 'icon' => 'warehouse'],
            ]),
            $this->group('Vận hành', [
                ['title' => 'Kế toán', 'url' => '/admin/accounting', 'icon' => 'wallet'],
                ['title' => 'Thủ kho', 'url' => '/admin/warehouse/operations', 'icon' => 'clipboard-list'],
                ['title' => 'Tồn kho SP', 'url' => '/admin/warehouse/inventory', 'icon' => 'package'],
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
            ['title' => 'Dashboard', 'url' => '/sales/dashboard', 'icon' => 'home'],
            ['title' => 'Tác nghiệp telesale', 'url' => '/sales/workspace', 'icon' => 'phone-call'],
            ['title' => 'Xếp hạng doanh thu', 'url' => '/sales/rankings', 'icon' => 'trophy'],
            ['title' => 'Hồ sơ KH', 'url' => '/sales/customers', 'icon' => 'clipboard-list'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function marketingItems(): array
    {
        return [
            ['title' => 'Dashboard', 'url' => '/marketing/dashboard', 'icon' => 'home'],
            ['title' => 'Báo cáo nguồn', 'url' => '/marketing/workspace', 'icon' => 'megaphone'],
            ['title' => 'Kết nối Landing', 'url' => '/marketing/campaigns', 'icon' => 'target'],
            ['title' => 'Doanh thu MKT', 'url' => '/marketing/revenue', 'icon' => 'line-chart'],
            ['title' => 'Xếp hạng doanh thu', 'url' => '/marketing/rankings', 'icon' => 'trophy'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function warehouseItems(): array
    {
        return [
            ['title' => 'Dashboard', 'url' => '/warehouse/dashboard', 'icon' => 'home'],
            ['title' => 'Tác nghiệp kho', 'url' => '/warehouse/workspace', 'icon' => 'truck'],
            ['title' => 'Đơn vận chuyển', 'url' => '/warehouse/shipping/orders', 'icon' => 'package'],
            ['title' => 'Tồn kho', 'url' => '/warehouse/inventory', 'icon' => 'package'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function accountingItems(): array
    {
        return [
            ['title' => 'Dashboard', 'url' => '/accounting/dashboard', 'icon' => 'home'],
            ['title' => 'Tác nghiệp kế toán', 'url' => '/accounting/workspace', 'icon' => 'wallet'],
            ['title' => 'Cài đặt', 'url' => '/settings', 'icon' => 'settings'],
        ];
    }

    /** @return list<array{title: string, url: string, icon: string}> */
    private function allocatorItems(): array
    {
        return [
            ['title' => 'Dashboard', 'url' => '/allocator/dashboard', 'icon' => 'home'],
            ['title' => 'Chia số & lead', 'url' => '/allocator/workspace', 'icon' => 'refresh-cw'],
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
