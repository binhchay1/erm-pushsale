<?php

namespace App\Services\Orders;

use App\Jobs\Orders\RecordOrderTraceJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đẩy một mốc hành trình sang queue. Không ghi database và không được làm fail luồng gọi.
 */
class OrderTracePublisher
{
    /** @param array<string, mixed> $event */
    public function publish(array $event): void
    {
        $stage = trim((string) ($event['stage'] ?? ''));
        if ($stage === '') {
            return;
        }

        try {
            $event['stage'] = $stage;
            $event['occurred_at'] = $event['occurred_at'] ?? now()->toIso8601String();
            $event['payload'] = $this->payload($event['payload'] ?? null);
            $event['dedupe_key'] = $this->dedupeKey($event);
            $event['summary'] = $this->clip($event['summary'] ?? null, 255);

            RecordOrderTraceJob::dispatch($event);
        } catch (Throwable $exception) {
            Log::warning('order_trace.publish_failed', [
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $event */
    private function dedupeKey(array $event): string
    {
        $given = trim((string) ($event['dedupe_key'] ?? ''));
        if ($given !== '') {
            return mb_substr($given, 0, 40);
        }

        return substr(hash('sha256', json_encode([
            $event['stage'],
            $event['action'] ?? null,
            $event['order_id'] ?? null,
            $event['order_code'] ?? null,
            $event['external_code'] ?? null,
            $event['gateway_order_id'] ?? null,
            $event['status_code'] ?? null,
            $event['occurred_at'] ?? null,
        ])), 0, 40);
    }

    private function payload(mixed $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) <= 12000) {
            return $payload;
        }

        return [
            '_truncated' => true,
            'bytes' => strlen($json),
        ];
    }

    private function clip(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
