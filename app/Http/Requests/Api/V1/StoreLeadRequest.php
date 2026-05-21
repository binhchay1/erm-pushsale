<?php

namespace App\Http\Requests\Api\V1;

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
            'phone' => ['required', 'string', 'max:20'],
            'product' => ['sometimes', 'string', 'max:255'],
            'utm_source' => ['sometimes', 'string', 'max:120'],
            'utm_campaign' => ['sometimes', 'string', 'max:120'],
            'submission_id' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
