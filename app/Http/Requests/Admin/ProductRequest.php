<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['product', 'combo'])],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($productId)],
            'unit' => ['nullable', 'string', 'max:30'],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_code' => ['nullable', 'string', 'max:50'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'warehouse_location' => ['nullable', 'string', 'max:120'],
            'available_marketing' => ['sometimes', 'boolean'],
            'available_sale' => ['sometimes', 'boolean'],
            'available_care' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:product_categories,id'],
            'parent_id' => ['nullable', 'exists:products,id', Rule::notIn([$productId])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
