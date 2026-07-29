<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Data\Customers\CustomerProfileFilterData;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Jobs\RecalculateCustomerSegmentsJob;
use App\Models\Order;
use App\Models\Pushsale\CustomerCareCampaign;
use App\Services\Customers\CareCampaignService;
use App\Services\Customers\CustomerProfileOptionsService;
use App\Services\Customers\CustomerProfileService;
use App\Services\Customers\CustomerSegmentService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class Customer360ManagementController extends Controller
{
    public function index(
        Request $request,
        CustomerProfileService $profileService,
        CustomerProfileOptionsService $options,
        CareCampaignService $campaigns,
        CustomerSegmentService $segments,
    ): Response {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::View), 403);

        $this->normaliseSearchInput($request);
        $filter = CustomerProfileFilterData::fromRequest($request, $request->user());
        $rows = $this->paginateCustomers($profileService, $filter);

        return Inertia::render('Customers/Management', [
            'filters' => $filter->toArray(),
            'filterOptions' => array_merge($options->build($request->user()), [
                'campaigns' => $campaigns->options(),
                'segments' => $segments->definitions(),
                'customer360Permissions' => [
                    'canManageCampaigns' => (bool) $request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full),
                    'canEditCustomers' => (bool) $request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full),
                    'canDeleteCustomers' => (bool) $request->user()?->isAdmin(),
                ],
            ]),
            'report' => $rows,
            'routeUrl' => '/'.$request->path(),
            'activeMenuCode' => '3.1',
            'pageTitle' => 'Khách hàng 360',
        ]);
    }

    public function createCampaign(Request $request, CareCampaignService $campaigns): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'repeat_days' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', 'in:draft,active,paused,completed'],
            'filters' => ['nullable', 'array'],
            'customer_condition' => ['nullable', 'array'],
            'customer_ids' => ['nullable', 'array', 'max:2000'],
            'customer_ids.*' => ['integer'],
        ]);

        try {
            $campaign = $campaigns->create(array_merge($validated, [
                'customer_condition' => [
                    'source' => 'customer360_filter',
                    'filters' => $validated['filters'] ?? $validated['customer_condition']['filters'] ?? [],
                ],
            ]), $request->user());

            ActivityLogger::log('customer360.campaign_created', $campaign, [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
            ], 'Tạo chiến dịch chăm sóc từ bộ lọc khách hàng 360', $request->user());

            return $this->saved($request, 'Đã tạo chiến dịch chăm sóc khách hàng.', ['campaign' => $campaign]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Không tạo được chiến dịch. Vui lòng kiểm tra dữ liệu và thử lại.'], 422);
        }
    }

    public function attachCampaign(Request $request, CareCampaignService $campaigns): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $validated = $request->validate([
            'campaign_id' => ['required', 'integer'],
            'customer_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'customer_ids.*' => ['integer'],
        ]);

        try {
            $campaign = CustomerCareCampaign::query()->findOrFail((int) $validated['campaign_id']);
            $campaign = $campaigns->attachOrders($campaign, $validated['customer_ids'], $request->user());

            ActivityLogger::log('customer360.campaign_customers_attached', $campaign, [
                'campaign_id' => $campaign->id,
                'attached_count' => count($validated['customer_ids']),
            ], 'Thêm khách hàng vào chiến dịch chăm sóc', $request->user());

            return $this->saved($request, 'Đã thêm khách hàng vào chiến dịch.', ['campaign' => $campaign]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Không thêm được khách hàng vào chiến dịch.'], 422);
        }
    }

    public function saveSegments(Request $request, CustomerSegmentService $segments): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $validated = $request->validate([
            'segments' => ['required', 'array', 'max:50'],
            'segments.*.name' => ['required', 'string', 'max:120'],
            'segments.*.color' => ['nullable', 'string', 'max:20'],
            'segments.*.min_successful_order_value' => ['nullable', 'integer', 'min:0'],
        ]);

        $saved = $segments->saveDefinitions($validated['segments']);

        ActivityLogger::log('customer360.segments_updated', null, [
            'segments_count' => count($saved),
        ], 'Cập nhật phân loại khách hàng 360', $request->user());

        return $this->saved($request, 'Đã cập nhật phân loại khách hàng.', ['segments' => $saved]);
    }

    public function recalculateSegments(Request $request, CustomerSegmentService $segments): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::Full), 403);

        $companyId = $request->user()?->company_id ? (int) $request->user()->company_id : null;
        $sync = $request->boolean('sync', true);

        if ($sync) {
            $result = $segments->recalculate($companyId);
        } else {
            RecalculateCustomerSegmentsJob::dispatch($companyId);
            $result = ['queued' => true];
        }

        ActivityLogger::log('customer360.segments_recalculated', null, $result, 'Tính toán phân loại khách hàng 360', $request->user());

        return $this->saved($request, 'Hệ thống đã ghi nhận và tính toán phân loại khách hàng.', $result);
    }

    public function export(Request $request, CustomerProfileService $profileService): StreamedResponse
    {
        abort_unless($request->user()?->allows(PermissionArea::Customers, PermissionLevel::View), 403);
        $this->normaliseSearchInput($request);
        $filter = CustomerProfileFilterData::fromRequest($request, $request->user());
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->values();

        $rows = $ids->isNotEmpty()
            ? $this->ordersForIds($ids->all())
            : collect($this->paginateCustomers($profileService, $filter, 5000)['rows']['data']);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sale', 'Marketing', 'Mã KH', 'Tên KH', 'Tuổi', 'Số điện thoại', 'Giới tính', 'Lời nhắn', 'Ngày tạo', 'Cập nhật']);
            foreach ($rows as $row) {
                $row = (array) $row;
                fputcsv($out, [
                    $row['saleName'] ?? '',
                    $row['marketingName'] ?? '',
                    $row['customerCode'] ?? '',
                    $row['customerName'] ?? '',
                    $row['age'] ?? '',
                    $row['customerPhone'] ?? '',
                    $row['gender'] ?? '',
                    $row['message'] ?? '',
                    $row['createdAt'] ?? '',
                    $row['updatedAt'] ?? '',
                ]);
            }
            fclose($out);
        }, 'khach-hang-360-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function normaliseSearchInput(Request $request): void
    {
        if (! $request->filled('search') && $request->filled('keyword')) {
            $request->merge(['search' => $request->input('keyword')]);
        }
    }

    /** @return array{rows: array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}} */
    private function paginateCustomers(CustomerProfileService $profileService, CustomerProfileFilterData $filter, ?int $forcedLimit = null): array
    {
        $query = $profileService->filteredQuery($filter)
            ->with([
                'saleUser:id,name,email',
                'marketerUser:id,name,email',
                'marketingSource:id,name,marketer_user_id',
                'marketingSource.marketer:id,name,email',
            ]);

        $phoneExpression = $profileService->normalizedPhoneExpression('orders.customer_phone');
        $grouped = (clone $query)
            ->reorder()
            ->selectRaw('MAX(orders.id) AS latest_order_id')
            ->whereNotNull('orders.customer_phone')
            ->where('orders.customer_phone', '!=', '')
            ->groupByRaw($phoneExpression);

        $perPage = $forcedLimit ?: $filter->perPage;
        /** @var LengthAwarePaginator $paginator */
        $paginator = DB::query()
            ->fromSub($grouped->toBase(), 'customer360_customers')
            ->select('latest_order_id')
            ->orderByDesc('latest_order_id')
            ->paginate($perPage, ['*'], 'page', $forcedLimit ? 1 : $filter->page)
            ->withQueryString();

        $ids = collect($paginator->items())->pluck('latest_order_id')->map(fn ($id) => (int) $id)->values();
        $orders = $this->ordersForIds($ids->all())->keyBy('id');
        $rows = $ids->map(fn (int $id): ?array => $orders->get($id))->filter()->values()->all();

        return [
            'rows' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function ordersForIds(array $ids)
    {
        return Order::query()
            ->with([
                'saleUser:id,name,email',
                'marketerUser:id,name,email',
                'marketingSource:id,name,marketer_user_id',
                'marketingSource.marketer:id,name,email',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'customerCode' => 'KH'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
                'customerName' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'saleName' => $order->saleUser?->name,
                'saleEmail' => $order->saleUser?->email,
                'marketingName' => $order->marketerUser?->name ?? $order->marketingSource?->marketer?->name,
                'marketingEmail' => $order->marketerUser?->email ?? $order->marketingSource?->marketer?->email,
                'age' => null,
                'gender' => null,
                'message' => $order->customer_note,
                'createdAt' => $order->data_arrived_at?->format('d/m/Y H:i'),
                'updatedAt' => $order->updated_at?->format('d/m/Y H:i'),
                'latestOrderCode' => $order->order_code,
            ]);
    }

    private function saved(Request $request, string $message, array $payload = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['message' => $message], $payload));
        }

        return back()->with('success', $message);
    }
}
