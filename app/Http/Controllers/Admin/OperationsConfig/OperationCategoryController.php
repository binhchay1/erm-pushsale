<?php

namespace App\Http\Controllers\Admin\OperationsConfig;

use App\Http\Controllers\Admin\Pushsale\BasePushsalePageController;

use App\Enums\OperationResult;
use App\Models\Pushsale\OperationResultSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OperationCategoryController extends BasePushsalePageController
{
    protected string $pageCode = '1.8.1';

    public function updateResult(Request $request, string $value): JsonResponse|RedirectResponse
    {
        $this->authorizePage($request);

        $allowed = collect(OperationResultSetting::defaultRows())->pluck('value')->all();
        abort_unless(in_array($value, $allowed, true), 404);

        $payload = $request->validate([
            'payload' => ['required', 'array'],
            'payload.label' => ['required', 'string', 'max:255'],
            'payload.closes_order' => ['nullable', 'boolean'],
            'payload.is_active' => ['nullable', 'boolean'],
            'payload.sort_order' => ['nullable', 'integer', 'min:0'],
        ])['payload'];

        OperationResultSetting::ensureDefaults();
        $defaults = collect(OperationResultSetting::defaultRows())->keyBy('value');
        $default = $defaults[$value] ?? [];

        /** @var OperationResultSetting $setting */
        $setting = OperationResultSetting::query()->firstOrNew(['value' => $value]);
        $setting->fill([
            'label' => trim((string) $payload['label']),
            'legacy_id' => (int) ($setting->legacy_id ?: ($default['legacy_id'] ?? 0)),
            'sort_order' => (int) ($payload['sort_order'] ?? $setting->sort_order ?: ($default['sort_order'] ?? 0)),
            'closes_order' => (bool) ($payload['closes_order'] ?? false),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'updated_by_user_id' => $request->user()?->id,
        ]);
        if (! $setting->exists) {
            $setting->created_by_user_id = $request->user()?->id;
        }
        $setting->save();

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'record' => $setting->refresh()->toArray()])
            : back()->with('success', 'Đã cập nhật kết quả tác nghiệp.');
    }
}
