<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingPartnerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

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
            'settings.extra_services' => ['nullable', 'array'],
            'settings.extra_services.*' => ['nullable', 'string', 'max:80'],
        ];
    }
}
