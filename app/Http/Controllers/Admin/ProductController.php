<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use App\Repositories\ProductRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly WarehouseRepository $warehouses,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'active' => (string) $request->query('active', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'marketing' => (string) $request->query('marketing', ''),
            'sale' => (string) $request->query('sale', ''),
            'care' => (string) $request->query('care', ''),
            'vat' => (string) $request->query('vat', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $query = Product::query()
            ->where('type', 'product')
            ->with(['parent:id,name', 'categories:id,name'])
            ->withCount('children');

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }
        if ($filters['active'] !== '') $query->where('is_active', $filters['active'] === '1');
        if ($filters['marketing'] !== '') $query->where('available_marketing', $filters['marketing'] === '1');
        if ($filters['sale'] !== '') $query->where('available_sale', $filters['sale'] === '1');
        if ($filters['care'] !== '') $query->where('available_care', $filters['care'] === '1');
        if ($filters['vat'] !== '') $query->where('vat_code', $filters['vat']);
        if ($filters['category_id'] !== '') {
            $query->whereHas('categories', fn ($category) => $category->whereKey($filters['category_id']));
        }

        match ($filters['sort']) {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderBy('unit_price'),
            'price_desc' => $query->orderByDesc('unit_price'),
            default => $query->latest('id'),
        };

        $products = $query->paginate(20)->withQueryString()->through(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'cost_price' => (int) $product->cost_price,
            'unit_price' => (int) $product->unit_price,
            'vat_percent' => (float) $product->vat_percent,
            'vat_code' => $product->vat_code,
            'price_after_vat' => (int) round($product->unit_price * (1 + ((float) $product->vat_percent / 100))),
            'weight_grams' => (int) $product->weight_grams,
            'is_active' => (bool) $product->is_active,
            'available_marketing' => (bool) $product->available_marketing,
            'available_sale' => (bool) $product->available_sale,
            'available_care' => (bool) $product->available_care,
            'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'category_names' => $product->categories->pluck('name')->implode(', '),
            'updated_at' => $product->updated_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']),
            'attributes' => ProductAttribute::query()->with('values:id,product_attribute_id,name')->orderBy('name')->get(['id', 'name', 'is_active']),
            'vatCodes' => Product::query()->whereNotNull('vat_code')->where('vat_code', '!=', '')->distinct()->orderBy('vat_code')->pluck('vat_code')->values(),
            'activeMenuCode' => '1.3.1',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'parents' => $this->parentOptions(),
            'activeMenuCode' => '1.3.1',
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['available_marketing'] = (bool) ($data['available_marketing'] ?? true);
        $data['available_sale'] = (bool) ($data['available_sale'] ?? true);
        $data['available_care'] = (bool) ($data['available_care'] ?? true);

        $product = Product::query()->create($data);
        $product->categories()->sync($categoryIds);

        return redirect()->route('admin.products.index')->with('success', __('messages.product_created'));
    }

    public function edit(Product $product): Response
    {
        $product->loadMissing('categories:id,name');

        return Inertia::render('Admin/Products/Form', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type ?? 'product',
                'sku' => $product->sku,
                'unit' => $product->unit,
                'cost_price' => (int) $product->cost_price,
                'unit_price' => (int) $product->unit_price,
                'vat_percent' => (float) $product->vat_percent,
                'vat_code' => $product->vat_code,
                'weight_grams' => (int) $product->weight_grams,
                'parent_id' => $product->parent_id,
                'is_active' => (bool) $product->is_active,
                'available_marketing' => (bool) $product->available_marketing,
                'available_sale' => (bool) $product->available_sale,
                'available_care' => (bool) $product->available_care,
                'category_ids' => $product->categories->pluck('id')->all(),
            ],
            'parents' => $this->parentOptions(excludeId: $product->id),
            'activeMenuCode' => '1.3.1',
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $hasCategoryIds = $request->has('category_ids');
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        foreach (['is_active', 'available_marketing', 'available_sale', 'available_care'] as $flag) {
            if ($request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            } else {
                unset($data[$flag]);
            }
        }

        $product->update($data);
        if ($hasCategoryIds) {
            $product->categories()->sync($categoryIds);
        }

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

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $handle = fopen($data['file']->getRealPath(), 'rb');
        if ($handle === false) {
            return back()->with('error', 'Không thể đọc file import.');
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
        $count = 0;

        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, array_pad($values, count($headers), null));
            if (! is_array($row)) continue;
            $name = trim((string) ($row['name'] ?? $row['tên'] ?? $row['ten'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? $row['mã sp'] ?? $row['ma_sp'] ?? ''));
            if ($name === '') continue;

            Product::query()->updateOrCreate(
                $sku !== '' ? ['sku' => $sku] : ['name' => $name, 'type' => 'product'],
                [
                    'name' => $name,
                    'type' => 'product',
                    'unit' => $row['unit'] ?? $row['đ.vị tính'] ?? null,
                    'cost_price' => (int) ($row['cost_price'] ?? $row['giá nhập'] ?? 0),
                    'unit_price' => (int) ($row['unit_price'] ?? $row['đơn giá'] ?? 0),
                    'vat_percent' => (float) ($row['vat_percent'] ?? $row['vat'] ?? 0),
                    'vat_code' => $row['vat_code'] ?? $row['mã vat'] ?? null,
                    'weight_grams' => (int) ($row['weight_grams'] ?? $row['kl(gram)'] ?? 0),
                    'is_active' => true,
                ],
            );
            $count++;
        }
        fclose($handle);

        return back()->with('success', "Đã import {$count} sản phẩm.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']]);
        ProductCategory::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã thêm phân loại sản phẩm.');
    }

    public function storeAttribute(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']]);
        ProductAttribute::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã thêm thuộc tính sản phẩm.');
    }

    public function storeAttributeValue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_attribute_id' => ['required', 'exists:product_attributes,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        ProductAttributeValue::query()->create([
            'company_id' => $request->user()->company_id,
            'product_attribute_id' => $data['product_attribute_id'],
            'name' => $data['name'],
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã thêm giá trị thuộc tính.');
    }

    /** @return list<array{id: int, name: string}> */
    private function parentOptions(?int $excludeId = null): array
    {
        return $this->products->parentOptionsWithSku($excludeId);
    }
}
