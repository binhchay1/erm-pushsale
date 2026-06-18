<?php

namespace App\Services\Connections;

use App\Services\Shipping\ShippingFeePresenter;

/**
 * Chuẩn hóa kết quả kiểm thử API → cấu trúc hiển thị trên giao diện admin.
 */
class ApiTestDisplayPresenter
{
    public function __construct(private readonly ShippingFeePresenter $feePresenter) {}

    /** @return array<string, mixed> */
    public function present(array $result, string $action = ''): array
    {
        if ($action === 'fee') {
            return $this->feePresenter->present($result);
        }

        $success = (bool) ($result['success'] ?? true);
        $message = isset($result['message']) ? (string) $result['message'] : null;
        $data = $result['data'] ?? $result['raw'] ?? null;

        if (! $success) {
            return [
                'success' => false,
                'message' => $message ?: __('api_test.failed'),
                'lines' => [],
                'items' => [],
                'options' => [],
            ];
        }

        if (is_array($data) && $this->isIndexedList($data)) {
            return [
                'success' => true,
                'message' => $message ?: __('api_test.success'),
                'lines' => [[
                    'label' => __('api_test.record_count'),
                    'value' => (string) count($data),
                    'highlight' => true,
                ]],
                'items' => $this->mapListItems($data, $action),
                'options' => [],
            ];
        }

        if (is_array($data) && $data !== []) {
            $lines = $this->linesFromAssociative($data);

            if ($lines !== []) {
                return [
                    'success' => true,
                    'message' => $message ?: __('api_test.success'),
                    'lines' => $lines,
                    'items' => [],
                    'options' => [],
                ];
            }
        }

        $lines = [];
        if (isset($result['http_status'])) {
            $lines[] = ['label' => __('api_test.http_status'), 'value' => (string) $result['http_status']];
        }

        return [
            'success' => true,
            'message' => $message ?: __('api_test.connected'),
            'lines' => $lines,
            'items' => [],
            'options' => [],
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function mapListItems(array $rows, string $action): array
    {
        return collect($rows)->take(15)->map(function (array $row) use ($action) {
            if ($action === 'pick-addresses') {
                return [
                    'label' => (string) ($row['pick_name'] ?? $row['name'] ?? __('api_test.pickup_warehouse')),
                    'value' => isset($row['pick_tel']) ? __('api_test.phone_prefix', ['phone' => $row['pick_tel']]) : null,
                    'note' => trim((string) ($row['address'] ?? '')),
                ];
            }

            if ($action === 'products') {
                return [
                    'label' => (string) ($row['name'] ?? $row['product_name'] ?? __('api_test.product')),
                    'value' => isset($row['weight']) ? __('api_test.weight_prefix', ['weight' => $row['weight']]) : null,
                    'note' => isset($row['price']) ? __('api_test.price_prefix', ['price' => $row['price']]) : null,
                ];
            }

            $name = $row['name'] ?? $row['label'] ?? $row['title'] ?? $row['SERVICE_NAME'] ?? null;
            $id = $row['id'] ?? $row['code'] ?? $row['shop_id'] ?? null;

            return [
                'label' => (string) ($name ?? $id ?? __('api_test.item')),
                'value' => $id && $name ? (string) $id : null,
                'note' => null,
            ];
        })->values()->all();
    }

    /** @param  array<string, mixed>  $data */
    private function linesFromAssociative(array $data): array
    {
        $labels = [
            'shop_id' => 'api_test.shop_id',
            'shop_name' => 'api_test.shop_name',
            'name' => 'api_test.name',
            'phone' => 'api_test.phone',
            'address' => 'api_test.address',
            'status' => 'api_test.status',
            'email' => 'api_test.email',
            'client_id' => 'api_test.client_id',
        ];

        $lines = [];
        foreach ($labels as $key => $labelKey) {
            if (! empty($data[$key])) {
                $lines[] = ['label' => __($labelKey), 'value' => (string) $data[$key]];
            }
        }

        return $lines;
    }

    /** @param  array<int|string, mixed>  $data */
    private function isIndexedList(array $data): bool
    {
        return $data !== [] && array_is_list($data) && is_array($data[0] ?? null);
    }
}
