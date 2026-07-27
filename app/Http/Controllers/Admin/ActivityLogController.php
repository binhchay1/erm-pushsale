<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Support\ActivityLogger;
use App\Support\ActivityLogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogRepository $logs,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'subject_type' => $request->input('subject_type'),
            'search' => $request->input('search'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $perPage = max(10, min(100, $request->integer('per_page', 25)));

        $paginator = $this->logs->paginate($filters, $perPage);

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => [
                'data' => collect($paginator->items())->map(fn ($log) => $this->presentList($log))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                ...$filters,
                'per_page' => $perPage,
            ],
            'actionOptions' => $this->actionOptions(),
            'subjectTypeOptions' => $this->subjectTypeOptions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function show(int $activityLog): RedirectResponse
    {
        // Chi tiết đã mở bằng modal trong trang danh sách. Không render một page
        // Inertia riêng để tránh lỗi manifest nếu production chưa build lại.
        abort_unless($this->logs->find($activityLog), 404);

        return redirect()->route('admin.activity-logs.index', ['focus' => $activityLog]);
    }

    /** @return array<string, mixed> */
    private function presentList(\App\Models\ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => ActivityLogPresenter::actionLabel($log->action),
            'summary' => ActivityLogPresenter::summary($log),
            'details' => ActivityLogPresenter::details($log),
            'meta_details' => ActivityLogPresenter::metaDetails($log),
            'raw_properties' => ActivityLogPresenter::rawProperties($log),
            'subject_type_label' => ActivityLogPresenter::subjectTypeLabel((string) $log->subject_type),
            'subject_label' => $log->subject_label,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'actor_name' => $log->actor?->name ?? __('activity.system_actor'),
            'actor_email' => $log->actor?->email,
            'created_at' => $log->created_at
                ?->timezone(config('app.timezone'))
                ->format('d/m/Y H:i:s'),
            'ip_address' => $log->ip_address,
            'properties' => $log->properties ?? [],
            'user_agent' => $log->user_agent,
        ];
    }

    /** @return array<string, mixed> */
    private function presentDetail(\App\Models\ActivityLog $log): array
    {
        return [
            ...$this->presentList($log),
            'properties' => $log->properties ?? [],
            'user_agent' => $log->user_agent,
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->email,
                'role' => $log->actor->role->value,
            ] : null,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function actionOptions(): array
    {
        $actions = [
            ActivityLogger::USER_CREATED,
            ActivityLogger::USER_UPDATED,
            ActivityLogger::CAMPAIGN_CREATED,
            ActivityLogger::CAMPAIGN_UPDATED,
            ActivityLogger::CAMPAIGN_DELETED,
            ActivityLogger::CAMPAIGN_APPROVED,
            ActivityLogger::CAMPAIGN_REJECTED,
            ActivityLogger::ORDER_CLOSED,
            ActivityLogger::ORDER_UPDATED,
            ActivityLogger::ORDER_CALL_LOGGED,
            ActivityLogger::INVENTORY_MOVEMENT_APPROVED,
            ActivityLogger::LEAD_INGESTED,
            ActivityLogger::MARKETING_DAILY_METRICS_UPDATED,
            ActivityLogger::DATA_FILTER_SEARCHED,
            ActivityLogger::AUTH_LOGIN_SUCCESS,
            ActivityLogger::AUTH_LOGIN_FAILED,
            ActivityLogger::AUTH_LOGIN_BLOCKED,
            ActivityLogger::AUTH_LOGOUT,
            'customer360.campaign_created',
            'customer360.campaign_customers_attached',
            'customer360.segments_updated',
            'customer.data_viewed',
        ];

        return collect($actions)->map(fn (string $action) => [
            'value' => $action,
            'label' => ActivityLogPresenter::actionLabel($action),
        ])->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function subjectTypeOptions(): array
    {
        return [
            ['value' => 'marketing_source', 'label' => __('activity.subjects.campaign')],
            ['value' => 'user', 'label' => __('activity.subjects.user')],
            ['value' => 'order', 'label' => __('activity.subjects.order')],
            ['value' => 'lead_ingestion', 'label' => __('activity.subjects.lead')],
            ['value' => 'warehouse_inventory_movement', 'label' => __('activity.subjects.inventory')],
            ['value' => 'customer360', 'label' => __('activity.subjects.customer360')],
            ['value' => 'report', 'label' => __('activity.subjects.report')],
        ];
    }
}
