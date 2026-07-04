<?php

namespace App\Http\Requests\Admin;

use App\Enums\CampaignLeadAllocation;
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
            // Landing bắt buộc có sản phẩm — thiếu thì không duyệt & không chia số được.
            'product_id' => ['required', 'exists:products,id'],
            'marketer_user_id' => [
                'nullable',
                'exists:users,id',
                Rule::exists('users', 'id')->where('role', UserRole::Marketing->value),
            ],
            'ad_channel' => ['nullable', 'string', 'max:80'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'js_tracking_enabled' => ['boolean'],
            'lead_allocation' => ['nullable', Rule::enum(CampaignLeadAllocation::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required' => __('messages.campaign_approval.product_required'),
            'product_id.exists' => __('messages.campaign_approval.product_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'js_tracking_enabled' => $this->boolean('js_tracking_enabled', false),
            'budget' => $this->input('budget') ?: 0,
            'marketer_user_id' => $this->input('marketer_user_id') ?: $this->user()->id,
            'product_id' => $this->input('product_id') ?: null,
            'lead_allocation' => $this->input('lead_allocation') ?: CampaignLeadAllocation::Inherit->value,
        ]);
    }
}
