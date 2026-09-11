<?php

namespace App\Services\Pushsale;

use App\Models\Product;
use App\Models\Pushsale\FacebookPageMapping;
use App\Models\Pushsale\OperationCategory;
use App\Models\Pushsale\OperationWorkflow;
use App\Models\Pushsale\Expense;
use App\Models\Pushsale\ProductComboItem;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WarehouseVoucherLine;
use App\Models\MarketingSource;
use App\Models\User;
use App\Rules\VietnameseMobilePhone;
use App\Services\Inventory\InventoryIntakeService;
use App\Support\VietnamesePhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PageResourceManager
{
    public function __construct(private readonly InventoryIntakeService $inventory) {}

    /** @return array<string, mixed>|null */
    public function definition(string $resourceKey): ?array
    {
        $resources = config('pushsale_resources', []);
        $definition = is_array($resources) ? ($resources[$resourceKey] ?? null) : null;

        return is_array($definition) ? $definition : null;
    }

    public function isEditable(string $resourceKey): bool
    {
        return $this->definition($resourceKey) !== null;
    }

    /** @return class-string<Model>|null */
    public function modelClass(string $resourceKey): ?string
    {
        $model = $this->definition($resourceKey)['model'] ?? null;

        return is_string($model) && is_a($model, Model::class, true) ? $model : null;
    }

    public function find(string $resourceKey, int $id): Model
    {
        $modelClass = $this->modelClass($resourceKey);
        abort_unless($modelClass, 404);

        return $modelClass::query()->findOrFail($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function formFields(string $resourceKey): array
    {
        return (array) ($this->definition($resourceKey)['fields'] ?? []);
    }

    /**
     * Dữ liệu thật cho danh sách bên trong dialog (phân loại, thuộc tính...).
     * Không đọc các dòng mẫu nằm trong HTML chụp từ Pushsale.
     *
     * @return list<array<string, mixed>>
     */
    public function records(string $resourceKey, int $limit = 250): array
    {
        $modelClass = $this->modelClass($resourceKey);
        if (! $modelClass) return [];

        $fields = $this->formFields($resourceKey);

        return $modelClass::query()
            ->latest('id')
            ->limit(max(1, min(1000, $limit)))
            ->get()
            ->map(function (Model $model) use ($fields): array {
                $row = ['id' => $model->getKey()];
                foreach ($fields as $field) {
                    $key = (string) ($field['key'] ?? '');
                    if ($key === '' || str_contains($key, 'token') || str_contains($key, 'secret')) continue;
                    $row[$key] = data_get($model, $key);
                }
                $row['_form'] = $row;
                return $row;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function validate(string $resourceKey, array $payload): array
    {
        $definition = $this->definition($resourceKey);
        abort_unless($definition, 404);

        $normalized = $this->normalizePayload($definition, $payload);
        $validated = Validator::make(
            $normalized,
            $this->validationRules($definition),
            (array) ($definition['messages'] ?? []),
        )->validate();

        if (($definition['special'] ?? null) === 'combo') {
            $validated = $this->validateComboPayload($validated);
        }

        return $validated;
    }

    public function create(string $resourceKey, array $payload, ?User $actor): Model
    {
        $definition = $this->definition($resourceKey);
        abort_unless($definition, 404);
        $validated = $this->validate($resourceKey, $payload);

        return DB::transaction(function () use ($resourceKey, $definition, $validated, $actor): Model {
            if (($definition['special'] ?? null) === 'warehouse_voucher') {
                return $this->createWarehouseVoucher($validated, $actor);
            }

            if ($resourceKey === '5.4' && empty($validated['manager_user_id']) && $actor) {
                $validated['manager_user_id'] = $actor->id;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $definition['model'];
            $attributes = array_merge((array) ($definition['defaults'] ?? []), $this->modelAttributes($definition, $validated));
            $this->applyAudit($attributes, $actor, true);
            $probe = new $modelClass;
            $attributes = Arr::only($attributes, $probe->getFillable());

            /** @var Model $model */
            $model = $modelClass::query()->create($attributes);
            $this->syncRelated($resourceKey, $model, $validated);

            return $model->refresh();
        });
    }

    public function update(string $resourceKey, Model $model, array $payload, ?User $actor): Model
    {
        $definition = $this->definition($resourceKey);
        abort_unless($definition, 404);
        $this->assertModel($definition, $model);
        $validated = $this->validate($resourceKey, $payload);

        return DB::transaction(function () use ($resourceKey, $definition, $model, $validated, $actor): Model {
            if (($definition['special'] ?? null) === 'warehouse_voucher' && $model instanceof WarehouseVoucher) {
                return $this->updateWarehouseVoucher($model, $validated, $actor);
            }

            $attributes = $this->modelAttributes($definition, $validated);
            // Secret fields are never pre-filled in the browser. An empty value on
            // update means “keep the current secret”, not “erase it”.
            if ($resourceKey === '2.6.3' && trim((string) ($attributes['access_token'] ?? '')) === '') {
                unset($attributes['access_token']);
            }
            if ($resourceKey === '1.14.1' && trim((string) ($attributes['password'] ?? '')) === '') {
                unset($attributes['password']);
            }
            // Mã combo dùng làm mã danh mục tham chiếu cho đơn hàng/kho, không đổi sau khi tạo.
            if ($resourceKey === '1.3.2') {
                unset($attributes['sku']);
            }
            $this->applyAudit($attributes, $actor, false);
            $attributes = Arr::only($attributes, $model->getFillable());
            $model->fill($attributes)->save();
            $this->syncRelated($resourceKey, $model, $validated);

            return $model->refresh();
        });
    }

    public function delete(string $resourceKey, Model $model): void
    {
        $definition = $this->definition($resourceKey);
        abort_unless($definition, 404);
        $this->assertModel($definition, $model);

        if (($definition['special'] ?? null) === 'warehouse_voucher' && $model instanceof WarehouseVoucher) {
            abort_if($model->status === 'confirmed', 422, 'Phiếu kho đã xác nhận không thể xóa vì đã phát sinh tồn kho.');
        }

        if ($resourceKey === '1.8.1' && $model instanceof OperationCategory) {
            abort_if((bool) $model->is_start, 422, 'Tác nghiệp khởi đầu đang gắn với luồng chia/sale, không thể xóa. Hãy sửa tên hoặc tắt áp dụng thay vì xóa.');
            $isUsedInWorkflow = OperationWorkflow::query()
                ->where('from_operation_category_id', $model->id)
                ->orWhere('to_operation_category_id', $model->id)
                ->exists();
            abort_if($isUsedInWorkflow, 422, 'Tác nghiệp này đang được dùng trong luồng chuyển bước. Hãy xóa/sửa luồng liên quan trước.');
        }

        $model->delete();
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    private function normalizePayload(array $definition, array $payload): array
    {
        foreach ((array) ($definition['fields'] ?? []) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') continue;
            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'checkbox') {
                $payload[$key] = filter_var($payload[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            } elseif ($type === 'multiselect') {
                $value = $payload[$key] ?? [];
                if (is_string($value)) $value = array_filter(array_map('trim', explode(',', $value)));
                $payload[$key] = array_values(array_unique(array_map('intval', (array) $value)));
            } elseif ($type === 'combo-items') {
                $payload[$key] = $this->normalizeComboItems($payload[$key] ?? []);
            } elseif ($type === 'currency') {
                $payload[$key] = (int) preg_replace('/[^0-9-]/', '', (string) ($payload[$key] ?? 0));
            } elseif ($type === 'json' && is_string($payload[$key] ?? null)) {
                $decoded = json_decode((string) $payload[$key], true);
                $payload[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : ['expression' => $payload[$key]];
            } elseif ($this->isPhoneField($key, $type)) {
                $raw = trim((string) ($payload[$key] ?? ''));
                $payload[$key] = $raw === '' ? null : (VietnamesePhone::normalize($raw) ?? $raw);
            }
        }

        if (array_key_exists('component_items', $payload)) {
            $payload['component_items'] = $this->normalizeComboItems($payload['component_items']);
        }

        if (($definition['special'] ?? null) === 'warehouse_voucher' && array_key_exists('type', $payload)) {
            $payload['type'] = $this->normalizeWarehouseVoucherType($payload['type']);
        }

        if (array_key_exists('quantity', $payload) && array_key_exists('unit_price', $payload)) {
            $payload['total'] = (int) round(((float) $payload['quantity']) * ((int) $payload['unit_price']));
        }

        return $payload;
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    private function validationRules(array $definition): array
    {
        $rules = (array) ($definition['rules'] ?? []);

        foreach ((array) ($definition['fields'] ?? []) as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            if ($key === '' || ! $this->isPhoneField($key, $type)) {
                continue;
            }

            $existing = Arr::wrap($rules[$key] ?? ['nullable', 'string', 'max:32']);
            $existing = array_values(array_filter(
                $existing,
                static fn ($rule) => ! is_string($rule) || ! str_starts_with($rule, 'regex:'),
            ));
            $hasPhoneRule = collect($existing)->contains(
                static fn ($rule) => $rule instanceof VietnameseMobilePhone,
            );
            if (! $hasPhoneRule) {
                $existing[] = new VietnameseMobilePhone;
            }
            $rules[$key] = $existing;
        }

        return $rules;
    }

    private function isPhoneField(string $key, string $type): bool
    {
        if ($type === 'tel') {
            return true;
        }

        return (bool) preg_match('/(^|_)(phone|sdt|mobile)(_|$)/i', $key);
    }

    /** @param array<string, mixed> $definition @param array<string, mixed> $validated @return array<string, mixed> */
    private function modelAttributes(array $definition, array $validated): array
    {
        $relationFields = [
            'category_ids', 'attribute_value_ids', 'component_product_ids', 'component_items',
            'product_id', 'document_quantity', 'quantity', 'unit_cost', 'batch_code', 'expiry_date', 'location_code', 'lines',
        ];
        if (($definition['special'] ?? null) !== 'warehouse_voucher') {
            $relationFields = ['category_ids', 'attribute_value_ids', 'component_product_ids', 'component_items'];
        }

        $attributes = Arr::except($validated, $relationFields);

        if (($definition['model'] ?? null) === Expense::class) {
            $attributes['total'] = (int) round(((float) ($validated['quantity'] ?? 0)) * ((int) ($validated['unit_price'] ?? 0)));
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function applyAudit(array &$attributes, ?User $actor, bool $creating): void
    {
        if (! $actor) return;
        if ($creating) $attributes['created_by_user_id'] = $actor->id;
        $attributes['updated_by_user_id'] = $actor->id;
    }

    /** @param array<string, mixed> $definition */
    private function assertModel(array $definition, Model $model): void
    {
        $expected = (string) $definition['model'];
        abort_unless($model instanceof $expected, 404);
    }

    /** @param array<string, mixed> $validated */
    private function syncRelated(string $resourceKey, Model $model, array $validated): void
    {
        if ($resourceKey === '1.3.1:product' && $model instanceof Product) {
            $model->categories()->sync($validated['category_ids'] ?? []);
            $model->attributeValues()->sync($validated['attribute_value_ids'] ?? []);
        }

        if ($resourceKey === '1.3.2' && $model instanceof Product) {
            $items = $validated['component_items'] ?? [];
            if (! count($items)) {
                $items = collect($validated['component_product_ids'] ?? [])->map(fn ($id): array => [
                    'product_id' => (int) $id,
                    'quantity' => 1,
                    'unit_price' => (int) Product::query()->whereKey((int) $id)->value('unit_price'),
                ])->all();
            }

            ProductComboItem::query()->where('combo_product_id', $model->id)->delete();
            foreach ($items as $item) {
                $id = (int) ($item['product_id'] ?? 0);
                if ($id <= 0 || $id === $model->id) continue;
                ProductComboItem::query()->create([
                    'combo_product_id' => $model->id,
                    'component_product_id' => $id,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'unit_price' => max(0, (int) ($item['unit_price'] ?? Product::query()->whereKey($id)->value('unit_price'))),
                ]);
            }
        }

        if ($resourceKey === '1.11' && $model instanceof FacebookPageMapping) {
            MarketingSource::query()->updateOrCreate(
                ['utm_source' => 'facebook', 'utm_campaign' => (string) $model->page_id],
                [
                    'name' => 'Facebook — '.$model->page_name,
                    'marketer_user_id' => $model->marketer_user_id,
                    'ad_channel' => 'Facebook',
                    'is_active' => (bool) $model->is_active,
                    'is_approved' => true,
                ],
            );
        }
    }

    /** @param mixed $value @return list<array{product_id:int, quantity:int, unit_price:int}> */
    private function normalizeComboItems(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return collect((array) $value)
            ->map(function ($item): array {
                if (! is_array($item)) {
                    return ['product_id' => (int) $item, 'quantity' => 1, 'unit_price' => 0];
                }

                return [
                    'product_id' => (int) ($item['product_id'] ?? $item['component_product_id'] ?? 0),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'unit_price' => max(0, (int) preg_replace('/[^0-9-]/', '', (string) ($item['unit_price'] ?? 0))),
                ];
            })
            ->filter(fn (array $item): bool => $item['product_id'] > 0)
            ->unique('product_id')
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function validateComboPayload(array $validated): array
    {
        $items = $this->normalizeComboItems($validated['component_items'] ?? []);
        if (! count($items)) {
            $items = collect($validated['component_product_ids'] ?? [])->map(fn ($id): array => [
                'product_id' => (int) $id,
                'quantity' => 1,
                'unit_price' => (int) Product::query()->whereKey((int) $id)->value('unit_price'),
            ])->filter(fn (array $item): bool => $item['product_id'] > 0)->values()->all();
        }

        if (! count($items)) {
            throw ValidationException::withMessages(['component_items' => 'Vui lòng chọn ít nhất một sản phẩm trong combo.']);
        }

        $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();
        $validIds = Product::query()
            ->whereIn('id', $productIds)
            ->where('type', 'product')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($validIds->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['component_items' => 'Combo chỉ được gồm sản phẩm đơn đang có trong catalog, không được lồng combo khác.']);
        }

        $validated['component_items'] = collect($items)->map(function (array $item): array {
            if ((int) ($item['unit_price'] ?? 0) <= 0) {
                $item['unit_price'] = (int) Product::query()->whereKey((int) $item['product_id'])->value('unit_price');
            }

            return $item;
        })->values()->all();
        $validated['component_product_ids'] = $productIds->all();

        return $validated;
    }

    /**
     * Lưu phiếu nháp nhiều dòng — chưa đụng tồn kho.
     *
     * @param  array<string, mixed>  $validated
     */
    private function createWarehouseVoucher(array $validated, ?User $actor): WarehouseVoucher
    {
        if (! $actor) {
            throw ValidationException::withMessages(['user' => 'Không xác định được người thao tác.']);
        }

        $type = $this->normalizeWarehouseVoucherType($validated['type'] ?? null);
        $lines = $this->normalizeWarehouseVoucherLines($validated);
        $this->assertWarehouseVoucherLines($lines);

        return DB::transaction(function () use ($validated, $actor, $type, $lines): WarehouseVoucher {
            $voucher = WarehouseVoucher::query()->create([
                'warehouse_id' => (int) $validated['warehouse_id'],
                'code' => (string) $validated['code'],
                'type' => $type,
                'document_date' => $validated['document_date'] ?? now()->toDateString(),
                'partner' => $validated['partner'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->syncWarehouseVoucherLines($voucher, $lines);

            return $voucher->load(['lines.product:id,name,sku,unit', 'warehouse:id,name', 'creator:id,name'])->refresh();
        });
    }

    /**
     * Cập nhật phiếu nháp nhiều dòng — chưa đụng tồn kho.
     *
     * @param  array<string, mixed>  $validated
     */
    private function updateWarehouseVoucher(WarehouseVoucher $voucher, array $validated, ?User $actor): WarehouseVoucher
    {
        abort_if($voucher->status === 'confirmed', 422, 'Phiếu kho đã xác nhận không thể sửa trực tiếp. Hãy tạo phiếu điều chỉnh mới.');

        $type = $this->normalizeWarehouseVoucherType($validated['type'] ?? $voucher->type);
        $lines = $this->normalizeWarehouseVoucherLines($validated);
        $this->assertWarehouseVoucherLines($lines);

        return DB::transaction(function () use ($voucher, $validated, $actor, $type, $lines): WarehouseVoucher {
            $voucher->fill([
                'warehouse_id' => (int) $validated['warehouse_id'],
                'code' => (string) $validated['code'],
                'type' => $type,
                'document_date' => $validated['document_date'] ?? now()->toDateString(),
                'partner' => $validated['partner'] ?? $voucher->partner,
                'note' => $validated['note'] ?? null,
                'status' => 'draft',
                'updated_by_user_id' => $actor?->id,
            ])->save();

            $this->syncWarehouseVoucherLines($voucher, $lines);

            return $voucher->load(['lines.product:id,name,sku,unit', 'warehouse:id,name', 'creator:id,name'])->refresh();
        });
    }

    /**
     * Hoàn thành phiếu nháp → confirmed và áp dụng nhập/xuất tồn theo loại phiếu.
     */
    public function completeWarehouseVoucher(WarehouseVoucher $voucher, User $actor): WarehouseVoucher
    {
        abort_if($voucher->status === 'confirmed', 422, 'Phiếu kho đã hoàn thành.');
        abort_if($voucher->status === 'cancelled', 422, 'Phiếu kho đã hủy không thể hoàn thành.');

        $voucher->loadMissing('lines');
        $lines = $voucher->lines->map(fn (WarehouseVoucherLine $line): array => [
            'product_id' => (int) $line->product_id,
            'document_quantity' => (int) $line->document_quantity,
            'quantity' => (int) $line->quantity,
            'unit_cost' => (int) $line->unit_cost,
            'batch_code' => $line->batch_code,
            'expiry_date' => $line->expiry_date?->toDateString(),
            'location_code' => $line->location_code,
            'note' => $line->note,
        ])->all();
        $this->assertWarehouseVoucherLines($lines);

        $type = $this->normalizeWarehouseVoucherType($voucher->type);

        return DB::transaction(function () use ($voucher, $actor, $type): WarehouseVoucher {
            foreach ($voucher->lines as $line) {
                $quantity = abs((int) $line->quantity);
                if ($quantity < 1) {
                    continue;
                }

                $movement = $this->isOutboundWarehouseVoucherType($type)
                    ? $this->inventory->export((int) $voucher->warehouse_id, (int) $line->product_id, $quantity, $actor, $voucher->note, $actor->id)
                    : $this->inventory->intake((int) $voucher->warehouse_id, (int) $line->product_id, $quantity, $actor, $voucher->note, $actor->id);

                $movement->forceFill([
                    'reference_type' => 'warehouse_voucher',
                    'reference_id' => $voucher->id,
                    'unit_cost' => (int) ($line->unit_cost ?: $movement->unit_cost ?: 0),
                ])->save();
            }

            $voucher->forceFill([
                'status' => 'confirmed',
                'approved_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ])->save();

            return $voucher->load(['lines.product:id,name,sku,unit', 'warehouse:id,name', 'creator:id,name', 'approver:id,name'])->refresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseWarehouseVoucherImport(mixed $file): array
    {
        if (! is_object($file) || ! method_exists($file, 'getRealPath')) {
            throw ValidationException::withMessages(['file' => 'File import không hợp lệ.']);
        }

        $path = (string) $file->getRealPath();
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Không đọc được file import.']);
        }

        $header = null;
        $rows = [];
        try {
            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === false) {
                    continue;
                }
                $cells = array_map(static fn ($value) => trim((string) $value), $data);
                if ($header === null) {
                    $header = array_map(static fn (string $value): string => strtolower($value), $cells);
                    continue;
                }
                if (count(array_filter($cells, static fn (string $value): bool => $value !== '')) === 0) {
                    continue;
                }
                $row = [];
                foreach ($header as $index => $key) {
                    $row[$key] = $cells[$index] ?? '';
                }
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        $skuMap = Product::query()
            ->withoutTenant()
            ->withoutShop()
            ->where('is_active', true)
            ->whereNotNull('sku')
            ->get(['id', 'sku', 'cost_price'])
            ->keyBy(fn (Product $product): string => strtoupper(trim((string) $product->sku)));

        $lines = [];
        foreach ($rows as $index => $row) {
            $productId = (int) ($row['product_id'] ?? $row['id'] ?? 0);
            $sku = strtoupper(trim((string) ($row['sku'] ?? $row['ma_san_pham'] ?? $row['mã sản phẩm'] ?? '')));
            if ($productId < 1 && $sku !== '' && $skuMap->has($sku)) {
                $productId = (int) $skuMap->get($sku)->id;
            }
            if ($productId < 1) {
                throw ValidationException::withMessages([
                    'file' => 'Dòng '.($index + 2).': không tìm thấy sản phẩm trong catalog (cần product_id hoặc sku).',
                ]);
            }

            $quantity = (int) preg_replace('/[^0-9-]/', '', (string) ($row['quantity'] ?? $row['so_luong'] ?? $row['số lượng'] ?? 0));
            $documentQuantity = (int) preg_replace('/[^0-9-]/', '', (string) ($row['document_quantity'] ?? $row['sl_chung_tu'] ?? $quantity));
            $unitCost = (int) preg_replace('/[^0-9-]/', '', (string) ($row['unit_cost'] ?? $row['gia_nhap'] ?? $row['giá nhập'] ?? 0));
            if ($unitCost <= 0 && $skuMap->has($sku)) {
                $unitCost = (int) ($skuMap->get($sku)->cost_price ?? 0);
            }

            $lines[] = [
                'product_id' => $productId,
                'document_quantity' => abs($documentQuantity ?: $quantity),
                'quantity' => abs($quantity),
                'unit_cost' => max(0, $unitCost),
                'batch_code' => ($row['batch_code'] ?? $row['lo'] ?? $row['lô'] ?? null) ?: null,
                'expiry_date' => ($row['expiry_date'] ?? $row['ngay_het_han'] ?? null) ?: null,
                'location_code' => ($row['location_code'] ?? $row['ma_vi_tri'] ?? null) ?: null,
                'note' => ($row['note'] ?? $row['ghi_chu'] ?? null) ?: null,
            ];
        }

        $this->assertWarehouseVoucherLines($lines);

        return $lines;
    }

    /**
     * Tester: cộng tồn cho các dòng tồn < ngưỡng trong kho.
     *
     * @return array{updated: int}
     */
    public function boostWarehouseStock(int $warehouseId, int $belowQuantity, int $addQuantity, User $actor): array
    {
        abort_unless($actor->isAdmin() || $actor->isPlatformAdmin(), 403, 'Chỉ admin mới được cộng tồn thử.');
        if ($addQuantity < 1) {
            throw ValidationException::withMessages(['add_quantity' => 'SL cộng thêm phải lớn hơn 0.']);
        }

        $inventories = \App\Models\WarehouseInventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stock_quantity', '<', $belowQuantity)
            ->get();

        $updated = 0;
        foreach ($inventories as $inventory) {
            $this->inventory->intake($warehouseId, (int) $inventory->product_id, $addQuantity, $actor, 'Tester cộng tồn kho', $actor->id);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * Tester: bù đúng phần âm về 0.
     *
     * @return array{updated: int}
     */
    public function resetNegativeWarehouseStock(int $warehouseId, User $actor): array
    {
        abort_unless($actor->isAdmin() || $actor->isPlatformAdmin(), 403, 'Chỉ admin mới được reset tồn về 0.');

        $inventories = \App\Models\WarehouseInventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stock_quantity', '<', 0)
            ->get();

        $updated = 0;
        foreach ($inventories as $inventory) {
            $need = abs((int) $inventory->stock_quantity);
            if ($need < 1) {
                continue;
            }
            $this->inventory->intake($warehouseId, (int) $inventory->product_id, $need, $actor, 'Tester reset tồn kho về 0', $actor->id);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /** @return array{id: int, warehouse_id: int, code: string, type: string, document_date: ?string, partner: ?string, note: ?string, status: string, created_by: ?string, approved_by: ?string, lines: list<array<string, mixed>>}|null */
    public function serializeWarehouseVoucher(?WarehouseVoucher $voucher): ?array
    {
        if (! $voucher) {
            return null;
        }

        $voucher->loadMissing(['lines.product:id,name,sku,unit,cost_price', 'warehouse:id,name', 'creator:id,name', 'approver:id,name']);

        return [
            'id' => (int) $voucher->id,
            'warehouse_id' => (int) $voucher->warehouse_id,
            'code' => (string) $voucher->code,
            'type' => $this->normalizeWarehouseVoucherType($voucher->type),
            'document_date' => $voucher->document_date?->toDateString(),
            'partner' => $voucher->partner,
            'note' => $voucher->note,
            'status' => (string) $voucher->status,
            'created_by' => $voucher->creator?->name,
            'created_at' => $voucher->created_at?->format('d/m/Y H:i:s'),
            'approved_by' => $voucher->approver?->name,
            'warehouse_name' => $voucher->warehouse?->name,
            'lines' => $voucher->lines->values()->map(fn (WarehouseVoucherLine $line): array => [
                'id' => (int) $line->id,
                'product_id' => (int) $line->product_id,
                'product' => $line->product?->name,
                'sku' => $line->product?->sku,
                'uom' => $line->product?->unit,
                'document_quantity' => (int) $line->document_quantity,
                'quantity' => (int) $line->quantity,
                'unit_cost' => (int) $line->unit_cost,
                'total' => (int) $line->quantity * (int) $line->unit_cost,
                'batch_code' => $line->batch_code,
                'expiry_date' => $line->expiry_date?->toDateString(),
                'location_code' => $line->location_code,
                'note' => $line->note,
            ])->all(),
        ];
    }

    public function normalizeWarehouseVoucherType(mixed $type): string
    {
        $key = strtolower(trim((string) $type));
        $map = [
            '1' => 'inbound',
            '2' => 'outbound',
            '3' => 'scrap',
            '4' => 'internal',
            'inbound' => 'inbound',
            'outbound' => 'outbound',
            'scrap' => 'scrap',
            'internal' => 'internal',
            'nhập kho' => 'inbound',
            'xuat kho' => 'outbound',
            'xuất kho' => 'outbound',
            'xuất kho nội bộ' => 'internal',
            'xuat kho noi bo' => 'internal',
            'xuất hủy' => 'scrap',
            'xuat huy' => 'scrap',
        ];

        return $map[$key] ?? (in_array($key, ['inbound', 'outbound', 'internal', 'scrap'], true) ? $key : 'inbound');
    }

    public function isOutboundWarehouseVoucherType(string $type): bool
    {
        return in_array($this->normalizeWarehouseVoucherType($type), ['outbound', 'internal', 'scrap'], true);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function normalizeWarehouseVoucherLines(array $validated): array
    {
        $rawLines = $validated['lines'] ?? null;
        if (is_array($rawLines) && count($rawLines) > 0) {
            return collect($rawLines)->map(function (mixed $line): array {
                $row = is_array($line) ? $line : [];
                $quantity = abs((int) ($row['quantity'] ?? 0));

                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'document_quantity' => abs((int) ($row['document_quantity'] ?? $quantity)),
                    'quantity' => $quantity,
                    'unit_cost' => max(0, (int) ($row['unit_cost'] ?? 0)),
                    'batch_code' => ($row['batch_code'] ?? null) ?: null,
                    'expiry_date' => ($row['expiry_date'] ?? null) ?: null,
                    'location_code' => ($row['location_code'] ?? null) ?: null,
                    'note' => ($row['note'] ?? null) ?: null,
                ];
            })->values()->all();
        }

        $productId = (int) ($validated['product_id'] ?? 0);
        if ($productId < 1) {
            return [];
        }

        $quantity = abs((int) ($validated['quantity'] ?? 0));

        return [[
            'product_id' => $productId,
            'document_quantity' => abs((int) ($validated['document_quantity'] ?? $quantity)),
            'quantity' => $quantity,
            'unit_cost' => max(0, (int) ($validated['unit_cost'] ?? 0)),
            'batch_code' => ($validated['batch_code'] ?? null) ?: null,
            'expiry_date' => ($validated['expiry_date'] ?? null) ?: null,
            'location_code' => ($validated['location_code'] ?? null) ?: null,
            'note' => ($validated['note'] ?? null) ?: null,
        ]];
    }

    /** @param list<array<string, mixed>> $lines */
    private function assertWarehouseVoucherLines(array $lines): void
    {
        if (count($lines) < 1) {
            throw ValidationException::withMessages(['lines' => 'Phiếu kho cần ít nhất một dòng sản phẩm.']);
        }

        $productIds = collect($lines)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();
        if ($productIds->count() !== count($lines)) {
            throw ValidationException::withMessages(['lines' => 'Mỗi dòng phải chọn sản phẩm trong catalog.']);
        }

        $uniqueIds = $productIds->unique()->values();
        // exists:products,id đã validate; kiểm tra lại bằng query bỏ shop/tenant scope
        // vì request HTTP có thể gắn shop context khác với sản phẩm catalog shared.
        $existing = Product::query()
            ->withoutTenant()
            ->withoutShop()
            ->whereIn('id', $uniqueIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        if ($existing->count() !== $uniqueIds->count()) {
            throw ValidationException::withMessages(['lines' => 'Chỉ được chọn sản phẩm đang có trong catalog.']);
        }

        $hasQty = collect($lines)->contains(fn (array $line): bool => abs((int) ($line['quantity'] ?? 0)) >= 1);
        if (! $hasQty) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng nhập/xuất phải lớn hơn 0.']);
        }
    }

    /** @param list<array<string, mixed>> $lines */
    private function syncWarehouseVoucherLines(WarehouseVoucher $voucher, array $lines): void
    {
        $voucher->lines()->delete();

        foreach ($lines as $line) {
            WarehouseVoucherLine::query()->create([
                'warehouse_voucher_id' => $voucher->id,
                'product_id' => (int) $line['product_id'],
                'document_quantity' => (int) ($line['document_quantity'] ?? $line['quantity'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_cost' => (int) ($line['unit_cost'] ?? 0),
                'batch_code' => $line['batch_code'] ?? null,
                'expiry_date' => $line['expiry_date'] ?? null,
                'location_code' => $line['location_code'] ?? null,
                'note' => $line['note'] ?? null,
            ]);
        }
    }

}
