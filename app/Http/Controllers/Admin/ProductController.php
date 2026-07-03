<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly WarehouseRepository $warehouses,
    ) {}

    public function index(): Response
    {
        $products = $this->products->allWithParentAndVariantCount()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type ?? 'product',
                'sku' => $p->sku,
                'unit_price' => (int) $p->unit_price,
                'is_active' => (bool) $p->is_active,
                'parent_name' => $p->parent?->name,
                'variants_count' => (int) $p->children_count,
            ])
            ->values();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        Product::query()->create($data);

        return redirect()->route('admin.products.index')->with('success', __('messages.product_created'));
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type ?? 'product',
                'sku' => $product->sku,
                'unit_price' => (int) $product->unit_price,
                'parent_id' => $product->parent_id,
                'is_active' => (bool) $product->is_active,
            ],
            'parents' => $this->parentOptions(excludeId: $product->id),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', __('messages.product_updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->children()->exists()) {
            return back()->with('error', __('messages.product_has_variants'));
        }

        $this->warehouses->deleteInventoriesOfProduct($product->id);
        $product->delete();

        return back()->with('success', __('messages.product_deleted'));
    }

    /** @return list<array{id: int, name: string}> */
    private function parentOptions(?int $excludeId = null): array
    {
        return $this->products->parentOptionsWithSku($excludeId);
    }
}
