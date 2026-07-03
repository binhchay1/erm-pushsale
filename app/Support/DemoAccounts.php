<?php

namespace App\Support;

/**
 * Nguồn dữ liệu chung cho toàn bộ tài khoản demo (nội bộ).
 *
 * Dùng bởi:
 * - AccountSeeder: tạo user + team + phân cấp quản lý.
 * - Trang đăng nhập: hiển thị danh sách tài khoản demo + giải thích chức vụ.
 *
 * Quy ước email: {vai trò}@saleops.local là TRƯỞNG bộ phận (tài khoản chính),
 * nhân viên dùng hậu tố số (sale01@, mkt01@, wh01@...).
 */
class DemoAccounts
{
    public const PASSWORD = 'password';

    public const DOMAIN = 'saleops.local';

    /**
     * Team demo: key => [tên, loại team].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function teams(): array
    {
        return [
            'sale_a' => ['Nhóm Sale A', 'sale'],
            'sale_b' => ['Nhóm Sale B', 'sale'],
            'mkt_a' => ['Nhóm Marketing A', 'marketing'],
            'mkt_b' => ['Nhóm Marketing B', 'marketing'],
            'warehouse' => ['Nhóm Kho', 'warehouse'],
            'allocator' => ['Nhóm Chia số', 'allocator'],
            'accounting' => ['Nhóm Kế toán', 'accounting'],
        ];
    }

    /**
     * Toàn bộ tài khoản demo kèm metadata cấu trúc + hiển thị.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $a = fn (
            string $email,
            string $name,
            string $role,
            ?string $orgLevel,
            bool $isLeader,
            ?string $team,
            ?string $manager,
            string $group,
            string $position,
            string $desc,
            bool $featured = false,
            bool $platformAdmin = false,
        ): array => compact(
            'email', 'name', 'role', 'orgLevel', 'isLeader', 'team',
            'manager', 'group', 'position', 'desc', 'featured', 'platformAdmin',
        );

        $admin = 'admin@saleops.local';

        $accounts = [
            // --- Quản trị hệ thống ---
            $a('superadmin@saleops.local', 'Super Admin', 'admin', null, false, null, null,
                'system', 'Super Admin',
                'Chủ hệ thống: quản trị nền tảng (/platform), tạo công ty & tài khoản, đồng thời xem toàn bộ dữ liệu nội bộ.',
                true, true),
            $a($admin, 'Trần Quản Trị', 'admin', null, false, null, null,
                'system', 'Quản trị công ty',
                'Toàn quyền công ty nội bộ: nhân sự, chiến dịch, duyệt Landing, báo cáo, kho, kế toán, cấu hình.',
                true),

            // --- Telesale (Sale) ---
            $a('sales@saleops.local', 'Vũ Đức Long', 'sales', 'head', false, 'sale_a', $admin,
                'sale', 'Trưởng bộ phận Telesale',
                'Xem toàn bộ đơn & báo cáo của cả bộ phận sale; quản lý các nhóm sale.',
                true),
            $a('leader.sale.a@saleops.local', 'Trần Thị Thu Hà', 'sales', 'supervisor', true, 'sale_a', 'sales@saleops.local',
                'sale', 'Trưởng nhóm Sale A',
                'Quản lý & xem số của riêng nhóm Sale A.',
                true),
            $a('leader.sale.b@saleops.local', 'Phạm Quang Huy', 'sales', 'supervisor', true, 'sale_b', 'sales@saleops.local',
                'sale', 'Trưởng nhóm Sale B',
                'Quản lý & xem số của riêng nhóm Sale B.'),
            $a('sale01@saleops.local', 'Nguyễn Văn Minh', 'sales', 'staff', false, 'sale_a', 'leader.sale.a@saleops.local',
                'sale', 'Nhân viên Telesale',
                'Gọi & chốt đơn được chia; chỉ thấy đơn của chính mình.',
                true),
            $a('sale02@saleops.local', 'Lê Thị Hồng Nhung', 'sales', 'staff', false, 'sale_a', 'leader.sale.a@saleops.local',
                'sale', 'Nhân viên Telesale', 'Gọi & chốt đơn được chia.'),
            $a('sale03@saleops.local', 'Phạm Văn Khoa', 'sales', 'staff', false, 'sale_a', 'leader.sale.a@saleops.local',
                'sale', 'Nhân viên Telesale', 'Gọi & chốt đơn được chia.'),
            $a('sale04@saleops.local', 'Đặng Thu Trang', 'sales', 'staff', false, 'sale_b', 'leader.sale.b@saleops.local',
                'sale', 'Nhân viên Telesale', 'Gọi & chốt đơn được chia.'),
            $a('sale05@saleops.local', 'Hoàng Văn Nam', 'sales', 'staff', false, 'sale_b', 'leader.sale.b@saleops.local',
                'sale', 'Nhân viên Telesale', 'Gọi & chốt đơn được chia.'),

            // --- Marketing ---
            $a('marketing@saleops.local', 'Lê Hoàng Anh', 'marketing', 'head', false, 'mkt_a', $admin,
                'marketing', 'Trưởng bộ phận Marketing',
                'Xem toàn bộ chiến dịch & báo cáo nguồn của cả bộ phận marketing.',
                true),
            $a('leader.marketing.a@saleops.local', 'Đỗ Mai Phương', 'marketing', 'supervisor', true, 'mkt_a', 'marketing@saleops.local',
                'marketing', 'Trưởng nhóm Marketing A',
                'Quản lý & xem số của nhóm Marketing A.',
                true),
            $a('leader.marketing.b@saleops.local', 'Ngô Văn Sơn', 'marketing', 'supervisor', true, 'mkt_b', 'marketing@saleops.local',
                'marketing', 'Trưởng nhóm Marketing B',
                'Quản lý & xem số của nhóm Marketing B.'),
            $a('mkt01@saleops.local', 'Bùi Thị Ngọc', 'marketing', 'staff', false, 'mkt_a', 'leader.marketing.a@saleops.local',
                'marketing', 'Nhân viên Marketing',
                'Tạo chiến dịch & kết nối Landing (chờ admin duyệt); chỉ thấy chiến dịch của mình.',
                true),
            $a('mkt02@saleops.local', 'Nguyễn Hải Đăng', 'marketing', 'staff', false, 'mkt_a', 'leader.marketing.a@saleops.local',
                'marketing', 'Nhân viên Marketing', 'Tạo & theo dõi chiến dịch của mình.'),
            $a('mkt03@saleops.local', 'Trần Văn Phú', 'marketing', 'staff', false, 'mkt_a', 'leader.marketing.a@saleops.local',
                'marketing', 'Nhân viên Marketing', 'Tạo & theo dõi chiến dịch của mình.'),
            $a('mkt04@saleops.local', 'Lý Thu Thảo', 'marketing', 'staff', false, 'mkt_b', 'leader.marketing.b@saleops.local',
                'marketing', 'Nhân viên Marketing', 'Tạo & theo dõi chiến dịch của mình.'),
            $a('mkt05@saleops.local', 'Phùng Văn Tú', 'marketing', 'staff', false, 'mkt_b', 'leader.marketing.b@saleops.local',
                'marketing', 'Nhân viên Marketing', 'Tạo & theo dõi chiến dịch của mình.'),
            $a('mkt06@saleops.local', 'Vũ Thị Quỳnh', 'marketing', 'staff', false, 'mkt_b', 'leader.marketing.b@saleops.local',
                'marketing', 'Nhân viên Marketing', 'Tạo & theo dõi chiến dịch của mình.'),

            // --- Kho vận ---
            $a('warehouse@saleops.local', 'Hoàng Văn Cường', 'warehouse', 'head', true, 'warehouse', $admin,
                'warehouse', 'Trưởng kho',
                'Ký duyệt phiếu nhập/xuất kho, tạo vận đơn, quản lý tồn kho.',
                true),
            $a('wh01@saleops.local', 'Đinh Văn Tài', 'warehouse', 'staff', false, 'warehouse', 'warehouse@saleops.local',
                'warehouse', 'Nhân viên kho',
                'Soạn hàng, tạo vận đơn (cần trưởng kho ký duyệt).',
                true),
            $a('wh02@saleops.local', 'Lý Thị Hòa', 'warehouse', 'staff', false, 'warehouse', 'warehouse@saleops.local',
                'warehouse', 'Nhân viên kho', 'Soạn hàng, tạo vận đơn.'),

            // --- Chia số (Allocator) ---
            $a('allocator@saleops.local', 'Trương Minh Đức', 'allocator', 'head', true, 'allocator', $admin,
                'allocator', 'Trưởng bộ phận Chia số',
                'Theo dõi lead đổ về, chia số thủ công cho telesale.',
                true),

            // --- Kế toán ---
            $a('accounting@saleops.local', 'Phan Thị Lan', 'accounting', 'head', true, 'accounting', $admin,
                'accounting', 'Trưởng bộ phận Kế toán',
                'Theo dõi đơn & dòng tiền, đối soát COD với hãng vận chuyển.',
                true),
        ];

        return $accounts;
    }

    /**
     * Nhóm hiển thị cho trang đăng nhập (chỉ tài khoản tiêu biểu).
     *
     * @return list<array{key: string, label: string, accounts: list<array{email: string, name: string, position: string, desc: string}>}>
     */
    public static function displayGroups(): array
    {
        $labels = [
            'system' => 'Quản trị hệ thống',
            'sale' => 'Telesale (Sale)',
            'marketing' => 'Marketing',
            'warehouse' => 'Kho vận',
            'allocator' => 'Chia số (Allocator)',
            'accounting' => 'Kế toán',
        ];

        $groups = [];

        foreach ($labels as $key => $label) {
            $accounts = [];

            foreach (self::all() as $acc) {
                if ($acc['group'] !== $key || ! $acc['featured']) {
                    continue;
                }

                $accounts[] = [
                    'email' => $acc['email'],
                    'name' => $acc['name'],
                    'position' => $acc['position'],
                    'desc' => $acc['desc'],
                ];
            }

            if ($accounts !== []) {
                $groups[] = ['key' => $key, 'label' => $label, 'accounts' => $accounts];
            }
        }

        return $groups;
    }
}
