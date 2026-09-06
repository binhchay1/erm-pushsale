<?php

namespace App\Http\Requests\Admin;

use App\Rules\VietnameseMobilePhone;
use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Cột `sort_order` / `use_two_level_address` là NOT NULL DEFAULT trong DB,
        // form gửi chuỗi rỗng khi người dùng bỏ trống → phải quy về giá trị mặc định.
        if ($this->has('sort_order')) {
            $sortOrder = $this->input('sort_order');
            $this->merge(['sort_order' => ($sortOrder === null || $sortOrder === '') ? 0 : (int) $sortOrder]);
        }

        if ($this->has('use_two_level_address')) {
            $this->merge(['use_two_level_address' => $this->boolean('use_two_level_address')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new VietnameseMobilePhone],
            'address' => ['nullable', 'string', 'max:255'],
            'pick_province' => ['nullable', 'string', 'max:120'],
            'pick_district' => ['nullable', 'string', 'max:120'],
            'pick_ward' => ['nullable', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'ghtk_pick_address_id' => ['nullable', 'string', 'max:80'],
            'manager_user_id' => ['nullable', 'exists:users,id'],
            'vtp_code' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'use_two_level_address' => ['boolean'],
            'sender_registration_name' => ['nullable', 'string', 'max:255'],
            'sender_print_note' => ['nullable', 'string', 'max:2000'],
            'default_delivery_provinces' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
