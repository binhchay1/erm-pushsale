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
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($productId)],
            'unit_price' => ['required', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'exists:products,id', Rule::notIn([$productId])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
