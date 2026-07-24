<?php

namespace Database\Seeders;

use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessKpiPlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->startOfMonth();
        $periods = [$now->copy()->subMonth(), $now, $now->copy()->addMonth()];
        $users = User::query()
            ->whereIn('role', ['marketing', 'sales', 'warehouse', 'accounting', 'admin'])
            ->orderBy('role')
            ->orderBy('team_id')
            ->orderBy('name')
            ->get();

        foreach ($periods as $periodIndex => $period) {
            foreach ($users as $index => $user) {
                $role = is_object($user->role) ? $user->role->value : (string) $user->role;
                $weight = $periodIndex + $index + 1;
                MonthlyKpiPlan::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'year' => (int) $period->year,
                        'month' => (int) $period->month,
                    ],
                    [
                        'kpi_name' => 'KPI '.Str::ucfirst($role).' '.$period->format('m/Y'),
                        'budget' => $role === 'marketing' ? 45000000 + $weight * 2500000 : 0,
                        'clicks_target' => $role === 'marketing' ? 12000 + $weight * 650 : 0,
                        'contacts_target' => match ($role) {
                            'marketing' => 650 + $weight * 24,
                            'sales' => 260 + $weight * 18,
                            'warehouse' => 0,
                            'accounting' => 60 + $weight * 3,
                            default => 120 + $weight * 4,
                        },
                        'revenue_target' => match ($role) {
                            'marketing' => 320000000 + $weight * 15000000,
                            'sales' => 180000000 + $weight * 12000000,
                            'warehouse' => 0,
                            'accounting' => 420000000 + $weight * 8000000,
                            default => 500000000 + $weight * 10000000,
                        },
                        'new_contacts_target' => $role === 'sales' ? 180 + $weight * 7 : 0,
                        'new_closed_target' => $role === 'sales' ? 70 + $weight * 3 : 0,
                        'old_contacts_target' => $role === 'sales' ? 90 + $weight * 4 : 0,
                        'old_closed_target' => $role === 'sales' ? 35 + $weight * 2 : 0,
                        'bonus_percent' => in_array($role, ['marketing', 'sales', 'accounting', 'admin'], true) ? 1 + (($weight % 5) * 0.25) : 0,
                        'base_salary' => match ($role) {
                            'sales' => 6500000 + ($index % 3) * 500000,
                            'marketing' => 8000000 + ($index % 4) * 700000,
                            'warehouse' => 7000000 + ($index % 3) * 400000,
                            'accounting' => 8500000,
                            default => 12000000,
                        },
                        'working_days' => 26,
                        'actual_days' => $period->isPast() ? 26 : 0,
                        'locked' => $period->lt($now),
                    ]
                );
            }
        }

        $this->command?->info('Đã tạo dữ liệu demo KPI tháng cho CEO 7.1.1.');
    }
}
