<?php

namespace App\Http\Controllers\Admin\Customers;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\Pushsale\CustomerCareCampaign;
use App\Services\Customers\CareCampaignService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class CareCampaignController extends Controller
{
    public function index(Request $request, CareCampaignService $campaigns): Response
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::View), 403);

        $result = $campaigns->paginate($request);

        return Inertia::render('Admin/Customers/CareCampaigns', [
            'pageTitle' => 'Quản lý chiến dịch chăm sóc',
            'activeMenuCode' => '3.2',
            'routeUrl' => '/admin/customers/care-campaigns',
            'filters' => $result['filters'],
            'rows' => $result['rows'],
            'pagination' => $result['meta'],
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'draft', 'label' => 'Nháp'],
                    ['value' => 'active', 'label' => 'Đang chạy'],
                    ['value' => 'paused', 'label' => 'Tạm dừng'],
                    ['value' => 'completed', 'label' => 'Hoàn thành'],
                ],
                'customerTypes' => [
                    ['value' => 'new', 'label' => 'Khách mới'],
                    ['value' => 'returning', 'label' => 'Khách mua lại'],
                ],
            ],
        ]);
    }

    public function store(Request $request, CareCampaignService $campaigns): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'repeat_days' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:draft,active,paused,completed'],
            'customer_condition' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'customer_ids' => ['nullable', 'array', 'max:2000'],
            'customer_ids.*' => ['integer'],
            'order_ids' => ['nullable', 'array', 'max:2000'],
            'order_ids.*' => ['integer'],
        ]);

        try {
            $campaign = $campaigns->create($validated, $request->user());
            ActivityLogger::log('customer360.campaign_created', $campaign, [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
            ], 'Tạo chiến dịch chăm sóc', $request->user());

            return $this->saved($request, 'Đã tạo chiến dịch chăm sóc.', ['campaign' => $campaigns->toRow($campaign)]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Không tạo được chiến dịch.'], 422);
        }
    }

    public function update(Request $request, CustomerCareCampaign $record, CareCampaignService $campaigns): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'repeat_days' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:draft,active,paused,completed'],
            'customer_condition' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
        ]);

        try {
            $campaign = $campaigns->update($record, $validated, $request->user());
            ActivityLogger::log('customer360.campaign_updated', $campaign, [
                'campaign_id' => $campaign->id,
            ], 'Cập nhật chiến dịch chăm sóc', $request->user());

            return $this->saved($request, 'Đã cập nhật chiến dịch.', ['campaign' => $campaigns->toRow($campaign)]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Không cập nhật được chiến dịch.'], 422);
        }
    }

    public function destroy(Request $request, CustomerCareCampaign $record, CareCampaignService $campaigns): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $campaigns->destroy($record);
        ActivityLogger::log('customer360.campaign_deleted', null, [
            'campaign_id' => $record->id,
        ], 'Xóa chiến dịch chăm sóc', $request->user());

        return $this->saved($request, 'Đã xóa chiến dịch.');
    }

    private function saved(Request $request, string $message, array $payload = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['message' => $message], $payload));
        }

        return back()->with('success', $message);
    }
}
