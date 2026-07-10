<?php

namespace App\Services\Leads;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;

class LeadPayloadValidator
{
    public function __construct(
        protected LeadSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, errors: array<string, string>, normalized: ?array<string, mixed>}
     */
    public function validate(LeadPayloadNormalizer $driver, array $payload): array
    {
        $errors = [];

        if ($this->sanitizer->exceedsPayloadLimit($payload)) {
            $errors['payload'] = __('messages.lead_intake.payload_too_large');
        }

        if ($this->sanitizer->hasHoneypot($payload)) {
            $errors['honeypot'] = __('messages.lead_intake.honeypot');
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'normalized' => null];
        }

        $normalized = $driver->normalize($payload);
        $phoneRaw = $normalized['customer_phone'] ?? null;

        if (! is_string($phoneRaw) || trim($phoneRaw) === '') {
            $errors['phone'] = __('messages.lead_intake.phone_required');
        } else {
            $phone = $this->sanitizer->normalizePhone($phoneRaw);

            if (! $phone) {
                $errors['phone'] = __('messages.lead_intake.invalid_phone');
            } else {
                $normalized['customer_phone'] = $phone;
            }
        }

        $name = $this->sanitizer->cleanText(
            $normalized['customer_name'] ?? null,
            (int) config('saleops.lead_intake.max_name_length', 100),
        );

        $message = array_key_exists('message', $normalized)
            ? $this->sanitizer->cleanText(
                $normalized['message'],
                (int) config('saleops.lead_intake.max_message_length', 1000),
            )
            : null;

        if ($this->sanitizer->looksLikeSpam($name, $message)) {
            $errors['content'] = __('messages.lead_intake.spam_detected');
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'normalized' => null];
        }

        $normalized['customer_name'] = $name ?? __('messages.lead_intake.guest_name');

        return ['valid' => true, 'errors' => [], 'normalized' => $normalized];
    }

    /**
     * Validate gói upsell. Trang cảm ơn có thể không hỏi lại SĐT, nhưng khi đó
     * bắt buộc phải có session/client-ref/order-ref để liên kết chính xác.
     *
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, errors: array<string, string>, normalized: ?array<string, mixed>}
     */
    public function validateUpsell(LeadPayloadNormalizer $driver, array $payload): array
    {
        $errors = [];

        if ($this->sanitizer->exceedsPayloadLimit($payload)) {
            $errors['payload'] = __('messages.lead_intake.payload_too_large');
        }

        if ($this->sanitizer->hasHoneypot($payload)) {
            $errors['honeypot'] = __('messages.lead_intake.honeypot');
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'normalized' => null];
        }

        $normalized = $driver->normalize($payload);
        $phoneRaw = $normalized['customer_phone'] ?? null;
        $phone = is_string($phoneRaw) ? $this->sanitizer->normalizePhone($phoneRaw) : null;

        $hasReference = filled($normalized['parent_ref'] ?? null)
            || filled($payload['session_id'] ?? null)
            || filled($payload['session_key'] ?? null)
            || filled($payload['saleops_session'] ?? null)
            || filled($payload['saleops_client_ref'] ?? null);

        if (! $phone && ! $hasReference) {
            $errors['identity'] = __('messages.lead_intake.upsell_identity_required');
        } elseif ($phone) {
            $normalized['customer_phone'] = $phone;
        }

        $name = $this->sanitizer->cleanText(
            $normalized['customer_name'] ?? null,
            (int) config('saleops.lead_intake.max_name_length', 100),
        );
        $message = array_key_exists('message', $normalized)
            ? $this->sanitizer->cleanText(
                $normalized['message'],
                (int) config('saleops.lead_intake.max_message_length', 1000),
            )
            : null;

        if ($this->sanitizer->looksLikeSpam($name, $message)) {
            $errors['content'] = __('messages.lead_intake.spam_detected');
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'normalized' => null];
        }

        return ['valid' => true, 'errors' => [], 'normalized' => $normalized];
    }

    public function requiresSyncValidation(IntegrationPlatform $platform): bool
    {
        // Facebook leadgen webhook thường chỉ gửi leadgen_id — không có SĐT trong payload ban đầu.
        return $platform !== IntegrationPlatform::Facebook;
    }
}
