<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InboundEventSource;
use App\Enums\InboundEventStatus;
use App\Http\Controllers\Controller;
use App\Models\InboundEvent;
use App\Services\System\SystemLogReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemMonitorController extends Controller
{
    public function index(Request $request, SystemLogReader $logReader): Response
    {
        abort_unless($request->user()?->canManagePlatform(), 403);
        $source = $request->query('source');
        $status = $request->query('status');
        $level = $request->query('level');
        $tab = $request->query('tab', 'events');

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

        return Inertia::render('Admin/SystemMonitor/Index', [
            'tab' => $tab,
            'events' => $events,
            'logs' => $tab === 'logs' ? $logReader->tail(250, $level ?: null) : [],
            'stats' => [
                'received_today' => InboundEvent::query()->where('created_at', '>=', $today)->count(),
                'failed_today' => InboundEvent::query()
                    ->where('created_at', '>=', $today)
                    ->whereIn('status', [InboundEventStatus::Failed, InboundEventStatus::Rejected])
                    ->count(),
                'pending' => InboundEvent::query()
                    ->whereIn('status', [InboundEventStatus::Received, InboundEventStatus::Queued])
                    ->count(),
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
