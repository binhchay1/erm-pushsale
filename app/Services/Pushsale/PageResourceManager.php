<?php

namespace App\Services\Pushsale;

use App\Models\Product;
use App\Models\Pushsale\OperationCategory;
use App\Models\Pushsale\OperationWorkflow;
use App\Models\Pushsale\Expense;
use App\Models\Pushsale\ProductComboItem;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WarehouseVoucherLine;
use App\Models\User;
use App\Services\Inventory\InventoryIntakeService;
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
            (array) ($definition['rules'] ?? []),
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
            }
        }

        if (array_key_exists('component_items', $payload)) {
            $payload['component_items'] = $this->normalizeComboItems($payload['component_items']);
        }

        if (array_key_exists('quantity', $payload) && array_key_exists('unit_price', $payload)) {
            $payload['total'] = (int) round(((float) $payload['quantity']) * ((int) $payload['unit_price']));
        }

        return $payload;
    }

    /** @param array<string, mixed> $definition @param array<string, mixed> $validated @return array<string, mixed> */
    private function modelAttributes(array $definition, array $validated): array
    {
        $relationFields = ['category_ids', 'attribute_value_ids', 'component_product_ids', 'component_items', 'product_id', 'document_quantity', 'quantity', 'unit_cost', 'batch_code', 'expiry_date', 'location_code'];
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

    /** @param array<string, mixed> $validated */
    private function createWarehouseVoucher(array $validated, ?User $actor): WarehouseVoucher
    {
        if (! $actor) {
            throw ValidationException::withMessages(['user' => 'Không xác định được người thao tác.']);
        }

        $quantity = abs((int) ($validated['quantity'] ?? 0));
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng nhập/xuất phải lớn hơn 0.']);
        }

        return DB::transaction(function () use ($validated, $actor, $quantity): WarehouseVoucher {
            $voucher = WarehouseVoucher::query()->create([
                'warehouse_id' => $validated['warehouse_id'],
                'code' => $validated['code'],
                'type' => $validated['type'],
                'document_date' => $validated['document_date'] ?? now()->toDateString(),
                'note' => $validated['note'] ?? null,
                'status' => 'confirmed',
                'approved_by_user_id' => $actor->id,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            WarehouseVoucherLine::query()->create([
                'warehouse_voucher_id' => $voucher->id,
                'product_id' => $validated['product_id'],
                'document_quantity' => (int) ($validated['document_quantity'] ?? $quantity),
                'quantity' => $quantity,
                'unit_cost' => (int) ($validated['unit_cost'] ?? 0),
                'batch_code' => $validated['batch_code'] ?? null,
                'expiry_date' => $validated['expiry_date'] ?? null,
                'location_code' => $validated['location_code'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $movement = $validated['type'] === 'outbound'
                ? $this->inventory->export((int) $validated['warehouse_id'], (int) $validated['product_id'], $quantity, $actor, $validated['note'] ?? null, $actor->id)
                : $this->inventory->intake((int) $validated['warehouse_id'], (int) $validated['product_id'], $quantity, $actor, $validated['note'] ?? null, $actor->id);

            // Liên kết phiếu nhập/xuất thủ công với thẻ kho để 5.3.2 và 5.3.3
            // cùng nhìn vào một sự kiện nghiệp vụ, không bị tách thành dữ liệu rời.
            $movement->forceFill([
                'reference_type' => 'warehouse_voucher',
                'reference_id' => $voucher->id,
                'unit_cost' => (int) ($validated['unit_cost'] ?? $movement->unit_cost ?? 0),
            ])->save();

            return $voucher->refresh();
        });
    }
    /** @param array<string, mixed> $validated */
    private function updateWarehouseVoucher(WarehouseVoucher $voucher, array $validated, ?User $actor): WarehouseVoucher
    {
        abort_if($voucher->status === 'confirmed', 422, 'Phiếu kho đã xác nhận không thể sửa trực tiếp. Hãy tạo phiếu điều chỉnh mới.');

        $voucher->fill([
            'warehouse_id' => $validated['warehouse_id'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'document_date' => $validated['document_date'] ?? now()->toDateString(),
            'note' => $validated['note'] ?? null,
            'updated_by_user_id' => $actor?->id,
        ])->save();

        $line = $voucher->lines()->firstOrNew();
        $line->fill([
            'product_id' => $validated['product_id'],
            'document_quantity' => $validated['document_quantity'] ?? $validated['quantity'],
            'quantity' => $validated['quantity'],
            'unit_cost' => $validated['unit_cost'] ?? 0,
            'batch_code' => $validated['batch_code'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'location_code' => $validated['location_code'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);
        $line->save();

        return $voucher->refresh();
    }

}
