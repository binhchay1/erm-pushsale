<?php

namespace Database\Seeders;

use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@saleops.local')->first();

        $rootTeam = Team::query()->firstOrCreate([
            'name' => 'Khối vận hành',
        ], [
            'type' => TeamType::Sale,
            'leader_user_id' => $admin?->id,
        ]);

        $teams = [
            UserRole::Sales->value => Team::query()->firstOrCreate(['name' => 'Nhóm Sale A'], [
                'type' => TeamType::Sale,
                'leader_user_id' => $admin?->id,
                'parent_id' => $rootTeam->id,
            ]),
            UserRole::Marketing->value => Team::query()->firstOrCreate(['name' => 'Nhóm Marketing'], [
                'type' => TeamType::Marketing,
                'leader_user_id' => $admin?->id,
                'parent_id' => $rootTeam->id,
            ]),
            UserRole::Warehouse->value => Team::query()->firstOrCreate(['name' => 'Nhóm Kho'], [
                'type' => TeamType::Warehouse,
                'leader_user_id' => $admin?->id,
                'parent_id' => $rootTeam->id,
            ]),
            UserRole::Allocator->value => Team::query()->firstOrCreate(['name' => 'Nhóm Chia số'], [
                'type' => TeamType::Allocator,
                'leader_user_id' => $admin?->id,
                'parent_id' => $rootTeam->id,
            ]),
            UserRole::Accounting->value => Team::query()->firstOrCreate(['name' => 'Nhóm Kế toán'], [
                'type' => TeamType::Accounting,
                'leader_user_id' => $admin?->id,
                'parent_id' => $rootTeam->id,
            ]),
        ];

        User::query()->where('role', UserRole::Sales)->each(
            fn (User $u) => $u->update(['team_id' => $teams[UserRole::Sales->value]->id, 'manager_user_id' => $admin?->id])
        );
        User::query()->where('role', UserRole::Marketing)->each(
            fn (User $u) => $u->update(['team_id' => $teams[UserRole::Marketing->value]->id, 'manager_user_id' => $admin?->id])
        );
        User::query()->where('role', UserRole::Warehouse)->each(
            fn (User $u) => $u->update(['team_id' => $teams[UserRole::Warehouse->value]->id, 'manager_user_id' => $admin?->id])
        );
        User::query()->where('role', UserRole::Allocator)->each(
            fn (User $u) => $u->update(['team_id' => $teams[UserRole::Allocator->value]->id, 'manager_user_id' => $admin?->id])
        );
        User::query()->where('role', UserRole::Accounting)->each(
            fn (User $u) => $u->update(['team_id' => $teams[UserRole::Accounting->value]->id, 'manager_user_id' => $admin?->id])
        );
    }
}
