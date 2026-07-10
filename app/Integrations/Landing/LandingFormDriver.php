<?php

namespace App\Integrations\Landing;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use App\Support\MoneyParser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LandingFormDriver implements LeadPayloadNormalizer
{
    public function platform(): string
    {
        return IntegrationPlatform::Landing->value;
    }

    public function normalize(array $payload): array
    {
        $flatFields = $this->flattenFields($payload);
        $phone = $this->findPhone($payload, $flatFields);
        $name = $this->findName($payload, $flatFields);
        $product = $this->findProduct($payload, $flatFields);
        $externalId = Arr::get($payload, 'submission_id')
            ?? Arr::get($payload, 'lead_id')
            ?? Arr::get($payload, 'id')
            ?? Arr::get($payload, 'form_response_id')
            ?? Arr::get($payload, 'form_data.id')
            ?? uniqid('lp_', true);

        $message = $this->findMessage($payload, $flatFields);
        $products = $this->findProducts($payload, $flatFields) ?? $product;
        $quantity = max(1, (int) (Arr::get($payload, 'quantity') ?? Arr::get($flatFields, 'quantity') ?? 1));

        return [
            'external_id' => (string) $externalId,
            'customer_name' => $name ?: 'Khách Landing',
            'customer_phone' => preg_replace('/\D+/', '', (string) $phone),
            'product_interest' => $products,
            'message' => $message,
            'quantity' => $quantity,
            'shipping_address' => $this->findAddress($payload, $flatFields),
            'shipping_fee_collected' => $this->findShippingFee($payload, $flatFields),
            'discount' => $this->findDiscount($payload, $flatFields),
            'items' => $this->findItems($payload, $flatFields, $quantity),
            'parent_ref' => $this->findParentRef($payload, $flatFields),
            'utm_source' => Arr::get($payload, 'utm_source')
                ?? Arr::get($payload, 'utm.source')
                ?? Arr::get($payload, 'source')
                ?? 'landing',
            'utm_campaign' => Arr::get($payload, 'utm_campaign')
                ?? Arr::get($payload, 'utm.campaign')
                ?? Arr::get($payload, 'campaign')
                ?? Arr::get($flatFields, 'utm_campaign'),
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $expected = IntegrationConnection::forPlatform(IntegrationPlatform::Landing)
            ->credentials['api_key']
            ?? env('LANDING_API_KEY');

        if (! $expected) {
            return app()->environment('local');
        }

        $key = $request->header('X-Api-Key')
            ?? $request->bearerToken()
            ?? $request->query('api_key');

        return $key && hash_equals($expected, (string) $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function flattenFields(array $payload): array
    {
        $flattened = [];

        foreach ((array) Arr::get($payload, 'fields', []) as $field) {
            $this->putField($flattened, (string) ($field['name'] ?? $field['key'] ?? ''), $field['value'] ?? null);
        }

        foreach ((array) Arr::get($payload, 'form_data', []) as $item) {
            $this->putField($flattened, (string) ($item['name'] ?? $item['key'] ?? ''), $item['value'] ?? null);
        }

        // Ladipage WordPress plugin thường gửi f1, f2, ... theo field order.
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/^f\d+$/i', $key) && is_scalar($value)) {
                $flattened[strtolower($key)] = (string) $value;
            }
        }

        return $flattened;
    }

    /**
     * Đưa 1 field vào map với cả key gốc (lower) lẫn key slug không dấu
     * để khớp được label tiếng Việt có dấu như "Địa chỉ", "Số điện thoại".
     *
     * @param  array<string, string>  $flattened
     */
    protected function putField(array &$flattened, string $name, mixed $value): void
    {
        if ($value === null || ! is_scalar($value)) {
            return;
        }

        $lower = Str::of($name)->lower()->trim()->value();
        if ($lower === '') {
            return;
        }

        $flattened[$lower] = (string) $value;

        // "Địa chỉ nhận hàng" -> "dia_chi_nhan_hang"
        $slug = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
        if ($slug !== '' && ! array_key_exists($slug, $flattened)) {
            $flattened[$slug] = (string) $value;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findPhone(array $payload, array $flatFields): string
    {
        $candidates = [
            Arr::get($payload, 'phone'),
            Arr::get($payload, 'customer_phone'),
            Arr::get($payload, 'mobile'),
            Arr::get($payload, 'tel'),
            Arr::get($flatFields, 'phone'),
            Arr::get($flatFields, 'customer_phone'),
            Arr::get($flatFields, 'dien_thoai'),
            Arr::get($flatFields, 'so_dien_thoai'),
            Arr::get($flatFields, 'sdt'),
        ];

        foreach ($flatFields as $value) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (strlen($digits) >= 9 && strlen($digits) <= 11) {
                $candidates[] = $value;
            }
        }

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $candidate);
            if (strlen($digits) >= 9) {
                return $digits;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findName(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'name'),
            Arr::get($payload, 'customer_name'),
            Arr::get($flatFields, 'name'),
            Arr::get($flatFields, 'ho_ten'),
            Arr::get($flatFields, 'full_name'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findProduct(array $payload, array $flatFields): ?string
    {
        return $this->findProducts($payload, $flatFields);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findProducts(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'products'),
            Arr::get($payload, 'product'),
            Arr::get($payload, 'product_interest'),
            Arr::get($flatFields, 'products'),
            Arr::get($flatFields, 'product'),
            Arr::get($flatFields, 'san_pham'),
            Arr::get($flatFields, 'ten_san_pham'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findMessage(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'message'),
            Arr::get($payload, 'note'),
            Arr::get($payload, 'customer_note'),
            Arr::get($flatFields, 'message'),
            Arr::get($flatFields, 'note'),
            Arr::get($flatFields, 'ghi_chu'),
            Arr::get($flatFields, 'tin_nhan'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findAddress(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'address'),
            Arr::get($payload, 'shipping_address'),
            Arr::get($payload, 'customer_address'),
            Arr::get($flatFields, 'address'),
            Arr::get($flatFields, 'dia_chi'),
            Arr::get($flatFields, 'diachi'),
            Arr::get($flatFields, 'dia_chi_nhan_hang'),
            Arr::get($flatFields, 'shipping_address'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * Chiết khấu cấp đơn (VND).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findDiscount(array $payload, array $flatFields): int
    {
        $candidates = [
            Arr::get($payload, 'discount'),
            Arr::get($payload, 'chiet_khau'),
            Arr::get($flatFields, 'discount'),
            Arr::get($flatFields, 'chiet_khau'),
            Arr::get($flatFields, 'giam_gia'),
            Arr::get($flatFields, 'voucher_value'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                $parsed = MoneyParser::parse($candidate);
                if ($parsed > 0) {
                    return $parsed;
                }
            }
        }

        return 0;
    }

    /**
     * Phí ship khách trả. Hỗ trợ field riêng và nhãn combo kiểu
     * "149k + 30k Ship" / "Miễn Ship".
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findShippingFee(array $payload, array $flatFields): int
    {
        $candidates = [
            Arr::get($payload, 'shipping_fee_collected'),
            Arr::get($payload, 'shipping_fee'),
            Arr::get($payload, 'ship_fee'),
            Arr::get($payload, 'phi_ship'),
            Arr::get($payload, 'phi_van_chuyen'),
            Arr::get($flatFields, 'shipping_fee_collected'),
            Arr::get($flatFields, 'shipping_fee'),
            Arr::get($flatFields, 'phi_ship'),
            Arr::get($flatFields, 'phi_van_chuyen'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return max(0, MoneyParser::parse($candidate));
            }
        }

        $labels = array_merge(
            array_values(array_filter($flatFields, 'is_string')),
            array_values(array_filter($payload, 'is_string')),
        );

        foreach ($labels as $label) {
            if (preg_match('/miễn\s*(?:phí\s*)?(?:ship|shipping|vận\s*chuyển)/iu', $label)) {
                continue;
            }

            if (preg_match('/([0-9][0-9.,]*)\s*(k|nghìn|nghin|ngàn|ngan|đ|vnđ|vnd)\s*(?:phí\s*)?(?:ship|shipping|vận\s*chuyển)/iu', $label, $match)) {
                return max(0, MoneyParser::parse($match[1].$match[2]));
            }
        }

        return 0;
    }

    /**
     * Tham chiếu đơn gốc cho luồng upsale ở trang cảm ơn.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findParentRef(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'parent_ref'),
            Arr::get($payload, 'parent_submission_id'),
            Arr::get($payload, 'saleops_client_ref'),
            Arr::get($payload, 'order_ref'),
            Arr::get($payload, 'order_code'),
            Arr::get($flatFields, 'parent_ref'),
            Arr::get($flatFields, 'parent_submission_id'),
            Arr::get($flatFields, 'saleops_client_ref'),
            Arr::get($flatFields, 'order_ref'),
            Arr::get($flatFields, 'ma_don'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * Danh sách gói combo / sản phẩm mua thêm khách chọn trên landing.
     *
     * Ưu tiên payload['items'] dạng cấu trúc; nếu không có, quét các field
     * combo/gói/mua thêm rồi tự tách giá tiền nhúng trong nhãn.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     * @return list<array<string, mixed>>
     */
    protected function findItems(array $payload, array $flatFields, int $defaultQty): array
    {
        $items = [];

        // 1) Cấu trúc chuẩn: items = [{name, price, quantity, type, discount, variant}]
        $structured = Arr::get($payload, 'items');
        if (is_array($structured)) {
            foreach ($structured as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? $row['product_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                // Giữ nguyên meta gốc (vd detected_qty) khi payload đã có sẵn items
                // đã chuẩn hoá — xảy ra khi chốt lead "đang gom" (dựng lại từ payload).
                $existingMeta = is_array($row['meta'] ?? null) ? $row['meta'] : [];

                $items[] = [
                    'product_id' => $row['product_id'] ?? null,
                    'product_name' => $name,
                    'unit_price' => MoneyParser::parse($row['price'] ?? $row['unit_price'] ?? 0),
                    'quantity' => max(1, (int) ($row['quantity'] ?? $defaultQty)),
                    'discount_amount' => MoneyParser::parse($row['discount'] ?? $row['discount_amount'] ?? 0),
                    'item_type' => in_array($row['type'] ?? $row['item_type'] ?? null, ['product', 'combo', 'upsell', 'gift'], true)
                        ? ($row['type'] ?? $row['item_type'])
                        : 'combo',
                    'origin' => $row['origin'] ?? 'landing',
                    'meta' => array_filter(array_merge($existingMeta, [
                        'variant' => $row['variant'] ?? $row['phan_loai'] ?? ($existingMeta['variant'] ?? null),
                        'raw_label' => $name,
                    ])),
                ];
            }

            if ($items !== []) {
                return $items;
            }
        }

        // Gộp key top-level (scalar) với flatFields để quét — Ladipage có thể gửi combo ở cấp cao nhất.
        $scan = $flatFields;
        foreach ($payload as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $scan[strtolower($key)] = (string) $value;
            }
        }

        // 2) Quét field combo/gói/mua thêm — nhãn có kèm giá "289k".
        $variant = Arr::get($scan, 'phan_loai')
            ?? Arr::get($scan, 'variant')
            ?? Arr::get($scan, 'mau')
            ?? Arr::get($scan, 'mau_sac');

        foreach ($scan as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            // Bỏ qua field phụ trợ (combo_price, gia_combo, combo_id…).
            if (preg_match('/(price|gia|amount|id)$/u', $key)) {
                continue;
            }

            $isCombo = (bool) preg_match('/^(combo|goi|g[oó]i)/u', $key);
            $isUpsell = (bool) preg_match('/^(mua_them|muathem|san_pham_them|upsell|addon|add_on)/u', $key);

            if (! $isCombo && ! $isUpsell) {
                continue;
            }

            $items[] = $this->itemFromLabel(
                (string) $value,
                $isUpsell ? 'upsell' : 'combo',
                is_string($variant) ? $variant : null,
            );
        }

        // 3) Cặp combo + combo_price rời (không nhúng giá trong nhãn).
        $comboLabel = Arr::get($scan, 'combo');
        $comboPrice = Arr::get($scan, 'combo_price') ?? Arr::get($scan, 'gia_combo');
        if ($items === [] && is_scalar($comboLabel) && filled($comboLabel) && $comboPrice !== null) {
            $item = $this->itemFromLabel((string) $comboLabel, 'combo', is_string($variant) ? $variant : null);
            $item['unit_price'] = MoneyParser::parse($comboPrice);
            $items[] = $item;
        }

        // Một số form LadiPage đặt tên field chung là product/san_pham thay vì
        // mua_them_*. JS trang cảm ơn gắn item_type=upsell để không mất dòng hàng.
        if ($items === []) {
            $generic = Arr::get($scan, 'products')
                ?? Arr::get($scan, 'product')
                ?? Arr::get($scan, 'product_interest')
                ?? Arr::get($scan, 'san_pham')
                ?? Arr::get($scan, 'ten_san_pham');
            $requestedType = Arr::get($scan, 'item_type');
            $isUpsellPacket = in_array(strtolower((string) Arr::get($scan, 'is_upsell')), ['1', 'true', 'yes'], true);
            $type = $requestedType === 'upsell' || $isUpsellPacket ? 'upsell' : 'product';

            if (is_scalar($generic) && filled($generic)) {
                $items[] = $this->itemFromLabel((string) $generic, $type, is_string($variant) ? $variant : null);
            }
        }

        return $items;
    }

    /**
     * Tách 1 nhãn combo landing thành dòng hàng: giá + số lượng nhúng trong text.
     *
     * @return array<string, mixed>
     */
    protected function itemFromLabel(string $label, string $type, ?string $variant): array
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

        // Giá trong nhãn — ưu tiên token có đơn vị tiền ("289k", "99.000đ", "1tr2").
        $price = 0;
        if (preg_match('/([0-9][0-9.,]*)\s*(k|nghìn|nghin|ngàn|ngan|đ|vnđ|vnd|tr|triệu|trieu)\b/iu', $label, $m)) {
            $price = MoneyParser::parse($m[1].$m[2]);
        } else {
            // Không có đơn vị → chọn cụm số nhiều chữ số nhất (bỏ "2" trong "Mua 2 Thỏi").
            preg_match_all('/[0-9][0-9.,]*/', $label, $all);
            foreach (($all[0] ?? []) as $token) {
                if (strlen(preg_replace('/\D/', '', $token) ?? '') >= 4) {
                    $candidate = MoneyParser::parse($token);
                    if ($candidate > $price) {
                        $price = $candidate;
                    }
                }
            }
        }

        // Số lượng: "Mua 2 Thỏi", "x2", "2 hộp".
        $qty = 1;
        if (preg_match('/(?:mua|x|sl|số lượng)\s*([0-9]+)/iu', $label, $mq)) {
            $qty = max(1, (int) $mq[1]);
        } elseif (preg_match('/\b([0-9]+)\s*(thỏi|cây|gói|hộp|chiếc|cái|lọ|chai)/iu', $label, $mq2)) {
            $qty = max(1, (int) $mq2[1]);
        }

        // Giá nhúng thường là giá cả gói → unit_price = giá/gói, quantity = 1 để tránh nhân đôi.
        return [
            'product_name' => $label,
            'unit_price' => $price,
            'quantity' => $price > 0 ? 1 : $qty,
            'discount_amount' => 0,
            'item_type' => $type,
            'origin' => 'landing',
            'meta' => array_filter([
                'variant' => $variant,
                'detected_qty' => $qty,
                'raw_label' => $label,
            ]),
        ];
    }
}
