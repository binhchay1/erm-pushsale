<?php

namespace App\Services\Inbound;

use App\Enums\InboundEventSource;
use App\Enums\InboundEventStatus;
use App\Models\InboundEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InboundEventRecorder
{
    /**
     * Lưu payload thô ngay khi nhận webhook — trước khi verify/queue.
     * Đảm bảo không mất dữ liệu nếu job lỗi hoặc worker chết.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Request $request,
        InboundEventSource $source,
        ?string $channel,
        ?int $companyId,
        array $payload,
    ): InboundEvent {
        $storedPayload = app(\App\Services\Leads\LeadSanitizer::class)->exceedsPayloadLimit($payload)
            ? ['_truncated' => true, '_keys' => array_slice(array_keys($payload), 0, 100)]
            : $payload;

        return InboundEvent::query()->create([
            'company_id' => $companyId,
            'source' => $source,
            'channel' => $channel,
            'status' => InboundEventStatus::Received,
            'payload' => $storedPayload,
            'headers' => $this->captureHeaders($request),
            'ip_address' => $request->ip(),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }

    /** @return array<string, string> */
    private function captureHeaders(Request $request): array
    {
        $headers = [];
        $sensitive = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-signature', 'x-webhook-signature'];

        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = in_array(strtolower($key), $sensitive, true)
                ? '[REDACTED]'
                : (is_array($values) ? ($values[0] ?? '') : (string) $values);
        }

        return $headers;
    }
}
