<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductRepository
{
    /** Danh sách sản phẩm cho bộ lọc / select. */
    public function options(): Collection
    {
        return Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'parent_id']);
    }

    /** Sản phẩm cha (không phải biến thể). */
    public function parentProducts(): Collection
    {
        return Product::query()->whereNull('parent_id')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Select sản phẩm cha kèm SKU, loại trừ chính nó khi sửa.
     *
     * @return list<array{id: int, name: string}>
     */
    public function parentOptionsWithSku(?int $excludeId = null): array
    {
        return Product::query()
            ->whereNull('parent_id')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->sku ? $p->name.' ('.$p->sku.')' : $p->name,
            ])
            ->all();
    }

    /**
     * Select sản phẩm (mọi cấp) hiển thị kèm SKU.
     *
     * @return list<array{id: int, name: string}>
     */
    public function optionsWithSkuLabel(): array
    {
        return Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->sku ? $p->name.' ('.$p->sku.')' : $p->name,
            ])
            ->all();
    }

    /** Danh sách sản phẩm kèm cha + số biến thể cho màn quản trị. */
    public function allWithParentAndVariantCount(): Collection
    {
        return Product::query()
            ->with('parent:id,name')
            ->withCount('children')
            ->orderBy('name')
            ->get();
    }
}
