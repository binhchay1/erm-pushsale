<?php

namespace App\Http\Controllers\Admin\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\FailedPartnerOrder;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\Pushsale\EcommerceProductLink;
use App\Models\Pushsale\EcommerceShopConnection;
use App\Models\Warehouse;
use App\Services\Ecommerce\EcommerceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EcommerceController extends Controller
{
    public function __construct(private readonly EcommerceSyncService $sync)
    {
    }

    public function shops(Request $request): Response
    {
        $filters = $this->normalizedFilters($request);
        $query = EcommerceShopConnection::query()->with(['warehouse:id,name', 'marketingSource:id,name']);

        $this->applyCommonShopFilters($query, $filters);

        if ($filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword): void {
                $q->where('shop_id', 'like', '%'.$keyword.'%')
                    ->orWhere('shop_name', 'like', '%'.$keyword.'%')
                    ->orWhere('note', 'like', '%'.$keyword.'%');
            });
        }

        return Inertia::render('Admin/Ecommerce/ConnectShops', [
            'filters' => $filters,
            'platforms' => $this->sync->platforms()->values(),
            'warehouses' => $this->warehouses(),
            'marketingSources' => $this->marketingSources(),
            'rows' => $query->latest('id')->paginate(20)->withQueryString()->through(fn (EcommerceShopConnection $shop) => $this->shopRow($shop)),
            'routeUrl' => '/admin/ecommerce/connect-shops',
            'storeUrl' => '/admin/ecommerce/connect-shops',
        ]);
    }

    public function storeShop(Request $request): RedirectResponse
    {
        $data = $this->validateShop($request);
        $shop = EcommerceShopConnection::query()->updateOrCreate(
            ['platform' => $data['platform'], 'shop_id' => $data['shop_id']],
            $data + ['is_enabled' => true]
        );

        return back()->with('success', 'Đã lưu kết nối '.$shop->shop_name.'.');
    }

    public function updateShop(Request $request, EcommerceShopConnection $shop): RedirectResponse
    {
        $data = $this->validateShop($request, $shop->id);
        $shop->update($data);

        return back()->with('success', 'Đã cập nhật kết nối '.$shop->shop_name.'.');
    }

    public function destroyShop(EcommerceShopConnection $shop): RedirectResponse
    {
        $shop->delete();

        return back()->with('success', 'Đã xóa kết nối shop.');
    }

    public function products(Request $request): Response
    {
        $filters = $this->normalizedFilters($request);
        $status = (string) $request->query('status', '-1');
        $keyword = trim((string) $request->query('keyword', ''));
        $shopId = (int) $request->query('shop_id', 0);

        $query = EcommerceProductLink::query()->with(['shop:id,platform,shop_id,shop_name', 'warehouse:id,name', 'product:id,name,sku']);
        if ($filters['platform'] !== '') {
            $query->where('platform', $filters['platform']);
        }
        if ($filters['warehouse_id'] > 0) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if ($shopId > 0) {
            $query->where('shop_connection_id', $shopId);
        }
        if (in_array($status, ['0', '1'], true)) {
            $query->where('connection_status', $status === '1' ? 'linked' : 'unlinked');
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('external_product_id', 'like', '%'.$keyword.'%')
                    ->orWhere('external_name', 'like', '%'.$keyword.'%')
                    ->orWhere('external_sku', 'like', '%'.$keyword.'%');
            });
        }

        return Inertia::render('Admin/Ecommerce/ConnectProducts', [
            'filters' => $filters + ['shop_id' => $shopId ?: '', 'status' => $status, 'keyword' => $keyword],
            'platforms' => $this->sync->platforms()->values(),
            'warehouses' => $this->warehouses(),
            'shops' => $this->shopOptions($filters),
            'products' => $this->productOptions(),
            'rows' => $query->latest('id')->paginate(20)->withQueryString()->through(fn (EcommerceProductLink $link) => $this->productRow($link)),
            'routeUrl' => '/admin/ecommerce/connect-products',
        ]);
    }

    public function syncProducts(Request $request): RedirectResponse
    {
        $shop = $this->resolveShopFromRequest($request);
        if (! $shop) {
            return back()->with('error', 'Hãy chọn shop.');
        }

        $created = $this->sync->syncProducts($shop);

        return back()->with('success', 'Đã đồng bộ sản phẩm từ '.$shop->shop_name.'. Tạo mới '.$created.' dòng.');
    }

    public function mapProduct(Request $request, EcommerceProductLink $link): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'sync_quantity' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $product = isset($data['product_id']) && $data['product_id'] ? Product::query()->find($data['product_id']) : null;
        $link->update([
            'product_id' => $product?->id,
            'product_sku' => $product?->sku,
            'sync_quantity' => (int) ($data['sync_quantity'] ?? $link->sync_quantity),
            'connection_status' => $product ? 'linked' : 'unlinked',
            'note' => $data['note'] ?? $link->note,
            'last_synced_at' => now(),
        ]);

        return back()->with('success', 'Đã cập nhật liên kết sản phẩm.');
    }

    public function errors(Request $request): Response
    {
        $filters = $this->normalizedFilters($request);
        $shopId = (int) $request->query('shop_id', 0);
        $keyword = trim((string) $request->query('keyword', ''));
        $dateRange = trim((string) $request->query('date_range', now()->format('d/m/Y 00:00').' - '.now()->format('d/m/Y 23:59')));

        $query = FailedPartnerOrder::query()->with('warehouse:id,name');
        if ($filters['platform'] !== '') {
            $query->where('platform', $this->platformLabel($filters['platform']));
        }
        if ($filters['warehouse_id'] > 0) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if ($shopId > 0) {
            $shopName = EcommerceShopConnection::query()->whereKey($shopId)->value('shop_name');
            if ($shopName) {
                $query->where('shop_name', $shopName);
            }
        }
        if ($keyword !== '') {
            $query->where('partner_order_id', 'like', '%'.$keyword.'%');
        }

        return Inertia::render('Admin/Ecommerce/SyncErrors', [
            'filters' => $filters + ['shop_id' => $shopId ?: '', 'keyword' => $keyword, 'date_range' => $dateRange],
            'platforms' => $this->sync->platforms()->values(),
            'warehouses' => $this->warehouses(),
            'shops' => $this->shopOptions($filters),
            'rows' => $query->latest('id')->paginate(20)->withQueryString()->through(fn (FailedPartnerOrder $row) => [
                'id' => $row->id,
                'partnerOrderId' => $row->partner_order_id,
                'errorDescription' => $row->error_description,
                'updatedAt' => $row->updated_at?->format('d/m/Y H:i'),
            ]),
            'routeUrl' => '/admin/ecommerce/sync-errors',
        ]);
    }

    public function fetchMissingOrders(Request $request): RedirectResponse
    {
        $shop = $this->resolveShopFromRequest($request);
        if (! $shop) {
            return back()->with('error', 'Hãy chọn shop.');
        }

        $created = $this->sync->fetchMissingOrders($shop);

        return back()->with('success', 'Đã lấy danh sách đơn chưa có trên hệ thống. Tạo mới '.$created.' dòng lỗi để xử lý.');
    }

    public function exportErrors(Request $request)
    {
        $rows = FailedPartnerOrder::query()->latest('id')->limit(500)->get();
        $csv = "STT,Mã đơn đối tác,Mô tả lỗi,Cập nhật\n".$rows->map(fn (FailedPartnerOrder $row) => implode(',', [
            $row->id,
            '"'.str_replace('"', '""', $row->partner_order_id).'"',
            '"'.str_replace('"', '""', (string) $row->error_description).'"',
            $row->updated_at?->format('d/m/Y H:i'),
        ]))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ecommerce-sync-errors.csv"',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateShop(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'platform' => ['required', Rule::in(['tiktok', 'shopee'])],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'marketing_source_id' => ['nullable', 'integer', 'exists:marketing_sources,id'],
            'shop_id' => ['required', 'string', 'max:80'],
            'shop_name' => ['required', 'string', 'max:160'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'logistics_mode' => ['nullable', 'integer', 'min:0', 'max:9'],
        ]);
    }

    private function normalizedFilters(Request $request): array
    {
        $platform = strtolower((string) $request->query('platform', 'tiktok'));
        if (! in_array($platform, ['tiktok', 'shopee'], true)) {
            $platform = 'tiktok';
        }

        return [
            'platform' => $platform,
            'warehouse_id' => (int) $request->query('warehouse_id', 0),
            'keyword' => trim((string) $request->query('keyword', '')),
        ];
    }

    private function applyCommonShopFilters($query, array $filters): void
    {
        if ($filters['platform'] !== '') {
            $query->where('platform', $filters['platform']);
        }
        if ($filters['warehouse_id'] > 0) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
    }

    private function resolveShopFromRequest(Request $request): ?EcommerceShopConnection
    {
        $shopId = (int) $request->input('shop_id', $request->query('shop_id', 0));
        if ($shopId > 0) {
            return EcommerceShopConnection::query()->find($shopId);
        }

        $filters = $this->normalizedFilters($request);

        return EcommerceShopConnection::query()
            ->when($filters['platform'] !== '', fn ($q) => $q->where('platform', $filters['platform']))
            ->when($filters['warehouse_id'] > 0, fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->oldest('id')
            ->first();
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'tiktok' => 'TikTok',
            'shopee' => 'Shopee',
            default => strtoupper($platform),
        };
    }

    private function shopRow(EcommerceShopConnection $shop): array
    {
        return [
            'id' => $shop->id,
            'platform' => $shop->platform,
            'platformLabel' => $shop->platformLabel(),
            'warehouseId' => $shop->warehouse_id,
            'warehouseName' => $shop->warehouse?->name ?? '—',
            'marketingSourceId' => $shop->marketing_source_id,
            'marketingSourceName' => $shop->marketingSource?->name ?? '—',
            'shopId' => $shop->shop_id,
            'shopName' => $shop->shop_name,
            'logoUrl' => $shop->logo_url,
            'note' => $shop->note,
            'updatedAt' => $shop->updated_at?->format('d/m/Y H:i'),
            'enabled' => $shop->is_enabled,
        ];
    }

    private function productRow(EcommerceProductLink $link): array
    {
        $attributes = collect($link->external_attributes ?? [])->map(fn ($value, $key) => $key.': '.$value)->implode(' + ');

        return [
            'id' => $link->id,
            'externalProductId' => $link->external_product_id,
            'externalSku' => $link->external_sku,
            'externalSkuId' => $link->external_sku_id,
            'externalName' => $link->external_name,
            'externalAttributes' => $attributes,
            'productId' => $link->product_id,
            'productSku' => $link->product?->sku ?? $link->product_sku,
            'productName' => $link->product?->name,
            'syncQuantity' => $link->sync_quantity,
            'connectionStatus' => $link->connection_status,
            'note' => $link->note,
            'shopName' => $link->shop?->shop_name,
        ];
    }

    private function warehouses(): array
    {
        return Warehouse::query()->orderBy('name')->get(['id', 'name'])->map(fn (Warehouse $warehouse) => [
            'value' => (string) $warehouse->id,
            'label' => $warehouse->name,
        ])->values()->all();
    }

    private function marketingSources(): array
    {
        return MarketingSource::query()->orderBy('name')->limit(200)->get(['id', 'name'])->map(fn (MarketingSource $source) => [
            'value' => (string) $source->id,
            'label' => $source->name,
        ])->values()->all();
    }

    private function productOptions(): array
    {
        return Product::query()->orderBy('name')->limit(500)->get(['id', 'name', 'sku'])->map(fn (Product $product) => [
            'value' => (string) $product->id,
            'label' => trim(($product->sku ? $product->sku.' - ' : '').$product->name),
        ])->values()->all();
    }

    private function shopOptions(array $filters): array
    {
        return EcommerceShopConnection::query()
            ->when($filters['platform'] !== '', fn ($q) => $q->where('platform', $filters['platform']))
            ->when($filters['warehouse_id'] > 0, fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->orderBy('shop_name')
            ->get(['id', 'shop_name', 'shop_id'])
            ->map(fn (EcommerceShopConnection $shop) => [
                'value' => (string) $shop->id,
                'label' => $shop->shop_name.' ('.$shop->shop_id.')',
            ])->values()->all();
    }
}
