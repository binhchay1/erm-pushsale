<?php

namespace App\Services\Leads;

use App\Support\VietnamesePhone;

/**
 * Làm sạch & kiểm tra dữ liệu lead trước khi vào hệ thống.
 * Mục tiêu: chống rác / spam / bot, chuẩn hóa SĐT Việt Nam, giới hạn độ dài.
 */
class LeadSanitizer
{
    /**
     * Chuẩn hóa SĐT về dạng 0XXXXXXXXX. Trả null nếu không phải SĐT di động VN hợp lệ.
     */
    public function normalizePhone(?string $raw): ?string
    {
        return VietnamesePhone::normalize($raw);
    }

    /**
     * Bỏ HTML, gộp khoảng trắng, cắt theo độ dài tối đa.
     */
    public function cleanText(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        if ($clean === '') {
            return null;
        }

        return mb_substr($clean, 0, max(1, $max));
    }

    /**
     * Form có trường bẫy bot (honeypot) được điền → là bot.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hasHoneypot(array $payload): bool
    {
        foreach ((array) config('saleops.lead_intake.honeypot_fields', []) as $field) {
            if (filled($payload[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heuristic spam đơn giản: nhiều link, từ khóa spam.
     */
    public function looksLikeSpam(?string $name, ?string $message): bool
    {
        $haystack = mb_strtolower(trim(($name ?? '').' '.($message ?? '')));

        if ($haystack === '') {
            return false;
        }

        if (preg_match_all('#https?://#i', $haystack) >= 2) {
            return true;
        }

        return (bool) preg_match(
            '/\b(viagra|casino|backlink|bitcoin|crypto|loan|free money|kiếm tiền nhanh|vay nhanh)\b/iu',
            $haystack,
        );
    }

    /**
     * Payload thô vượt giới hạn kích thước.
     *
     * @param  array<string, mixed>  $payload
     */
    public function exceedsPayloadLimit(array $payload): bool
    {
        $max = (int) config('saleops.lead_intake.max_payload_bytes', 65536);

        return strlen((string) json_encode($payload)) > $max;
    }
}
