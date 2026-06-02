<?php

namespace App\Contracts\Integrations;

use Illuminate\Http\Request;

/**
 * Chuẩn hóa payload từ từng nền tảng → cấu trúc lead thống nhất.
 */
interface LeadPayloadNormalizer
{
    public function platform(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   external_id: ?string,
     *   customer_name: ?string,
     *   customer_phone: string,
     *   product_interest: ?string,
     *   utm_source: ?string,
     *   utm_campaign: ?string,
     * }
     */
    public function normalize(array $payload): array;

    /** Xác minh webhook (GET challenge hoặc POST signature). */
    public function verifyWebhook(Request $request): bool;
}
