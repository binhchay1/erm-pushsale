<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EcommerceConnectShopController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $platform = strtolower((string) $request->query('platform', 'tiktok'));
        if (! in_array($platform, ['tiktok', 'shopee'], true)) {
            $platform = 'tiktok';
        }

        $warehouseId = (int) $request->query('warehouse_id', 0);
        $keyword = trim((string) $request->query('keyword', ''));

        $connections = collect([$platform])
            ->map(fn (string $key) => IntegrationConnection::forPlatform(IntegrationPlatform::from($key)))
            ->map(function (IntegrationConnection $connection) {
                $credentials = $connection->credentials ?? [];
                $metadata = $connection->metadata ?? [];
                $warehouseId = (int) ($credentials['default_warehouse_id'] ?? $metadata['warehouse_id'] ?? 0);

                return [
                    'id' => $connection->id,
                    'platform' => $connection->platform,
                    'platformLabel' => match ($connection->platform) {
                        'tiktok' => 'TikTok',
                        'shopee' => 'Shopee',
                        default => strtoupper($connection->platform),
                    },
                    'warehouseId' => $warehouseId,
                    'warehouseName' => $warehouseId > 0 ? Warehouse::query()->whereKey($warehouseId)->value('name') : '—',
                    'shopId' => (string) ($credentials['shop_id'] ?? $credentials['seller_id'] ?? $credentials['account_id'] ?? ''),
                    'shopName' => (string) ($credentials['shop_name'] ?? $metadata['shop_name'] ?? ''),
                    'logoUrl' => (string) ($metadata['logo_url'] ?? ''),
                    'note' => (string) ($metadata['note'] ?? ($connection->is_enabled ? 'Đang sử dụng kết nối này' : 'Chưa bật kết nối')),
                    'updatedAt' => $connection->updated_at?->format('d/m/Y H:i'),
                    'enabled' => (bool) $connection->is_enabled,
                    'settingsUrl' => '/admin/integrations#'.$connection->platform,
                ];
            })
            ->filter(function (array $row) use ($warehouseId, $keyword) {
                if ($warehouseId > 0 && (int) $row['warehouseId'] !== $warehouseId) {
                    return false;
                }
                if ($keyword === '') {
                    return true;
                }
                $needle = mb_strtolower($keyword);

                return str_contains(mb_strtolower($row['shopId'].' '.$row['shopName'].' '.$row['warehouseName']), $needle);
            })
            ->values();

        return Inertia::render('Admin/Ecommerce/ConnectShops', [
            'filters' => [
                'platform' => $platform,
                'warehouse_id' => $warehouseId ?: '',
                'keyword' => $keyword,
            ],
            'platforms' => [
                ['value' => 'tiktok', 'label' => 'TikTok'],
                ['value' => 'shopee', 'label' => 'Shopee'],
            ],
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name'])->map(fn (Warehouse $warehouse) => [
                'value' => (string) $warehouse->id,
                'label' => $warehouse->name,
            ])->values(),
            'rows' => $connections,
            'routeUrl' => '/admin/ecommerce/connect-shops',
            'activeMenuCode' => '2.9.1',
        ]);
    }
}
