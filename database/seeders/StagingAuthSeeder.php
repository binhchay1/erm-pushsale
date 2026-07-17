<?php

namespace Database\Seeders;

use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Scopes\TenantScope;
use App\Models\Team;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\DemoAccounts;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder tối thiểu cho staging: chỉ tạo/cập nhật công ty nội bộ + tài khoản demo.
 * Dùng khi cần bật auto-login/admin test mà chưa muốn seed toàn bộ đơn/kho/báo cáo.
 */
class StagingAuthSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanyProvisioningService::internalCompany();

        app(TenantManager::class)->forCompany($company->id, function () use ($company): void {
            $teams = $this->seedTeams($company);
            $users = $this->seedUsers($company, $teams);
            $this->linkManagersAndLeaders($users, $teams);
        });

        $owner = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'superadmin@saleops.local')
            ->first();

        if ($owner) {
            $owner->forceFill([
                'is_owner' => true,
                'is_platform_admin' => true,
                'company_id' => $company->id,
            ])->save();

            Company::query()->whereKey($company->id)->update([
                'owner_user_id' => $owner->id,
                'contact_email' => 'superadmin@saleops.local',
                'status' => Company::STATUS_ACTIVE,
            ]);
        }

        $this->command?->info('Đã tạo/cập nhật tài khoản staging tối thiểu.');
    }

    /** @return array<string, Team> */
    private function seedTeams(Company $company): array
    {
        $teams = [];

        foreach (DemoAccounts::teams() as $key => [$name, $type]) {
            $teams[$key] = Team::query()->withoutGlobalScope(TenantScope::class)->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['type' => TeamType::from($type)]
            );
        }

        return $teams;
    }

    /** @param  array<string, Team>  $teams @return array<string, User> */
    private function seedUsers(Company $company, array $teams): array
    {
        $users = [];
        $phoneSeq = 1;

        foreach (DemoAccounts::all() as $acc) {
            $users[$acc['email']] = User::query()->withoutGlobalScope(TenantScope::class)->updateOrCreate(
                ['email' => $acc['email']],
                [
                    'company_id' => $company->id,
                    'name' => $acc['name'],
                    'password' => Hash::make(DemoAccounts::PASSWORD),
                    'role' => UserRole::from($acc['role']),
                    'org_level' => $acc['orgLevel'] ? OrgLevel::from($acc['orgLevel']) : null,
                    'is_team_leader' => $acc['isLeader'],
                    'team_id' => $acc['team'] ? ($teams[$acc['team']]->id ?? null) : null,
                    'job_title' => $acc['position'],
                    'phone' => '09'.str_pad((string) (10_000_000 + $phoneSeq++ * 137), 8, '0', STR_PAD_LEFT),
                ]
            );

            $users[$acc['email']]->ensurePreferences();
        }

        return $users;
    }

    /** @param  array<string, User>  $users @param  array<string, Team>  $teams */
    private function linkManagersAndLeaders(array $users, array $teams): void
    {
        foreach (DemoAccounts::all() as $acc) {
            $user = $users[$acc['email']] ?? null;
            if (! $user) {
                continue;
            }

            $managerId = $acc['manager'] && isset($users[$acc['manager']])
                ? $users[$acc['manager']]->id
                : null;

            $user->forceFill(['manager_user_id' => $managerId])->save();

            if ($acc['isLeader'] && $acc['team'] && isset($teams[$acc['team']])) {
                $teams[$acc['team']]->forceFill(['leader_user_id' => $user->id])->save();
            }
        }
    }
}
