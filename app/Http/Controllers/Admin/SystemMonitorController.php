<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InboundEventSource;
use App\Enums\InboundEventStatus;
use App\Http\Controllers\Controller;
use App\Models\InboundEvent;
use App\Services\System\SystemLogReader;
use App\Services\System\SystemHealthSnapshotService;
use App\Services\Reports\ReportConsistencyAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemMonitorController extends Controller
{
    public function index(Request $request, SystemLogReader $logReader, SystemHealthSnapshotService $systemHealth, ReportConsistencyAuditService $reportAudit): Response
    {
        abort_unless($request->user()?->canManagePlatform(), 403);
        $source = $request->query('source');
        $status = $request->query('status');
        $level = $request->query('level');
        $tab = $request->query('tab', 'overview');

        $eventsQuery = InboundEvent::query()
            ->with('company:id,name,slug')
            ->orderByDesc('id');

        if ($source) {
            $eventsQuery->where('source', $source);
        }
        if ($status) {
            $eventsQuery->where('status', $status);
        }

        $events = $eventsQuery->paginate(25)->withQueryString()->through(fn (InboundEvent $e) => [
            'id' => $e->id,
            'source' => $e->source->value,
            'source_label' => $e->source->label(),
            'channel' => $e->channel,
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'company' => $e->company?->name,
            'ip_address' => $e->ip_address,
            'error_message' => $e->error_message,
            'correlation_id' => $e->correlation_id,
            'created_at' => $e->created_at?->format('d/m/Y H:i:s'),
        ]);

        $today = now()->startOfDay();

        $todayQuery = InboundEvent::query()->where('created_at', '>=', $today);

        return Inertia::render('Admin/SystemMonitor/Index', [
            'tab' => $tab,
            'system' => in_array($tab, ['overview', 'queues'], true) ? $systemHealth->snapshot() : null,
            'reportAudit' => $tab === 'reports' ? $reportAudit->snapshot($request->user()) : null,
            'events' => $events,
            'logs' => $tab === 'logs' ? $logReader->tail(250, $level ?: null) : [],
            'stats' => [
                'received_today' => (clone $todayQuery)->count(),
                'processed_today' => (clone $todayQuery)->where('status', InboundEventStatus::Processed)->count(),
                'failed_today' => (clone $todayQuery)
                    ->whereIn('status', [InboundEventStatus::Failed, InboundEventStatus::Rejected])
                    ->count(),
                'rejected_today' => (clone $todayQuery)->where('status', InboundEventStatus::Rejected)->count(),
                'pending' => InboundEvent::query()
                    ->whereIn('status', [InboundEventStatus::Received, InboundEventStatus::Queued])
                    ->count(),
                'top_errors' => (clone $todayQuery)
                    ->whereIn('status', [InboundEventStatus::Failed, InboundEventStatus::Rejected])
                    ->whereNotNull('error_message')
                    ->selectRaw('error_message, COUNT(*) as cnt')
                    ->groupBy('error_message')
                    ->orderByDesc('cnt')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row) => ['message' => $row->error_message, 'count' => (int) $row->cnt])
                    ->all(),
            ],
            'filters' => [
                'source' => $source,
                'status' => $status,
                'level' => $level,
            ],
            'sources' => collect(InboundEventSource::cases())
                ->map(fn (InboundEventSource $s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
            'statuses' => collect(InboundEventStatus::cases())
                ->map(fn (InboundEventStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
            'logLevels' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'],
        ]);
    }

    public function show(Request $request, InboundEvent $inboundEvent): Response
    {
        abort_unless($request->user()?->canManagePlatform(), 403);

        $inboundEvent->load('company:id,name,slug');

        return Inertia::render('Admin/SystemMonitor/Show', [
            'event' => [
                'id' => $inboundEvent->id,
                'source' => $inboundEvent->source->value,
                'source_label' => $inboundEvent->source->label(),
                'channel' => $inboundEvent->channel,
                'status' => $inboundEvent->status->value,
                'status_label' => $inboundEvent->status->label(),
                'company' => $inboundEvent->company?->name,
                'ip_address' => $inboundEvent->ip_address,
                'error_message' => $inboundEvent->error_message,
                'correlation_id' => $inboundEvent->correlation_id,
                'http_status' => $inboundEvent->http_status,
                'headers' => $inboundEvent->headers ?? [],
                'payload' => $inboundEvent->payload ?? [],
                'created_at' => $inboundEvent->created_at?->format('d/m/Y H:i:s'),
                'processed_at' => $inboundEvent->processed_at?->format('d/m/Y H:i:s'),
            ],
        ]);
    }
}
