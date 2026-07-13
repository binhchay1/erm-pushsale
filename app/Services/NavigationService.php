<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Navigation theo đúng cây menu của Pushsale.vn (AdminLTE 2).
 *
 * Cây gốc nằm tại config/pushsale_navigation.php để có thể đối chiếu từng tên
 * menu với file template. Service chỉ làm hai việc: chọn module theo vai trò và
 * đổi route admin sang route tương ứng của vai trò hiện tại.
 */
class NavigationService
{
    /** @return list<array<string, mixed>> */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        /** @var list<array<string, mixed>> $tree */
        $tree = config('pushsale_navigation', []);
        $allowedTopLevels = $this->topLevelsForRole($user->role);

        $tree = array_values(array_filter($tree, function (array $item) use ($allowedTopLevels): bool {
            $number = $this->menuNumber((string) ($item['title'] ?? ''));

            return $number !== null && in_array($number, $allowedTopLevels, true);
        }));

        $tree = array_map(fn (array $item): array => $this->adaptRoutes($item, $user->role), $tree);
        $tree = $this->filterTreeByPermission($user, $tree);

        // Super admin có thêm màn quản trị nền tảng nhưng vẫn giữ nguyên 9 nhóm
        // chuẩn Pushsale; mục này được gắn vào nhóm Quản trị đơn vị.
        if ($user->canManagePlatform()) {
            $tree = $this->appendPlatformItems($tree);
        }

        return $tree;
    }

    /** @return list<int> */
    private function topLevelsForRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::Admin => [1, 2, 3, 4, 5, 6, 7, 8, 9],
            UserRole::Sales => [3, 4, 8],
            UserRole::Marketing => [2, 3, 8],
            UserRole::Warehouse => [3, 5, 8],
            UserRole::Accounting => [3, 6, 8],
            UserRole::Allocator => [1, 2, 3, 8],
        };
    }

    private function menuNumber(string $title): ?int
    {
        if (preg_match('/^(\d+)\./', trim($title), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function adaptRoutes(array $item, UserRole $role): array
    {
        if (isset($item['url']) && is_string($item['url'])) {
            $url = $this->routeForRole($item['url'], $role);
            if ($url === null) {
                unset($item['url']);
                $item['disabled'] = true;
            } else {
                $item['url'] = $url;
            }
        }

        if (! empty($item['children']) && is_array($item['children'])) {
            $item['children'] = array_map(
                fn (array $child): array => $this->adaptRoutes($child, $role),
                $item['children'],
            );
        }

        return $item;
    }

    private function routeForRole(string $url, UserRole $role): ?string
    {
        if ($role === UserRole::Admin) {
            return $url;
        }

        // Các màn hình được dựng từ template Pushsale có URL tĩnh riêng và
        // được dùng chung giữa các vai trò. Cây menu + permission quyết định
        // người dùng có nhìn thấy và được phép mở mã màn hình nào.
        if (preg_match('#^/admin/pages/[a-z0-9-]+$#', $url) === 1) {
            return $url;
        }

        $exact = match ($role) {
            UserRole::Sales => [
                '/admin/sales/workspace' => '/sales/workspace',
                '/admin/customers' => '/sales/customers',
                '/admin/rankings' => '/sales/rankings',
                '/admin/sales/performance' => '/sales/performance',
                '/admin/sales/revenue' => '/sales/reports/sale-3',
                '/admin/reports/ceo' => '/sales/reports/sale-3',
                '/admin/reports/team-leaders' => '/sales/performance',
            ],
            UserRole::Marketing => [
                '/admin/marketing/dashboard' => '/marketing/workspace',
                '/admin/customers' => '/marketing/customers',
                '/admin/rankings' => '/marketing/rankings',
                '/admin/landing-approvals' => '/marketing/landing-approvals',
                '/admin/leads' => '/marketing/leads',
                '/admin/integrations' => '/marketing/campaigns',
                '/admin/marketing/revenue' => '/marketing/revenue',
                '/admin/marketing/campaign-report' => '/marketing/campaign-report',
                '/admin/reports/hourly' => '/marketing/reports/hourly',
                '/admin/reports/team-leaders' => '/marketing/reports/team-leaders',
                '/admin/reports/ceo' => '/marketing/reports/marketing-1',
            ],
            UserRole::Warehouse => [
                '/admin/warehouse/operations' => '/warehouse/workspace',
                '/admin/warehouse/inventory' => '/warehouse/inventory',
                '/admin/warehouse/movements' => '/warehouse/inventory',
                '/admin/warehouses' => '/warehouse/inventory',
                '/admin/customers' => '/warehouse/customers',
                '/admin/shipping/orders' => '/warehouse/shipping/orders',
            ],
            UserRole::Accounting => [
                '/admin/accounting' => '/accounting/workspace',
                '/admin/customers' => '/accounting/customers',
                '/admin/reports/ceo' => '/accounting/reports/kho-1',
            ],
            UserRole::Allocator => [
                '/admin/leads' => '/allocator/workspace',
                '/admin/customers' => '/allocator/customers',
                '/admin/reports/team-leaders' => '/allocator/reports/allocation',
                '/admin/reports/hourly' => '/allocator/reports/load',
            ],
            default => [],
        };

        if (array_key_exists($url, $exact)) {
            return $exact[$url];
        }

        if (preg_match('#^/admin/reports/extra/([a-z0-9-]+)$#', $url, $matches) === 1) {
            return match ($role) {
                UserRole::Sales => '/sales/reports/'.$matches[1],
                UserRole::Marketing => '/marketing/reports/'.$matches[1],
                UserRole::Warehouse => '/warehouse/reports/'.$matches[1],
                UserRole::Accounting => '/accounting/reports/'.$matches[1],
                UserRole::Allocator => '/allocator/reports/allocation',
                default => null,
            };
        }

        // Các route dùng chung ngoài prefix admin.
        if (in_array($url, ['/settings', '/notifications', '/org-chart'], true)) {
            return $url;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function filterTreeByPermission(User $user, array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (! $user->isAdmin() && ! empty($item['area']) && ! $user->allows((string) $item['area'])) {
                continue;
            }

            if (! empty($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterTreeByPermission($user, $item['children']);

                if ($item['children'] === [] && empty($item['url'])) {
                    continue;
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $tree @return list<array<string, mixed>> */
    private function appendPlatformItems(array $tree): array
    {
        foreach ($tree as &$top) {
            if ($this->menuNumber((string) ($top['title'] ?? '')) !== 1) {
                continue;
            }

            $top['children'][] = [
                'title' => '1.16 Quản trị nền tảng',
                'children' => [
                    ['title' => '1. Danh sách doanh nghiệp', 'url' => '/platform/companies', 'icon' => 'building'],
                    ['title' => '2. Cấu hình nền tảng', 'url' => '/platform/settings', 'icon' => 'sliders'],
                    ['title' => '3. Giám sát hệ thống', 'url' => '/admin/system-monitor', 'icon' => 'heartbeat'],
                ],
            ];
            break;
        }
        unset($top);

        return $tree;
    }
}
