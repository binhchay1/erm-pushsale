<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIntegrationRequest extends FormRequest
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
            'credentials' => ['sometimes', 'array'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'verify_token' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
