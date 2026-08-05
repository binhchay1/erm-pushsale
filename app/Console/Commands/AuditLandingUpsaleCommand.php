<?php

namespace App\Console\Commands;

use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use App\Support\MarketingPacketMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class AuditLandingUpsaleCommand extends Command
{
    protected $signature = 'landing:upsale-audit
        {--from= : From date/datetime, filtered by lead_ingestions.created_at}
        {--to= : To date/datetime, filtered by lead_ingestions.created_at}
        {--source-id= : Marketing source id}
        {--company-id= : Company id}
        {--json : Print JSON}';

    protected $description = 'Audit landing/upsale packets, countable marketing contacts, and invalid upsale states.';

    public function handle(): int
    {
        $from = $this->parseDateOption('from', false);
        $to = $this->parseDateOption('to', true);
        $sourceId = $this->positiveIntOption('source-id');
        $companyId = $this->positiveIntOption('company-id');

        $base = $this->baseQuery($from, $to, $sourceId, $companyId);

        $countableAll = $this->countableCount($base);
        $countableNew = $this->countableCount($base, 'new');
        $countableReturning = $this->countableCount($base, 'returning');
        $validUpsale = $this->validUpsaleQuery($base)->count();

        $invalid = [
            'processed_upsale_without_order' => $this->upsaleQuery($base)
                ->where('status', LeadIngestionStatus::Processed->value)
                ->whereNull('order_id')
                ->whereNull('related_order_id')
                ->whereDoesntHave('parentIngestion.order')
                ->whereDoesntHave('parentIngestion.relatedOrder')
                ->count(),
            'pending_upsale' => $this->upsaleQuery($base)
                ->whereIn('status', [LeadIngestionStatus::Gathering->value, LeadIngestionStatus::Pending->value])
                ->count(),
            'review_upsale' => $this->upsaleQuery($base)
                ->where(function (Builder $query): void {
                    $query->where('requires_review', true)
                        ->orWhere('status', LeadIngestionStatus::NeedsReview->value);
                })
                ->count(),
            'duplicate_or_failed_upsale' => $this->upsaleQuery($base)
                ->whereIn('status', [LeadIngestionStatus::Duplicate->value, LeadIngestionStatus::Failed->value])
                ->count(),
        ];

        $byType = $this->baseQuery($from, $to, $sourceId, $companyId)
            ->selectRaw("COALESCE(packet_type, 'lead') as packet_type, COUNT(*) as total")
            ->groupByRaw("COALESCE(packet_type, 'lead')")
            ->orderBy('packet_type')
            ->pluck('total', 'packet_type')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $byStatus = $this->baseQuery($from, $to, $sourceId, $companyId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $result = [
            'scope' => [
                'from' => $from?->toDateTimeString(),
                'to' => $to?->toDateTimeString(),
                'source_id' => $sourceId,
                'company_id' => $companyId,
            ],
            'marketing_contacts' => [
                'all' => $countableAll,
                'new' => $countableNew,
                'returning' => $countableReturning,
                'partition_ok' => $countableAll === $countableNew + $countableReturning,
            ],
            'upsale' => [
                'valid_counted' => $validUpsale,
                'invalid_or_review_only' => $invalid,
            ],
            'packets_by_type' => $byType,
            'packets_by_status' => $byStatus,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $result['marketing_contacts']['partition_ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Landing / upsale audit');
        $this->line('Scope: '.json_encode($result['scope'], JSON_UNESCAPED_UNICODE));
        $this->table(['Metric', 'Value'], [
            ['Marketing contact - tất cả', $countableAll],
            ['Marketing contact - khách mới', $countableNew],
            ['Marketing contact - khách cũ', $countableReturning],
            ['Tất cả = mới + cũ', $result['marketing_contacts']['partition_ok'] ? 'OK' : 'LỆCH'],
            ['Upsale hợp lệ được tính', $validUpsale],
        ]);
        $this->table(['Invalid / review-only upsale state', 'Count'], collect($invalid)->map(fn ($value, $key): array => [$key, $value])->values()->all());
        $this->table(['Packet type', 'Count'], collect($byType)->map(fn ($value, $key): array => [$key, $value])->values()->all());
        $this->table(['Status', 'Count'], collect($byStatus)->map(fn ($value, $key): array => [$key, $value])->values()->all());

        if (! $result['marketing_contacts']['partition_ok']) {
            $this->error('Marketing contact partition mismatch: all must equal new + returning.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function parseDateOption(string $name, bool $endOfDay): ?Carbon
    {
        $value = $this->option($name);
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = Carbon::parse($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))
            ? ($endOfDay ? $date->endOfDay() : $date->startOfDay())
            : $date;
    }

    private function positiveIntOption(string $name): ?int
    {
        $value = (int) $this->option($name);

        return $value > 0 ? $value : null;
    }

    private function baseQuery(?Carbon $from, ?Carbon $to, ?int $sourceId, ?int $companyId): Builder
    {
        return LeadIngestion::query()
            ->when($from && $to, fn (Builder $query): Builder => $query->whereBetween('created_at', [$from, $to]))
            ->when($from && ! $to, fn (Builder $query): Builder => $query->where('created_at', '>=', $from))
            ->when(! $from && $to, fn (Builder $query): Builder => $query->where('created_at', '<=', $to))
            ->when($sourceId, fn (Builder $query): Builder => $query->where('marketing_source_id', $sourceId))
            ->when($companyId, function (Builder $query) use ($companyId): Builder {
                return $query->where(function (Builder $company) use ($companyId): void {
                    $company->whereHas('marketingSource', fn (Builder $source): Builder => $source->where('company_id', $companyId))
                        ->orWhereHas('order', fn (Builder $order): Builder => $order->where('company_id', $companyId))
                        ->orWhereHas('relatedOrder', fn (Builder $order): Builder => $order->where('company_id', $companyId));
                });
            });
    }

    private function countableCount(Builder $base, ?string $customerType = null): int
    {
        $query = clone $base;
        MarketingPacketMetrics::applyCountableScope($query);
        MarketingPacketMetrics::applyCustomerTypeScope($query, $customerType);

        return (int) $query->count();
    }

    private function upsaleQuery(Builder $base): Builder
    {
        return (clone $base)->whereIn('packet_type', MarketingPacketMetrics::upsaleTypes());
    }

    private function validUpsaleQuery(Builder $base): Builder
    {
        $query = clone $base;
        MarketingPacketMetrics::applyValidUpsaleScope($query);

        return $query;
    }
}
