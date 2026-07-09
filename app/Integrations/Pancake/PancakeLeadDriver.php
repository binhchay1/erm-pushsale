<?php

namespace App\Integrations\Pancake;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use App\Support\MoneyParser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Chuẩn hoá dữ liệu Pancake POS / Pancake extension về lead/order nội bộ.
 *
 * Driver này cố tình nhận nhiều shape khác nhau vì Pancake có 3 luồng hay gặp:
 * - Webhook POS bắn order/customer/conversation.
 * - Extension đọc dữ liệu đang hiển thị trên Pancake rồi gửi về SaleOps.
 * - API sync/polling trả về order object từ POS Open API.
 */
class PancakeLeadDriver implements LeadPayloadNormalizer
{
    public function platform(): string
    {
        return IntegrationPlatform::Pancake->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $packet = $this->extractPacket($payload);
        $flat = $this->flatten($packet);
        $customer = $this->extractCustomer($packet);
        $order = $this->extractOrder($packet);

        $externalId = $this->firstScalar([
            Arr::get($packet, 'saleops_external_id'),
            Arr::get($packet, 'external_id'),
            Arr::get($packet, 'pancake_order_id'),
            Arr::get($order, 'id'),
            Arr::get($packet, 'id'),
            Arr::get($packet, 'order_id'),
            Arr::get($packet, 'bill_id'),
            Arr::get($packet, 'conversation_id'),
            Arr::get($packet, 'customer_id'),
        ]) ?: uniqid('pancake_', true);

        $phone = $this->findPhone($packet, $customer, $flat);
        $name = $this->findName($packet, $customer, $flat) ?: 'Khách Pancake';
        $address = $this->findAddress($packet, $customer, $flat);
        $message = $this->findMessage($packet, $order, $flat);
        $items = $this->findItems($packet);
        $productInterest = $this->productInterest($items, $packet, $flat);
        $sourceLabel = $this->sourceLabel($packet, $flat);

        return [
            'external_id' => 'pancake_'.$externalId,
            'customer_name' => $name,
            'customer_phone' => preg_replace('/\D+/', '', (string) $phone),
            'product_interest' => $productInterest,
            'message' => $message,
            'quantity' => max(1, (int) (Arr::get($packet, 'quantity') ?? Arr::get($order, 'quantity') ?? 1)),
            'shipping_address' => $address,
            'shipping_notes' => $this->firstScalar([
                Arr::get($packet, 'shipping_notes'),
                Arr::get($packet, 'shipping_note'),
                Arr::get($packet, 'delivery_note'),
                Arr::get($flat, 'ghi_chu_giao_hang'),
            ]),
            'discount' => $this->money([
                Arr::get($packet, 'discount'),
                Arr::get($order, 'discount'),
                Arr::get($packet, 'total_discount'),
            ]),
            'deposit' => $this->money([
                Arr::get($packet, 'deposit'),
                Arr::get($order, 'deposit'),
                Arr::get($packet, 'prepaid'),
            ]),
            'shipping_fee_collected' => $this->money([
                Arr::get($packet, 'shipping_fee'),
                Arr::get($packet, 'shipping_fee_collected'),
                Arr::get($order, 'shipping_fee'),
            ]),
            'items' => $items,
            'item_origin' => 'pancake',
            'utm_source' => Arr::get($packet, 'utm_source')
                ?? Arr::get($packet, 'source')
                ?? Arr::get($packet, 'channel')
                ?? 'pancake',
            'utm_campaign' => Arr::get($packet, 'utm_campaign')
                ?? Arr::get($packet, 'campaign')
                ?? Arr::get($packet, 'page_id')
                ?? Arr::get($packet, 'shop_id')
                ?? $sourceLabel,
            'pancake' => [
                'shop_id' => Arr::get($packet, 'shop_id'),
                'page_id' => Arr::get($packet, 'page_id'),
                'conversation_id' => Arr::get($packet, 'conversation_id'),
                'customer_id' => Arr::get($packet, 'customer_id'),
                'order_id' => Arr::get($order, 'id') ?? Arr::get($packet, 'order_id') ?? Arr::get($packet, 'id'),
                'source_label' => $sourceLabel,
                'sale_email' => Arr::get($packet, 'sale_email') ?? Arr::get($packet, 'assignee.email'),
                'sale_name' => Arr::get($packet, 'sale_name') ?? Arr::get($packet, 'assignee.name'),
                'pancake_user_id' => Arr::get($packet, 'pancake_user_id')
                    ?? Arr::get($packet, 'pancake_user.id')
                    ?? Arr::get($packet, 'assignee.id')
                    ?? Arr::get($packet, 'creator.id')
                    ?? Arr::get($packet, 'user.id'),
                'pancake_user_email' => Arr::get($packet, 'pancake_user_email')
                    ?? Arr::get($packet, 'pancake_user.email')
                    ?? Arr::get($packet, 'assignee.email')
                    ?? Arr::get($packet, 'creator.email'),
                'pancake_user_name' => Arr::get($packet, 'pancake_user_name')
                    ?? Arr::get($packet, 'pancake_user.name')
                    ?? Arr::get($packet, 'assignee.name')
                    ?? Arr::get($packet, 'creator.name'),
                'raw_status' => Arr::get($packet, 'status'),
                'raw_inserted_at' => Arr::get($packet, 'inserted_at') ?? Arr::get($packet, 'created_at'),
            ],
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Pancake);

        // URL webhook của hệ thống đã chứa token ngẫu nhiên theo tenant. Pancake POS thường chỉ cho nhập URL,
        // không phải nền tảng nào cũng ký HMAC, nên token URL là lớp xác thực mặc định.
        if (filled($request->route('token'))) {
            return true;
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Pancake-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->header('X-SaleOps-Signature');
        $apiKey = $request->header('X-Api-Key')
            ?? $request->bearerToken()
            ?? $request->query('api_key')
            ?? $request->query('token');

        foreach ($this->candidateSecrets($connection) as $secret) {
            if ($apiKey && hash_equals($secret, (string) $apiKey)) {
                return true;
            }

            if ($signature) {
                $plain = hash_hmac('sha256', $payload, $secret);
                if (hash_equals($plain, (string) $signature)
                    || hash_equals('sha256='.$plain, (string) $signature)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $payload */
    protected function extractPacket(array $payload): array
    {
        foreach (['data.order', 'data', 'order', 'payload', 'entry.0', 'object'] as $key) {
            $value = Arr::get($payload, $key);
            if (is_array($value) && $value !== []) {
                return array_merge($payload, $value);
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $packet */
    protected function extractCustomer(array $packet): array
    {
        foreach (['customer', 'buyer', 'user', 'profile', 'bill', 'shipping'] as $key) {
            $value = Arr::get($packet, $key);
            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    /** @param array<string, mixed> $packet */
    protected function extractOrder(array $packet): array
    {
        $value = Arr::get($packet, 'order');

        return is_array($value) ? $value : $packet;
    }

    /** @param array<string, mixed> $packet */
    protected function flatten(array $packet): array
    {
        $flat = [];
        $walk = function ($value, string $prefix = '') use (&$walk, &$flat): void {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $next = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                    $walk($child, $next);
                }

                return;
            }

            if (is_scalar($value)) {
                $flat[$prefix] = (string) $value;
                $slug = Str::of($prefix)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
                if ($slug !== '') {
                    $flat[$slug] = (string) $value;
                }
            }
        };
        $walk($packet);

        return $flat;
    }

    /** @param array<string, mixed> $packet @param array<string, mixed> $customer @param array<string, string> $flat */
    protected function findPhone(array $packet, array $customer, array $flat): ?string
    {
        $candidates = [
            Arr::get($packet, 'phone'),
            Arr::get($packet, 'customer_phone'),
            Arr::get($packet, 'bill_phone_number'),
            Arr::get($packet, 'shipping_phone_number'),
            Arr::get($packet, 'receiver_phone'),
            Arr::get($customer, 'phone'),
            Arr::get($customer, 'phone_number'),
            Arr::get($customer, 'mobile'),
            Arr::get($customer, 'tel'),
            Arr::get($flat, 'so_dien_thoai'),
            Arr::get($flat, 'sdt'),
        ];

        foreach ($flat as $value) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (strlen($digits) >= 9 && strlen($digits) <= 12) {
                $candidates[] = $value;
            }
        }

        return $this->firstPhone($candidates);
    }

    /** @param array<string, mixed> $packet @param array<string, mixed> $customer @param array<string, string> $flat */
    protected function findName(array $packet, array $customer, array $flat): ?string
    {
        return $this->firstScalar([
            Arr::get($packet, 'name'),
            Arr::get($packet, 'customer_name'),
            Arr::get($packet, 'bill_full_name'),
            Arr::get($packet, 'shipping_full_name'),
            Arr::get($customer, 'name'),
            Arr::get($customer, 'full_name'),
            Arr::get($flat, 'ho_ten'),
            Arr::get($flat, 'ten_khach_hang'),
        ]);
    }

    /** @param array<string, mixed> $packet @param array<string, mixed> $customer @param array<string, string> $flat */
    protected function findAddress(array $packet, array $customer, array $flat): ?string
    {
        return $this->firstScalar([
            Arr::get($packet, 'address'),
            Arr::get($packet, 'shipping_address'),
            Arr::get($packet, 'bill_address'),
            Arr::get($packet, 'full_address'),
            Arr::get($packet, 'delivery_address'),
            Arr::get($customer, 'address'),
            Arr::get($customer, 'shipping_address'),
            Arr::get($flat, 'dia_chi'),
            Arr::get($flat, 'dia_chi_nhan_hang'),
        ]);
    }

    /** @param array<string, mixed> $packet @param array<string, mixed> $order @param array<string, string> $flat */
    protected function findMessage(array $packet, array $order, array $flat): ?string
    {
        return $this->firstScalar([
            Arr::get($packet, 'message'),
            Arr::get($packet, 'note'),
            Arr::get($packet, 'customer_note'),
            Arr::get($packet, 'conversation_snippet'),
            Arr::get($order, 'note'),
            Arr::get($order, 'notes'),
            Arr::get($flat, 'tin_nhan'),
            Arr::get($flat, 'ghi_chu'),
        ]);
    }

    /** @param array<string, mixed> $packet @return list<array<string, mixed>> */
    protected function findItems(array $packet): array
    {
        $rawItems = Arr::get($packet, 'items')
            ?? Arr::get($packet, 'order_items')
            ?? Arr::get($packet, 'products')
            ?? Arr::get($packet, 'variations')
            ?? Arr::get($packet, 'line_items')
            ?? [];

        if (! is_array($rawItems)) {
            return [];
        }

        $rows = [];
        foreach ($rawItems as $index => $item) {
            if (! is_array($item)) {
                if (is_scalar($item) && filled($item)) {
                    $rows[] = [
                        'product_name' => (string) $item,
                        'quantity' => 1,
                        'unit_price' => 0,
                        'item_type' => 'product',
                        'origin' => 'pancake',
                    ];
                }
                continue;
            }

            $name = $this->firstScalar([
                Arr::get($item, 'product_name'),
                Arr::get($item, 'name'),
                Arr::get($item, 'variation_name'),
                Arr::get($item, 'display_name'),
                Arr::get($item, 'product.name'),
            ]);

            if (! $name) {
                continue;
            }

            $rows[] = [
                'product_id' => null,
                'product_name' => $name,
                'item_type' => 'product',
                'origin' => 'pancake',
                'quantity' => max(1, (int) (Arr::get($item, 'quantity') ?? Arr::get($item, 'qty') ?? 1)),
                'unit_price' => $this->money([
                    Arr::get($item, 'unit_price'),
                    Arr::get($item, 'price'),
                    Arr::get($item, 'retail_price'),
                ]),
                'discount_amount' => $this->money([
                    Arr::get($item, 'discount'),
                    Arr::get($item, 'discount_amount'),
                ]),
                'meta' => [
                    'pancake_variation_id' => Arr::get($item, 'variation_id') ?? Arr::get($item, 'id'),
                    'pancake_product_id' => Arr::get($item, 'product_id'),
                    'raw_index' => $index,
                ],
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $items @param array<string, mixed> $packet @param array<string, string> $flat */
    protected function productInterest(array $items, array $packet, array $flat): ?string
    {
        if ($items !== []) {
            return collect($items)->pluck('product_name')->filter()->take(5)->implode(', ');
        }

        return $this->firstScalar([
            Arr::get($packet, 'product'),
            Arr::get($packet, 'product_interest'),
            Arr::get($packet, 'product_name'),
            Arr::get($flat, 'san_pham'),
            Arr::get($flat, 'ten_san_pham'),
        ]);
    }

    /** @param array<string, mixed> $packet @param array<string, string> $flat */
    protected function sourceLabel(array $packet, array $flat): ?string
    {
        return $this->firstScalar([
            Arr::get($packet, 'page_name'),
            Arr::get($packet, 'shop_name'),
            Arr::get($packet, 'source_name'),
            Arr::get($packet, 'fanpage.name'),
            Arr::get($flat, 'nguon'),
        ]);
    }

    /** @param list<mixed> $values */
    protected function firstScalar(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    protected function firstPhone(array $values): ?string
    {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (strlen($digits) >= 9) {
                return $digits;
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    protected function money(array $values): int
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                $parsed = MoneyParser::parse($value);
                if ($parsed > 0) {
                    return $parsed;
                }
            }
        }

        return 0;
    }

    /** @return list<string> */
    protected function candidateSecrets(IntegrationConnection $connection): array
    {
        return collect([
            $connection->webhook_secret,
            $connection->credentials['webhook_secret'] ?? null,
            $connection->credentials['extension_token'] ?? null,
            $connection->credentials['api_key'] ?? null,
            config('integrations.platforms.pancake.fields.extension_token.default'),
            config('integrations.platforms.pancake.fields.api_key.default'),
            config('integrations.webhook.global_secret'),
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }
}
