<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Marketing;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creatorId = $this->user()->id;
        $campaignId = $this->route('campaign')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('marketing_sources', 'name')
                    ->where(function ($query) use ($creatorId) {
                        $query->where('created_by_user_id', $creatorId)->whereNull('parent_id');
                    })
                    ->ignore($campaignId),
            ],
            'product_id' => ['nullable', 'exists:products,id'],
            'marketer_user_id' => [
                'nullable',
                'exists:users,id',
                Rule::exists('users', 'id')->where('role', UserRole::Marketing->value),
            ],
            'ad_channel' => ['nullable', 'string', 'max:80'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'budget' => $this->input('budget') ?: 0,
            'marketer_user_id' => $this->input('marketer_user_id') ?: $this->user()->id,
            'product_id' => $this->input('product_id') ?: null,
        ]);
    }
}
