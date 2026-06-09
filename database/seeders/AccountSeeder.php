<?php

namespace Database\Seeders;

use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tài khoản demo theo đúng business — mật khẩu chung: `password`.
 *
 * Phân quyền theo role (đã được middleware + service kiểm soát):
 *
 * | Role       | Tài khoản chính            | Được làm gì / thấy gì trên giao diện                                    |
 * |------------|----------------------------|--------------------------------------------------------------------------|
 * | admin      | admin@saleops.local        | Toàn quyền: mọi báo cáo, duyệt Landing, quản lý nhân viên/phòng ban/team,
 * |            |                            | sản phẩm, kho, lịch sử nhập xuất kho, đối soát, cài đặt hệ thống.        |
 * | sales      | sales@saleops.local        | Menu /sales/*: gọi & chốt đơn, báo cáo hiệu suất, xếp hạng, hồ sơ KH.    |
 * |            |                            | Chỉ thấy đơn/lead được gán cho chính mình; sơ đồ nhân sự chỉ team mình.  |
 * | marketing  | marketing@saleops.local    | Menu /marketing/*: tổng quan, báo cáo nguồn/chiến dịch/doanh số,         |
 * |            |                            | tạo kết nối trang Landing (chờ admin duyệt). Chỉ thấy chiến dịch mình.   |
 * | warehouse  | warehouse@saleops.local    | Menu /warehouse/*: xuất kho & vận đơn, đơn vận chuyển, tồn kho.          |
 * |            |                            | Nhập/xuất kho phải chọn trưởng kho ký duyệt.                             |
 * | allocator  | allocator@saleops.local    | Menu /allocator/*: theo dõi lead về, chia số thủ công cho telesale.      |
 * | accounting | accounting@saleops.local   | Menu /accounting/*: theo dõi đơn & dòng tiền, đối soát COD.              |
 *
 * Cấp bậc trong mỗi bộ phận: Trưởng bộ phận (head) → Trưởng nhóm (supervisor) → Nhân viên (staff).
 * Sơ đồ nhân sự: head thấy cả bộ phận, leader/staff chỉ thấy team mình, admin thấy tất.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->makeUser('Quản trị viên', 'admin@saleops.local', UserRole::Admin, [
            'job_title' => 'Quản trị hệ thống',
            'phone' => '0900000001',
        ]);

        $this->seedSalesDepartment($admin);
        $this->seedMarketingDepartment($admin);
        $this->seedWarehouseDepartment($admin);
        $this->seedSinglePersonDepartment(
            $admin,
            UserRole::Allocator,
            TeamType::Allocator,
            'Nhóm Chia số',
            'Trương Minh Đức',
            'allocator@saleops.local',
            'Trưởng bộ phận Chia số',
        );
        $this->seedSinglePersonDepartment(
            $admin,
            UserRole::Accounting,
            TeamType::Accounting,
            'Nhóm Kế toán',
            'Phan Thị Lan',
            'accounting@saleops.local',
            'Trưởng bộ phận Kế toán',
        );

        $this->command?->info('Đã tạo '.User::query()->count().' tài khoản theo cơ cấu phòng ban.');
    }

    private function seedSalesDepartment(User $admin): void
    {
        $teamA = Team::query()->create(['name' => 'Nhóm Sale A', 'type' => TeamType::Sale]);
        $teamB = Team::query()->create(['name' => 'Nhóm Sale B', 'type' => TeamType::Sale]);

        $head = $this->makeUser('Vũ Đức Long', 'head.sale@saleops.local', UserRole::Sales, [
            'job_title' => 'Trưởng bộ phận Telesale',
            'org_level' => OrgLevel::Head,
            'is_team_leader' => true,
            'manager_user_id' => $admin->id,
            'team_id' => $teamA->id,
            'phone' => '0911000100',
        ]);

        $this->seedTeam($teamA, $head, 'Trần Thị Thu Hà', 'leader.sale.a@saleops.local', UserRole::Sales, [
            ['Nguyễn Văn Minh', 'sales@saleops.local'],
            ['Lê Thị Hồng Nhung', 'sale02@saleops.local'],
            ['Phạm Văn Khoa', 'sale03@saleops.local'],
            ['Đặng Thu Trang', 'sale04@saleops.local'],
            ['Hoàng Văn Nam', 'sale05@saleops.local'],
        ]);

        $this->seedTeam($teamB, $head, 'Phạm Quang Huy', 'leader.sale.b@saleops.local', UserRole::Sales, [
            ['Võ Thị Kim Chi', 'sale06@saleops.local'],
            ['Bùi Đức Thắng', 'sale07@saleops.local'],
            ['Ngô Thị Mỹ Linh', 'sale08@saleops.local'],
            ['Đỗ Văn Hùng', 'sale09@saleops.local'],
            ['Trịnh Thị Yến', 'sale10@saleops.local'],
        ]);
    }

    private function seedMarketingDepartment(User $admin): void
    {
        $teamA = Team::query()->create(['name' => 'Nhóm Marketing A', 'type' => TeamType::Marketing]);
        $teamB = Team::query()->create(['name' => 'Nhóm Marketing B', 'type' => TeamType::Marketing]);

        $head = $this->makeUser('Lê Hoàng Anh', 'head.marketing@saleops.local', UserRole::Marketing, [
            'job_title' => 'Trưởng bộ phận Marketing',
            'org_level' => OrgLevel::Head,
            'is_team_leader' => true,
            'manager_user_id' => $admin->id,
            'team_id' => $teamA->id,
            'phone' => '0912000100',
        ]);

        $this->seedTeam($teamA, $head, 'Đỗ Mai Phương', 'leader.marketing.a@saleops.local', UserRole::Marketing, [
            ['Bùi Thị Ngọc', 'marketing@saleops.local'],
            ['Nguyễn Hải Đăng', 'mkt02@saleops.local'],
            ['Trần Văn Phú', 'mkt03@saleops.local'],
        ]);

        $this->seedTeam($teamB, $head, 'Ngô Văn Sơn', 'leader.marketing.b@saleops.local', UserRole::Marketing, [
            ['Lý Thu Thảo', 'mkt05@saleops.local'],
            ['Phùng Văn Tú', 'mkt06@saleops.local'],
            ['Vũ Thị Quỳnh', 'mkt07@saleops.local'],
        ]);
    }

    private function seedWarehouseDepartment(User $admin): void
    {
        $team = Team::query()->create(['name' => 'Nhóm Kho', 'type' => TeamType::Warehouse]);

        // Trưởng kho — người ký duyệt mọi phiếu nhập / xuất kho
        $head = $this->makeUser('Hoàng Văn Cường', 'head.warehouse@saleops.local', UserRole::Warehouse, [
            'job_title' => 'Trưởng kho',
            'org_level' => OrgLevel::Head,
            'is_team_leader' => true,
            'manager_user_id' => $admin->id,
            'team_id' => $team->id,
            'phone' => '0913000100',
        ]);

        $team->update(['leader_user_id' => $head->id]);

        $this->makeUser('Đinh Văn Tài', 'warehouse@saleops.local', UserRole::Warehouse, [
            'job_title' => 'Nhân viên kho',
            'org_level' => OrgLevel::Staff,
            'manager_user_id' => $head->id,
            'team_id' => $team->id,
            'phone' => '0913000101',
        ]);

        $this->makeUser('Lý Thị Hòa', 'wh02@saleops.local', UserRole::Warehouse, [
            'job_title' => 'Nhân viên kho',
            'org_level' => OrgLevel::Staff,
            'manager_user_id' => $head->id,
            'team_id' => $team->id,
            'phone' => '0913000102',
        ]);
    }

    private function seedSinglePersonDepartment(
        User $admin,
        UserRole $role,
        TeamType $type,
        string $teamName,
        string $name,
        string $email,
        string $jobTitle,
    ): void {
        $team = Team::query()->create(['name' => $teamName, 'type' => $type]);

        $user = $this->makeUser($name, $email, $role, [
            'job_title' => $jobTitle,
            'org_level' => OrgLevel::Head,
            'is_team_leader' => true,
            'manager_user_id' => $admin->id,
            'team_id' => $team->id,
            'phone' => '091400010'.$team->id,
        ]);

        $team->update(['leader_user_id' => $user->id]);
    }

    /** @param  list<array{0: string, 1: string}>  $members */
    private function seedTeam(
        Team $team,
        User $head,
        string $leaderName,
        string $leaderEmail,
        UserRole $role,
        array $members,
    ): void {
        $leader = $this->makeUser($leaderName, $leaderEmail, $role, [
            'job_title' => 'Trưởng nhóm '.$team->name,
            'org_level' => OrgLevel::Supervisor,
            'is_team_leader' => true,
            'manager_user_id' => $head->id,
            'team_id' => $team->id,
            'phone' => '09'.str_pad((string) (20000000 + $team->id * 100), 8, '0', STR_PAD_LEFT),
        ]);

        $team->update(['leader_user_id' => $leader->id]);

        foreach ($members as $index => [$name, $email]) {
            $this->makeUser($name, $email, $role, [
                'job_title' => 'Nhân viên '.$team->type->label(),
                'org_level' => OrgLevel::Staff,
                'manager_user_id' => $leader->id,
                'team_id' => $team->id,
                'phone' => '09'.str_pad((string) (20000000 + $team->id * 100 + $index + 1), 8, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeUser(string $name, string $email, UserRole $role, array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ], $attributes));

        $user->ensurePreferences();

        return $user;
    }
}
