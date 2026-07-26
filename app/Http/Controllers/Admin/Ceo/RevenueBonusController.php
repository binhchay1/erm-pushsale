<?php

namespace App\Http\Controllers\Admin\Ceo;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

use App\Models\Pushsale\RevenueBonusRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RevenueBonusController extends BasePushsalePageController
{
    protected string $pageCode = '7.1.4';

    public function index(Request $request): Response|StreamedResponse
    {
        $this->authorizePage($request);
        $filters = $this->filters($request);
        $query = RevenueBonusRule::query()
            ->with('updatedBy:id,name,email')
            ->where('year', $filters['year'])
            ->where('position_key', $filters['position_key']);

        if ($filters['month'] > 0) {
            $query->where('month', $filters['month']);
        }

        $rows = $query->orderBy('month')
            ->orderBy('position_key')
            ->orderBy('revenue_from')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RevenueBonusRule $rule): array => $this->serialize($rule))
            ->values()
            ->all();

        if ($request->boolean('export')) {
            return $this->exportRewardRules($rows);
        }

        return Inertia::render('Admin/Ceo/RevenueBonus', [
            'schema' => [
                'code' => '7.1.4',
                'title' => '(UnitAdmin) Thiết lập tiền thưởng theo doanh số',
                'component' => 'Page_7_1_4',
            ],
            'rows' => $rows,
            'filters' => $filters,
            'routeUrl' => '/'.$request->path(),
            'activeMenuCode' => $this->pageCode,
        ]);
    }

    public function bulkSave(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'records' => ['required', 'array'],
            'records.*.id' => ['nullable', 'integer', 'exists:revenue_bonus_rules,id'],
            'records.*.position_key' => ['required', 'in:marketing,sales,sale'],
            'records.*.year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'records.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'records.*.revenue_from' => ['nullable', 'integer', 'min:0'],
            'records.*.revenue_to' => ['nullable', 'integer', 'min:0'],
            'records.*.bonus_percent' => ['nullable', 'numeric', 'min:0'],
            'records.*.bonus_amount' => ['nullable', 'integer', 'min:0'],
            'records.*.locked' => ['boolean'],
            'records.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $saved = 0;
        DB::transaction(function () use ($validated, $request, &$saved): void {
            foreach ($validated['records'] as $index => $record) {
                $from = (int) ($record['revenue_from'] ?? 0);
                $to = (int) ($record['revenue_to'] ?? 0);
                if ($to > 0 && $to <= $from) {
                    throw ValidationException::withMessages(['records' => 'Doanh số tháng đến phải lớn hơn doanh số tháng từ.']);
                }

                $payload = [
                    'position_key' => RevenueBonusRule::normalizePosition((string) $record['position_key']),
                    'year' => (int) $record['year'],
                    'month' => (int) $record['month'],
                    'revenue_from' => $from,
                    'revenue_to' => $to,
                    'bonus_percent' => (float) ($record['bonus_percent'] ?? 0),
                    'bonus_amount' => (int) ($record['bonus_amount'] ?? 0),
                    'locked' => (bool) ($record['locked'] ?? false),
                    'sort_order' => (int) ($record['sort_order'] ?? ($index + 1)),
                    'updated_by_user_id' => $request->user()?->id,
                ];

                if (! empty($record['id'])) {
                    RevenueBonusRule::query()->findOrFail((int) $record['id'])->fill($payload)->save();
                } else {
                    RevenueBonusRule::query()->create(array_merge($payload, [
                        'created_by_user_id' => $request->user()?->id,
                    ]));
                }

                $saved++;
            }
        });

        return $this->ok($request, "Đã lưu {$saved} dòng khai báo thưởng.");
    }

    public function destroy(Request $request, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        RevenueBonusRule::query()->findOrFail($record)->delete();

        return $this->ok($request, 'Đã xóa dòng khai báo thưởng.');
    }

    public function copyPrevious(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $filters = $this->filters($request);
        $current = CarbonImmutable::create($filters['year'], $filters['month'] ?: now()->month, 1);
        $previous = $current->subMonth();
        $count = 0;

        DB::transaction(function () use ($filters, $previous, $request, &$count): void {
            $previousRows = RevenueBonusRule::query()
                ->where('position_key', $filters['position_key'])
                ->where('year', $previous->year)
                ->where('month', $previous->month)
                ->orderBy('sort_order')
                ->get();

            foreach ($previousRows as $row) {
                RevenueBonusRule::query()->updateOrCreate(
                    [
                        'position_key' => $filters['position_key'],
                        'year' => $filters['year'],
                        'month' => $filters['month'] ?: now()->month,
                        'revenue_from' => $row->revenue_from,
                    ],
                    [
                        'revenue_to' => $row->revenue_to,
                        'bonus_percent' => $row->bonus_percent,
                        'bonus_amount' => $row->bonus_amount,
                        'locked' => false,
                        'sort_order' => $row->sort_order,
                        'created_by_user_id' => $request->user()?->id,
                        'updated_by_user_id' => $request->user()?->id,
                    ]
                );
                $count++;
            }
        });

        return $this->ok($request, "Đã copy {$count} dòng dữ liệu tháng trước.");
    }

    public function setLocked(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $filters = $this->filters($request);
        $locked = $request->boolean('locked', true);
        $updated = RevenueBonusRule::query()
            ->where('position_key', $filters['position_key'])
            ->where('year', $filters['year'])
            ->when($filters['month'] > 0, fn ($query) => $query->where('month', $filters['month']))
            ->update(['locked' => $locked, 'updated_by_user_id' => $request->user()?->id, 'updated_at' => now()]);

        return $this->ok($request, ($locked ? 'Đã chốt' : 'Đã hủy chốt')." {$updated} dòng dữ liệu.");
    }

    /** @return array{year:int, month:int, position_key:string} */
    private function filters(Request $request): array
    {
        return [
            'year' => max(2020, min(2100, (int) $request->query('year', now()->year))),
            'month' => max(0, min(12, (int) $request->query('month', now()->month))),
            'position_key' => RevenueBonusRule::normalizePosition((string) $request->query('position_key', 'marketing')),
        ];
    }

    /** @return array<string, mixed> */
    private function serialize(RevenueBonusRule $rule): array
    {
        return [
            'id' => $rule->id,
            'position_key' => $rule->position_key,
            'position_label' => RevenueBonusRule::positionLabel($rule->position_key),
            'year' => (int) $rule->year,
            'month' => (int) $rule->month,
            'revenue_from' => (int) $rule->revenue_from,
            'revenue_to' => (int) $rule->revenue_to,
            'bonus_percent' => (float) $rule->bonus_percent,
            'bonus_amount' => (int) $rule->bonus_amount,
            'locked' => (bool) $rule->locked,
            'updated_at' => optional($rule->updated_at)->toDateTimeString(),
            'updated_by' => $rule->updatedBy?->name,
            'sort_order' => (int) $rule->sort_order,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function exportRewardRules(array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['STT', 'Chức vụ', 'Tháng', 'Doanh số từ', 'Doanh số đến nhỏ hơn', '% thưởng', 'Tiền thưởng', 'Chốt dữ liệu']);
            foreach ($rows as $index => $row) {
                fputcsv($out, [
                    $index + 1,
                    $row['position_label'],
                    $row['month'].'/'.$row['year'],
                    $row['revenue_from'],
                    $row['revenue_to'],
                    $row['bonus_percent'],
                    $row['bonus_amount'],
                    $row['locked'] ? 'Đã chốt' : 'Chưa chốt',
                ]);
            }
            fclose($out);
        }, 'thiet-lap-thuong-theo-doanh-so.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ok(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
