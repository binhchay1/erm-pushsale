<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

use App\Models\Pushsale\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkShiftController extends BasePushsalePageController
{
    protected string $pageCode = '1.2.3';

    public function saveSchedule(Request $request): JsonResponse
    {
        $this->authorizePage($request);

        $validated = $request->validate([
            'shifts' => ['required', 'array', 'size:3'],
            'shifts.*.name' => ['required', 'string', 'in:Ca 1,Ca 2,Ca 3'],
            'shifts.*.from_hour' => ['required', 'integer', 'between:0,24'],
            'shifts.*.to_hour' => ['required', 'integer', 'between:0,24'],
        ]);

        $shifts = collect($validated['shifts'])->sortBy('name')->values();
        $this->assertNoOverlap($shifts->all());

        DB::transaction(function () use ($shifts, $request): void {
            foreach ($shifts as $shift) {
                $disabled = (int) $shift['from_hour'] === 0 && (int) $shift['to_hour'] === 0;
                $model = WorkShift::query()->firstOrNew(['name' => $shift['name']]);
                $model->fill([
                    'from_hour' => sprintf('%02d:00:00', min(23, (int) $shift['from_hour'])),
                    'to_hour' => sprintf('%02d:00:00', min(23, (int) $shift['to_hour'])),
                    'is_active' => ! $disabled,
                    'updated_by_user_id' => $request->user()->id,
                ]);
                if (! $model->exists) {
                    $model->created_by_user_id = $request->user()->id;
                }
                $model->save();
            }
        });

        return response()->json(['ok' => true, 'message' => 'Đã cập nhật ba ca làm việc.']);
    }

    /** @param array<int, array{name:string,from_hour:int,to_hour:int}> $shifts */
    private function assertNoOverlap(array $shifts): void
    {
        $occupied = [];
        foreach ($shifts as $shift) {
            $from = (int) $shift['from_hour'];
            $to = (int) $shift['to_hour'];
            if ($from === 0 && $to === 0) {
                continue;
            }
            if ($from === $to) {
                throw ValidationException::withMessages(['shifts' => "{$shift['name']} có giờ bắt đầu và kết thúc trùng nhau."]);
            }

            $hours = $to > $from
                ? range($from, $to - 1)
                : array_merge(range($from, 23), $to > 0 ? range(0, $to - 1) : []);
            foreach ($hours as $hour) {
                if (isset($occupied[$hour])) {
                    throw ValidationException::withMessages([
                        'shifts' => "{$shift['name']} bị trùng khung giờ với {$occupied[$hour]}.",
                    ]);
                }
                $occupied[$hour] = $shift['name'];
            }
        }
    }
}
