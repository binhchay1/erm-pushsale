<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Support\ActivityLogger;
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

        $paginator = $this->logs->paginate($filters);

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => [
                'data' => collect($paginator->items())->map(fn ($log) => $this->presentList($log))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'filters' => $filters,
            'actionOptions' => $this->actionOptions(),
            'subjectTypeOptions' => $this->subjectTypeOptions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function show(int $activityLog): Response
    {
        $log = $this->logs->find($activityLog);
        abort_unless($log, 404);

        return Inertia::render('Admin/ActivityLogs/Show', [
            'log' => $this->presentDetail($log),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentList(\App\Models\ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => $log->actionLabel(),
            'subject_label' => $log->subject_label,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'actor_name' => $log->actor?->name ?? __('activity.system_actor'),
            'actor_email' => $log->actor?->email,
            'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
            'ip_address' => $log->ip_address,
            'properties' => $log->properties ?? [],
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
            ActivityLogger::ORDER_CALL_LOGGED,
            ActivityLogger::INVENTORY_MOVEMENT_APPROVED,
            ActivityLogger::LEAD_INGESTED,
        ];

        return collect($actions)->map(function (string $action) {
            $key = 'activity.actions.'.$action;
            $label = __($key);

            return [
                'value' => $action,
                'label' => $label === $key ? $action : $label,
            ];
        })->all();
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
        ];
    }
}
