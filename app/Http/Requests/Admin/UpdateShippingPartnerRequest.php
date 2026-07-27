<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShippingPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->isAdmin() || $user?->canManagePlatform());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'integration_mode' => ['sometimes', Rule::in(['manual', 'direct', 'direct_generic', 'generic', 'aggregator'])],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:4000'],
            'settings' => ['sometimes', 'array'],
            'settings.pickup_mode' => ['nullable', Rule::in(['carrier_pickup', 'dropoff', 'manual'])],
            'settings.inspection_mode' => ['nullable', Rule::in(['none', 'view_only', 'open_and_try'])],
            'settings.goods_type' => ['nullable', Rule::in(['parcel', 'document', 'food', 'fragile'])],
            'settings.insurance_enabled' => ['nullable', 'boolean'],
            'settings.allow_partial_delivery' => ['nullable', 'boolean'],
            'settings.auto_create_waybill' => ['nullable', 'boolean'],
            'settings.auto_restock_return' => ['nullable', 'boolean'],
            'settings.use_carrier_cod' => ['nullable', 'boolean'],
            'settings.fixed_receiver_phone' => ['nullable', 'string', 'max:30'],
            'settings.sender_profile_id' => ['nullable', 'string', 'max:100'],
            'settings.callback_url_enabled' => ['nullable', 'boolean'],
            'settings.allow_insurance_order' => ['nullable', 'boolean'],
            'settings.extra_money' => ['nullable', 'string', 'max:100'],
            'settings.discount_code' => ['nullable', 'string', 'max:100'],
            'settings.pickup_time' => ['nullable', 'string', 'max:100'],
            'settings.order_label' => ['nullable', 'string', 'max:150'],
            'settings.failed_delivery_collect_fee' => ['nullable', 'string', 'max:100'],
            'settings.api_version' => ['nullable', 'string', 'max:30'],
            'settings.otp' => ['nullable', 'string', 'max:30'],
            'settings.extra_services' => ['nullable', 'array'],
            'settings.extra_services.*' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provider = (string) $this->route('provider');
            $fields = config("shipping_partners.providers.{$provider}.fields", []);
            if (! is_array($fields) || $fields === []) {
                return;
            }

            $incoming = $this->input('credentials', []);
            $incoming = is_array($incoming) ? $incoming : [];
            $stored = [];

            try {
                $connection = \App\Models\ShippingPartnerConnection::query()
                    ->where('provider', $provider)
                    ->first();
                $stored = is_array($connection?->credentials) ? $connection->credentials : [];
            } catch (\Throwable) {
                $stored = [];
            }

            foreach ($fields as $key => $meta) {
                if (! is_array($meta) || ! ($meta['required'] ?? false)) {
                    continue;
                }

                $label = (string) ($meta['label'] ?? $key);
                $value = $incoming[$key] ?? null;
                $hasIncoming = filled($value);
                $isSecret = (bool) ($meta['secret'] ?? false);
                $hasStoredSecret = $isSecret && filled($stored[$key] ?? null);

                if (! $hasIncoming && ! $hasStoredSecret) {
                    $validator->errors()->add("credentials.{$key}", "{$label} bắt buộc.");
                }
            }
        });
    }
}
