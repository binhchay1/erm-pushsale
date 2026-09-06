<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Enums\TeamType;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use App\Repositories\ProductRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly WarehouseRepository $warehouses,
    ) {}

    /**
     * @return array{id:int,name:string,is_active:bool,updated_by:?string,updated_at:?string}[]
     */
    private function productCategoryOptions(): array
    {
        return ProductCategory::query()
            ->with('updater:id,name,email')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'is_active' => (bool) $category->is_active,
                'updated_by' => $this->displayUser($category->updater),
                'updated_at' => $category->updated_at?->format('d / m / Y H:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id:int,name:string,is_active:bool,updated_by:?string,updated_at:?string,values:array<int,array<string,mixed>>}[]
     */
    private function productAttributeOptions(): array
    {
        return ProductAttribute::query()
            ->with(['updater:id,name,email', 'values' => fn ($query) => $query->with('updater:id,name,email')->orderByDesc('id')])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProductAttribute $attribute): array => [
                'id' => (int) $attribute->id,
                'name' => $attribute->name,
                'is_active' => (bool) $attribute->is_active,
                'updated_by' => $this->displayUser($attribute->updater),
                'updated_at' => $attribute->updated_at?->format('d / m / Y H:i'),
                'values' => $attribute->values->map(fn (ProductAttributeValue $value): array => [
                    'id' => (int) $value->id,
                    'product_attribute_id' => (int) $value->product_attribute_id,
                    'attribute_name' => $attribute->name,
                    'name' => $value->name,
                    'updated_by' => $this->displayUser($value->updater),
                    'updated_at' => $value->updated_at?->format('d / m / Y H:i'),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $attributes
     * @return array<int,array<string,mixed>>
     */
    private function productAttributeValueOptions(array $attributes): array
    {
        return collect($attributes)->flatMap(fn (array $attribute): array => $attribute['values'] ?? [])->values()->all();
    }

    private function displayUser(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->name ?: ($user->email ? Str::before($user->email, '@') : null);
    }

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
            ->with(['parent:id,name', 'categories:id,name', 'attributeValues:id'])
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
            'barcode' => $product->barcode,
            'length_cm' => (float) $product->length_cm,
            'width_cm' => (float) $product->width_cm,
            'height_cm' => (float) $product->height_cm,
            'warehouse_location' => $product->warehouse_location,
            'price_after_vat' => (int) round($product->unit_price * (1 + ((float) $product->vat_percent / 100))),
            'weight_grams' => (int) $product->weight_grams,
            'is_active' => (bool) $product->is_active,
            'available_marketing' => (bool) $product->available_marketing,
            'available_sale' => (bool) $product->available_sale,
            'available_care' => (bool) $product->available_care,
            'marketing_team_ids' => $this->integerArray($product->marketing_team_ids),
            'marketing_user_ids' => $this->integerArray($product->marketing_user_ids),
            'sale_team_ids' => $this->integerArray($product->sale_team_ids),
            'sale_user_ids' => $this->integerArray($product->sale_user_ids),
            'care_team_ids' => $this->integerArray($product->care_team_ids),
            'care_user_ids' => $this->integerArray($product->care_user_ids),
            'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'attribute_value_ids' => $product->attributeValues->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'category_names' => $product->categories->pluck('name')->implode(', '),
            'updated_at' => $product->updated_at?->format('d/m/Y H:i'),
        ]);

        $categoryOptions = $this->productCategoryOptions();
        $attributeOptions = $this->productAttributeOptions();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => $categoryOptions,
            'attributes' => $attributeOptions,
            'attributeValues' => $this->productAttributeValueOptions($attributeOptions),
            'vatCodes' => Product::query()->whereNotNull('vat_code')->where('vat_code', '!=', '')->distinct()->orderBy('vat_code')->pluck('vat_code')->values(),
            'permissionOptions' => $this->permissionOptions(),
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

    public function importPage(): Response
    {
        return Inertia::render('Admin/Products/Import', [
            'activeMenuCode' => '1.3.1',
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import sản phẩm');
        $headers = [
            'Tên SP gốc', 'Mã SP', 'Phân loại', 'Đ.vị tính', 'Giá nhập', 'Đơn giá', 'Mã VAT', 'VAT (%)',
            'KL(gram)', 'Mã vạch', 'Dài (cm)', 'Rộng (cm)', 'Cao (cm)', 'Mã vị trí',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['Sản phẩm mẫu', 'SP001', 'Nhóm mẫu', 'hộp', 100000, 150000, 'KCT', 0, 500, '893000000001', 10, 8, 5, 'A01'],
        ], null, 'A2');

        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'mau-import-san-pham.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? [];
        $attributeValueIds = $data['attribute_value_ids'] ?? [];
        unset($data['category_ids'], $data['attribute_value_ids']);
        $this->normalizePermissionAssignmentPayload($data);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['available_marketing'] = (bool) ($data['available_marketing'] ?? true);
        $data['available_sale'] = (bool) ($data['available_sale'] ?? true);
        $data['available_care'] = (bool) ($data['available_care'] ?? true);

        $product = Product::query()->create($data);
        $product->categories()->sync($categoryIds);
        $product->attributeValues()->sync($attributeValueIds);

        return redirect()->route('admin.products.index')->with('success', __('messages.product_created'));
    }

    public function edit(Product $product): Response
    {
        $product->loadMissing(['categories:id,name', 'attributeValues:id']);

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
                'barcode' => $product->barcode,
                'weight_grams' => (int) $product->weight_grams,
                'length_cm' => (float) $product->length_cm,
                'width_cm' => (float) $product->width_cm,
                'height_cm' => (float) $product->height_cm,
                'warehouse_location' => $product->warehouse_location,
                'parent_id' => $product->parent_id,
                'is_active' => (bool) $product->is_active,
                'available_marketing' => (bool) $product->available_marketing,
                'available_sale' => (bool) $product->available_sale,
                'available_care' => (bool) $product->available_care,
                'marketing_team_ids' => $this->integerArray($product->marketing_team_ids),
                'marketing_user_ids' => $this->integerArray($product->marketing_user_ids),
                'sale_team_ids' => $this->integerArray($product->sale_team_ids),
                'sale_user_ids' => $this->integerArray($product->sale_user_ids),
                'care_team_ids' => $this->integerArray($product->care_team_ids),
                'care_user_ids' => $this->integerArray($product->care_user_ids),
                'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'attribute_value_ids' => $product->attributeValues->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ],
            'parents' => $this->parentOptions(excludeId: $product->id),
            'activeMenuCode' => '1.3.1',
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $hasCategoryIds = $request->has('category_ids');
        $hasAttributeValueIds = $request->has('attribute_value_ids');
        $categoryIds = $data['category_ids'] ?? [];
        $attributeValueIds = $data['attribute_value_ids'] ?? [];
        unset($data['category_ids'], $data['attribute_value_ids']);
        $this->normalizePermissionAssignmentPayload($data);

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
        if ($hasAttributeValueIds) {
            $product->attributeValues()->sync($attributeValueIds);
        }

        return redirect()->route('admin.products.index')->with('success', __('messages.product_updated'));
    }


    public function updateBusinessStatus(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $data['is_active'];

        $product->forceFill([
            'is_active' => $isActive,
            // Khi ngừng kinh doanh, sản phẩm không được đưa vào các luồng phát sinh mới.
            // Dữ liệu lịch sử đơn, tồn kho và báo cáo cũ vẫn giữ nguyên để đối soát.
            'available_marketing' => $isActive,
            'available_sale' => $isActive,
            'available_care' => $isActive,
        ])->save();

        $message = $isActive
            ? sprintf('Đã mở kinh doanh lại mặt hàng "%s".', $product->name)
            : sprintf('Đã ngừng kinh doanh mặt hàng "%s". Sản phẩm sẽ không còn được chọn cho marketing, sale, CSKH và các luồng phát sinh mới.', $product->name);

        return back()->with('success', $message);
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
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:20480'],
        ]);

        $rows = $this->readImportRows($data['file']);
        if (count($rows) > 3000) {
            return back()->with('error', 'File import vượt quá 3000 dòng.');
        }

        $count = 0;
        foreach ($rows as $row) {
            $name = trim((string) ($this->rowValue($row, ['name', 'ten', 'tên', 'ten sp goc', 'tên sp gốc', 'san pham', 'sản phẩm']) ?? ''));
            $sku = trim((string) ($this->rowValue($row, ['sku', 'ma sp', 'mã sp', 'ma_sp', 'ma san pham', 'mã sản phẩm']) ?? ''));

            if ($name === '') {
                continue;
            }

            $categoryName = trim((string) ($this->rowValue($row, ['phan loai', 'phân loại', 'category']) ?? ''));
            $category = null;
            if ($categoryName !== '') {
                $category = ProductCategory::query()->firstOrCreate(
                    ['company_id' => $request->user()->company_id, 'name' => $categoryName],
                    [
                        'is_active' => true,
                        'created_by_user_id' => $request->user()->id,
                        'updated_by_user_id' => $request->user()->id,
                    ],
                );
            }

            $product = Product::query()->updateOrCreate(
                $sku !== '' ? ['sku' => $sku] : ['name' => $name, 'type' => 'product'],
                [
                    'name' => $name,
                    'type' => 'product',
                    'unit' => $this->rowValue($row, ['unit', 'dv tinh', 'đv tính', 'd.vi tinh', 'đ.vị tính', 'don vi tinh', 'đơn vị tính']),
                    'cost_price' => $this->numberValue($this->rowValue($row, ['cost_price', 'gia nhap', 'giá nhập'])),
                    'unit_price' => $this->numberValue($this->rowValue($row, ['unit_price', 'don gia', 'đơn giá', 'gia ban', 'giá bán'])),
                    'vat_percent' => $this->decimalValue($this->rowValue($row, ['vat_percent', 'vat', 'vat (%)'])),
                    'vat_code' => $this->rowValue($row, ['vat_code', 'ma vat', 'mã vat']) ?: 'KCT',
                    'barcode' => $this->rowValue($row, ['barcode', 'ma vach', 'mã vạch']),
                    'weight_grams' => $this->numberValue($this->rowValue($row, ['weight_grams', 'kl(gram)', 'kl', 'khoi luong', 'khối lượng'])),
                    'length_cm' => $this->decimalValue($this->rowValue($row, ['length_cm', 'dai (cm)', 'dài (cm)', 'dai', 'dài'])),
                    'width_cm' => $this->decimalValue($this->rowValue($row, ['width_cm', 'rong (cm)', 'rộng (cm)', 'rong', 'rộng'])),
                    'height_cm' => $this->decimalValue($this->rowValue($row, ['height_cm', 'cao (cm)', 'cao'])),
                    'warehouse_location' => $this->rowValue($row, ['warehouse_location', 'ma vi tri', 'mã vị trí', 'vi tri', 'vị trí']),
                    'is_active' => true,
                    'available_marketing' => true,
                    'available_sale' => true,
                    'available_care' => true,
                ],
            );

            if ($category) {
                $product->categories()->syncWithoutDetaching([$category->id]);
            }

            $count++;
        }

        return back()->with('success', "Đã import {$count} sản phẩm.");
    }

    /** @return list<array<string, mixed>> */
    private function readImportRows(\Illuminate\Http\UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
            $rawRows = $sheet->toArray(null, true, true, false);
        } else {
            $handle = fopen($file->getRealPath(), 'rb');
            if ($handle === false) {
                return [];
            }
            $rawRows = [];
            while (($values = fgetcsv($handle)) !== false) {
                $rawRows[] = $values;
            }
            fclose($handle);
        }

        $headers = array_map(fn ($value): string => $this->normalizeHeader((string) $value), array_shift($rawRows) ?: []);
        $rows = [];
        foreach ($rawRows as $values) {
            if (! array_filter($values, fn ($value): bool => trim((string) $value) !== '')) {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $values[$index] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function rowValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader((string) $key);
            if (array_key_exists($normalized, $row)) {
                return $row[$normalized];
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replace(['_', '-', '.', '/', '\\'], ' ')->squish()->toString();
    }

    private function numberValue(mixed $value): int
    {
        $normalized = preg_replace('/[^0-9\-]/', '', (string) $value);
        return max(0, (int) $normalized);
    }

    private function decimalValue(mixed $value): float
    {
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,\.\-]/', '', (string) $value) ?? '0');
        return max(0, (float) $normalized);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validateTaxonomy($request, ProductCategory::class);
        ProductCategory::query()->create([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã thêm phân loại sản phẩm.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $data = $this->validateTaxonomy($request, ProductCategory::class, $category->id);
        $category->update([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã cập nhật phân loại sản phẩm.');
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        $category->delete();
        return back()->with('success', 'Đã xóa phân loại sản phẩm.');
    }

    public function storeAttribute(Request $request): RedirectResponse
    {
        $data = $this->validateTaxonomy($request, ProductAttribute::class);
        ProductAttribute::query()->create([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã thêm thuộc tính sản phẩm.');
    }

    public function updateAttribute(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $data = $this->validateTaxonomy($request, ProductAttribute::class, $attribute->id);
        $attribute->update([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã cập nhật thuộc tính sản phẩm.');
    }

    public function destroyAttribute(ProductAttribute $attribute): RedirectResponse
    {
        $attribute->delete();
        return back()->with('success', 'Đã xóa thuộc tính sản phẩm.');
    }

    public function storeAttributeValue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_attribute_id' => ['required', 'exists:product_attributes,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        ProductAttributeValue::query()->create([
            'product_attribute_id' => $data['product_attribute_id'],
            'name' => $data['name'],
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã thêm giá trị thuộc tính.');
    }

    public function updateAttributeValue(Request $request, ProductAttributeValue $attributeValue): RedirectResponse
    {
        $data = $request->validate([
            'product_attribute_id' => ['required', 'exists:product_attributes,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $attributeValue->update([
            'product_attribute_id' => $data['product_attribute_id'],
            'name' => $data['name'],
            'updated_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Đã cập nhật giá trị thuộc tính.');
    }

    public function destroyAttributeValue(ProductAttributeValue $attributeValue): RedirectResponse
    {
        $attributeValue->delete();
        return back()->with('success', 'Đã xóa giá trị thuộc tính.');
    }

    /** @return array{name:string,is_active?:bool} */
    private function validateTaxonomy(Request $request, string $modelClass, ?int $ignoreId = null): array
    {
        $table = (new $modelClass)->getTable();

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($ignoreId)->where(fn ($query) => $query->where('company_id', $request->user()->company_id))],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }


    /** @return array<string, array<int, array<string, mixed>>> */
    private function permissionOptions(): array
    {
        $teams = Team::query()
            ->with(['users' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->whereIn('type', [TeamType::Marketing->value, TeamType::Sale->value])
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $users = User::query()
            ->with('team:id,name,type')
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_MARKETING, User::ROLE_SALES])
            ->orderBy('role')
            ->orderBy('team_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'team_id']);

        $teamOption = fn (Team $team): array => [
            'value' => (int) $team->id,
            'label' => $team->name,
            'member_ids' => $team->users->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];

        $userOption = fn (User $user): array => [
            'value' => (int) $user->id,
            'label' => $user->name,
            'subLabel' => trim(($user->email ?? '').($user->team?->name ? ' · '.$user->team->name : '')),
            'team_id' => $user->team_id ? (int) $user->team_id : null,
        ];

        $marketingTeams = $teams->filter(fn (Team $team): bool => $team->type === TeamType::Marketing)->map($teamOption)->values()->all();
        $saleTeams = $teams->filter(fn (Team $team): bool => $team->type === TeamType::Sale)->map($teamOption)->values()->all();
        $marketingUsers = $users->filter(fn (User $user): bool => $this->roleValue($user) === User::ROLE_MARKETING)->map($userOption)->values()->all();
        $saleUsers = $users->filter(fn (User $user): bool => $this->roleValue($user) === User::ROLE_SALES)->map($userOption)->values()->all();

        return [
            'marketingTeams' => $marketingTeams,
            'marketingUsers' => $marketingUsers,
            'saleTeams' => $saleTeams,
            'saleUsers' => $saleUsers,
            // Hiện hệ thống chưa tách role CSKH độc lập; CSKH sau bán dùng cùng tập sale/team sale.
            // Khi bổ sung role CSKH riêng, chỉ cần đổi nguồn dữ liệu ở đây, form và backend vẫn dùng care_*_ids.
            'careTeams' => $saleTeams,
            'careUsers' => array_map(fn (array $row): array => [
                ...$row,
                'subLabel' => trim((string) ($row['subLabel'] ?? '').' · CSKH sau bán'),
            ], $saleUsers),
        ];
    }

    private function roleValue(User $user): string
    {
        return $user->role instanceof \BackedEnum ? (string) $user->role->value : (string) $user->role;
    }

    /** @param array<string, mixed> $data */
    private function normalizePermissionAssignmentPayload(array &$data): void
    {
        foreach ([
            'marketing' => User::ROLE_MARKETING,
            'sale' => User::ROLE_SALES,
            // CSKH đang sử dụng nhân sự sale/care sau bán trong business hiện tại.
            'care' => User::ROLE_SALES,
        ] as $scope => $role) {
            $teamKey = $scope.'_team_ids';
            $userKey = $scope.'_user_ids';
            $availableKey = 'available_'.$scope;

            $teamIds = $this->validTeamIds($data[$teamKey] ?? [], $scope === 'marketing' ? TeamType::Marketing : TeamType::Sale);
            $userIds = $this->validUserIds($data[$userKey] ?? [], $role, $teamIds);

            $data[$teamKey] = $teamIds;
            $data[$userKey] = $userIds;

            if (array_key_exists($availableKey, $data)) {
                $data[$availableKey] = (bool) $data[$availableKey];
            }
        }
    }

    /** @return list<int> */
    private function validTeamIds(mixed $ids, TeamType $type): array
    {
        $ids = $this->integerArray($ids);
        if ($ids === []) {
            return [];
        }

        $existing = Team::query()
            ->whereIn('id', $ids)
            ->where('type', $type->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->keepInputOrder($ids, $existing);
    }

    /** @param list<int> $teamIds @return list<int> */
    private function validUserIds(mixed $ids, string $role, array $teamIds = []): array
    {
        $ids = $this->integerArray($ids);
        if ($ids === []) {
            return [];
        }

        $query = User::query()
            ->whereIn('id', $ids)
            ->where('role', $role)
            ->where('is_active', true);

        // Nếu đã chọn nhanh theo team, chỉ nhận nhân sự nằm trong team đó để tránh cấu hình chéo.
        if ($teamIds !== []) {
            $query->whereIn('team_id', $teamIds);
        }

        $existing = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return $this->keepInputOrder($ids, $existing);
    }

    /**
     * Giữ đúng thứ tự người dùng chọn mà không cần FIELD() (hàm riêng của MySQL).
     *
     * @param  list<int>  $ids
     * @param  list<int>  $existing
     * @return list<int>
     */
    private function keepInputOrder(array $ids, array $existing): array
    {
        return array_values(array_filter($ids, fn (int $id): bool => in_array($id, $existing, true)));
    }

    /** @return list<int> */
    private function integerArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function parentOptions(?int $excludeId = null): array
    {
        return $this->products->parentOptionsWithSku($excludeId);
    }
}
