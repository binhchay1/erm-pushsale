<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role === UserRole::Admin || $role === UserRole::Marketing;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['nullable', 'exists:products,id'],
            'marketer_user_id' => ['nullable', 'exists:users,id'],
            'ad_channel' => ['nullable', 'string', 'max:80'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['required', 'string', 'max:120'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'budget' => $this->input('budget') ?: 0,
        ]);
    }
}
