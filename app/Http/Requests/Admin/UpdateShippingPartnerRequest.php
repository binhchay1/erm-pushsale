<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
