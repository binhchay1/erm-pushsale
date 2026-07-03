<?php

namespace Database\Seeders;

use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use App\Support\DemoAccounts;
use Illuminate\Database\Seeder;

/**
 * Tài khoản demo cho MỌI vai trò & cấp bậc — nguồn dữ liệu tại App\Support\DemoAccounts
 * (dùng chung với danh sách tài khoản demo ở trang đăng nhập). Mật khẩu chung: `password`.
 *
 * Quy ước: {vai trò}@saleops.local là TRƯỞNG bộ phận; nhân viên dùng hậu tố số.
 * Cơ cấu: Trưởng bộ phận (head) → Trưởng nhóm (supervisor) → Nhân viên (staff).
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $teams = $this->seedTeams();

        // Pass 1: tạo user (chưa gán quản lý để tránh phụ thuộc thứ tự).
        /** @var array<string, User> $byEmail */
        $byEmail = [];
        $phoneSeq = 1;

        foreach (DemoAccounts::all() as $acc) {
            $byEmail[$acc['email']] = User::query()->create([
                'name' => $acc['name'],
                'email' => $acc['email'],
                'password' => DemoAccounts::PASSWORD,
                'role' => UserRole::from($acc['role']),
                'org_level' => $acc['orgLevel'] ? OrgLevel::from($acc['orgLevel']) : null,
                'is_team_leader' => $acc['isLeader'],
                'team_id' => $acc['team'] ? $teams[$acc['team']]->id : null,
                'job_title' => $acc['position'],
                'phone' => '09'.str_pad((string) (10_000_000 + $phoneSeq++ * 137), 8, '0', STR_PAD_LEFT),
            ]);
        }

        // Pass 2: gán quản lý trực tiếp + trưởng nhóm cho team.
        foreach (DemoAccounts::all() as $acc) {
            $user = $byEmail[$acc['email']];

            if ($acc['manager'] && isset($byEmail[$acc['manager']])) {
                $user->forceFill(['manager_user_id' => $byEmail[$acc['manager']]->id])->save();
            }

            if ($acc['isLeader'] && $acc['team']) {
                $teams[$acc['team']]->forceFill(['leader_user_id' => $user->id])->save();
            }
        }

        foreach ($byEmail as $user) {
            $user->ensurePreferences();
        }

        $this->command?->info('Đã tạo '.User::query()->count().' tài khoản demo theo cơ cấu phòng ban.');
    }

    /** @return array<string, Team> */
    private function seedTeams(): array
    {
        $teams = [];

        foreach (DemoAccounts::teams() as $key => [$name, $type]) {
            $teams[$key] = Team::query()->create([
                'name' => $name,
                'type' => TeamType::from($type),
            ]);
        }

        return $teams;
    }
}
