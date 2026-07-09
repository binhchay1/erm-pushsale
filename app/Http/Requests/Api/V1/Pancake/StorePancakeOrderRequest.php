<?php

namespace App\Http\Requests\Api\V1\Pancake;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use Illuminate\Foundation\Http\FormRequest;

class StorePancakeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->isSales()
            || $user->allows(PermissionArea::Leads, PermissionLevel::Full)
            || $user->allows(PermissionArea::Telesale, PermissionLevel::Full)
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string', 'max:255'],
            'order_id' => ['nullable', 'string', 'max:255'],
            'pancake_order_id' => ['nullable', 'string', 'max:255'],
            'shop_id' => ['nullable', 'string', 'max:255'],
            'page_id' => ['nullable', 'string', 'max:255'],
            'page_name' => ['nullable', 'string', 'max:255'],
            'conversation_id' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'shipping_notes' => ['nullable', 'string', 'max:1000'],
            'sale_user_id' => ['nullable', 'integer'],
            'sale_email' => ['nullable', 'email'],
            'marketing_source_id' => ['nullable', 'integer'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.price' => ['nullable'],
            'discount' => ['nullable'],
            'deposit' => ['nullable'],
            'shipping_fee' => ['nullable'],
            'shipping_fee_collected' => ['nullable'],
            'raw' => ['nullable', 'array'],
        ];
    }
}
