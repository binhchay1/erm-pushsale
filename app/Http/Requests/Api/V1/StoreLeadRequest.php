<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\VietnameseMobilePhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new VietnameseMobilePhone],
            'product' => ['sometimes', 'string', 'max:255'],
            'utm_source' => ['sometimes', 'string', 'max:120'],
            'utm_campaign' => ['sometimes', 'string', 'max:120'],
            'submission_id' => ['sometimes', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.required' => __('messages.lead_intake.phone_required'),
        ];
    }
}
