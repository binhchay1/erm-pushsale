<?php

namespace App\Http\Controllers\Admin\Pushsale\Pages;

use App\Models\Pushsale\KpiCatalogItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Page7_1_1Controller extends BasePushsalePageController
{
    protected string $pageCode = '7.1.1';

    public function addMissing(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        [$year, $month, $department] = $this->periodFromRequest($request);
        $users = $this->targetUsers($department)->get();
        $created = 0;

        DB::transaction(function () use ($users, $year, $month, $request, &$created): void {
            foreach ($users as $user) {
                $plan = MonthlyKpiPlan::query()->firstOrCreate(
                    ['user_id' => $user->id, 'year' => $year, 'month' => $month],
                    $this->defaultPlanAttributes($user, $year, $month, $request->user()?->id)
                );

                if ($plan->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $this->ok($request, "Đã thêm {$created} KPI cho nhân viên chưa có kế hoạch.");
    }

    public function copyPrevious(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        [$year, $month, $department] = $this->periodFromRequest($request);
        $current = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $previous = $current->subMonth();
        $users = $this->targetUsers($department)->get()->keyBy('id');
        $previousPlans = MonthlyKpiPlan::query()
            ->where('year', $previous->year)
            ->where('month', $previous->month)
            ->whereIn('user_id', $users->keys())
            ->get();
        $copied = 0;

        DB::transaction(function () use ($previousPlans, $year, $month, $request, &$copied): void {
            foreach ($previousPlans as $previousPlan) {
                MonthlyKpiPlan::query()->updateOrCreate(
                    ['user_id' => $previousPlan->user_id, 'year' => $year, 'month' => $month],
                    [
                        'kpi_name' => $previousPlan->kpi_name,
                        'budget' => $previousPlan->budget,
                        'clicks_target' => $previousPlan->clicks_target,
                        'contacts_target' => $previousPlan->contacts_target,
                        'revenue_target' => $previousPlan->revenue_target,
                        'new_contacts_target' => $previousPlan->new_contacts_target,
                        'new_closed_target' => $previousPlan->new_closed_target,
                        'old_contacts_target' => $previousPlan->old_contacts_target,
                        'old_closed_target' => $previousPlan->old_closed_target,
                        'bonus_percent' => $previousPlan->bonus_percent,
                        'base_salary' => $previousPlan->base_salary,
                        'working_days' => $previousPlan->working_days,
                        'actual_days' => 0,
                        'locked' => false,
                        'updated_by_user_id' => $request->user()?->id,
                        'created_by_user_id' => $previousPlan->created_by_user_id ?: $request->user()?->id,
                    ]
                );
                $copied++;
            }
        });

        return $this->ok($request, "Đã copy {$copied} KPI từ tháng trước.");
    }

    public function lockPeriod(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        [$year, $month, $department] = $this->periodFromRequest($request);
        $userIds = $this->targetUsers($department)->pluck('id');
        $affected = MonthlyKpiPlan::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $userIds)
            ->update(['locked' => true, 'updated_by_user_id' => $request->user()?->id]);

        return $this->ok($request, "Đã chốt {$affected} dòng KPI tháng này.");
    }

    public function bulkSave(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'records' => ['required', 'array'],
            'records.*.id' => ['required', 'integer', 'exists:monthly_kpi_plans,id'],
            'records.*.kpi_name' => ['nullable', 'string', 'max:255'],
            'records.*.budget' => ['nullable', 'integer', 'min:0'],
            'records.*.clicks_target' => ['nullable', 'integer', 'min:0'],
            'records.*.contacts_target' => ['nullable', 'integer', 'min:0'],
            'records.*.revenue_target' => ['nullable', 'integer', 'min:0'],
            'records.*.bonus_percent' => ['nullable', 'numeric', 'min:0'],
            'records.*.base_salary' => ['nullable', 'integer', 'min:0'],
            'records.*.working_days' => ['nullable', 'integer', 'min:0'],
            'records.*.actual_days' => ['nullable', 'integer', 'min:0'],
            'records.*.locked' => ['boolean'],
        ]);

        $saved = 0;
        DB::transaction(function () use ($validated, $request, &$saved): void {
            foreach ($validated['records'] as $record) {
                $plan = MonthlyKpiPlan::query()->findOrFail((int) $record['id']);
                $plan->fill([
                    'kpi_name' => $record['kpi_name'] ?? $plan->kpi_name,
                    'budget' => (int) ($record['budget'] ?? $plan->budget),
                    'clicks_target' => (int) ($record['clicks_target'] ?? $plan->clicks_target),
                    'contacts_target' => (int) ($record['contacts_target'] ?? $plan->contacts_target),
                    'revenue_target' => (int) ($record['revenue_target'] ?? $plan->revenue_target),
                    'bonus_percent' => (float) ($record['bonus_percent'] ?? $plan->bonus_percent),
                    'base_salary' => (int) ($record['base_salary'] ?? $plan->base_salary),
                    'working_days' => (int) ($record['working_days'] ?? $plan->working_days),
                    'actual_days' => (int) ($record['actual_days'] ?? $plan->actual_days),
                    'locked' => (bool) ($record['locked'] ?? $plan->locked),
                    'updated_by_user_id' => $request->user()?->id,
                ])->save();
                $saved++;
            }
        });

        return $this->ok($request, "Đã lưu {$saved} dòng KPI.");
    }

    /** @return array{0:int,1:int,2:string} */
    private function periodFromRequest(Request $request): array
    {
        $now = now();
        $year = max(2020, min(2100, (int) $request->input('year', $request->query('year', $now->year))));
        $month = max(1, min(12, (int) $request->input('month', $request->query('month', $now->month))));
        $department = (string) $request->input('department', $request->query('department', 'marketing'));

        return [$year, $month, $department];
    }

    private function targetUsers(string $department)
    {
        $query = User::query()->orderBy('role')->orderBy('team_id')->orderBy('name');
        if ($department !== '' && $department !== 'all') {
            $query->where('role', $department);
        } else {
            $query->whereIn('role', ['marketing', 'sales', 'warehouse', 'accounting', 'admin']);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function defaultPlanAttributes(User $user, int $year, int $month, ?int $actorId): array
    {
        $role = is_object($user->role) ? $user->role->value : (string) $user->role;
        $baseSalary = match ($role) {
            'sales' => 7000000,
            'marketing' => 9000000,
            'warehouse' => 7500000,
            'accounting' => 8500000,
            default => 12000000,
        };

        $workingDays = 26;
        $catalog = $this->catalogForRole($role);

        return [
            'kpi_name' => $catalog?->kpi_name ?: 'KPI '.Str::ucfirst($role).' tháng '.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.$year,
            'budget' => $catalog ? (int) $catalog->daily_budget * $workingDays : ($role === 'marketing' ? 60000000 : 0),
            'clicks_target' => $catalog ? (int) $catalog->daily_clicks * $workingDays : ($role === 'marketing' ? 18000 : 0),
            'contacts_target' => $catalog
                ? (int) ($catalog->daily_contacts ?: ($catalog->daily_new_contacts + $catalog->daily_old_contacts)) * $workingDays
                : match ($role) {
                    'marketing' => 850,
                    'sales' => 420,
                    'warehouse' => 0,
                    default => 100,
                },
            'revenue_target' => $catalog ? (int) $catalog->daily_revenue * $workingDays : match ($role) {
                'sales' => 280000000,
                'marketing' => 420000000,
                'warehouse' => 0,
                default => 500000000,
            },
            'new_contacts_target' => $catalog && $role === 'sales' ? (int) $catalog->daily_new_contacts * $workingDays : ($role === 'sales' ? 280 : 0),
            'new_closed_target' => $catalog && $role === 'sales' ? (int) $catalog->daily_new_closed * $workingDays : ($role === 'sales' ? 120 : 0),
            'old_contacts_target' => $catalog && $role === 'sales' ? (int) $catalog->daily_old_contacts * $workingDays : ($role === 'sales' ? 140 : 0),
            'old_closed_target' => $catalog && $role === 'sales' ? (int) $catalog->daily_old_closed * $workingDays : ($role === 'sales' ? 70 : 0),
            'bonus_percent' => $role === 'warehouse' ? 0 : 1.5,
            'base_salary' => $baseSalary,
            'working_days' => $workingDays,
            'actual_days' => 0,
            'locked' => false,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
        ];
    }


    private function catalogForRole(string $role): ?KpiCatalogItem
    {
        $position = $role === 'sales' ? 'sales' : ($role === 'marketing' ? 'marketing' : '');
        if ($position === '') {
            return null;
        }

        return KpiCatalogItem::query()
            ->where('position_key', $position)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function ok(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
