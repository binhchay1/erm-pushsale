<?php

namespace App\Services\Marketing;

use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSource;
use App\Support\LandingProductLabel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LandingConnectionPayloadMapper
{
    /**
     * Convert an arbitrary landing form into the authoritative payload understood by LandingFormDriver.
     * Product ids/prices always come from the configured connection, never from client-submitted prices.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function map(LandingConnection $connection, LandingConnectionSource $source, array $input, string $flowToken): array
    {
        // Monetary and item data from a public landing page is untrusted.
        // Keep customer/form fields, but always rebuild order items and totals from backend configuration.
        $payload = Arr::except($input, [
            'items', 'products', 'product_id', 'product_ids', 'unit_price', 'price',
            'subtotal', 'total', 'order_total', 'amount', 'discount', 'chiet_khau',
            'shipping_fee', 'shipping_fee_collected', 'ship_fee', 'phi_ship', 'phi_van_chuyen',
        ]);
        $payload['_landing_connection_authoritative_pricing'] = true;
        $payload['discount'] = 0;
        $payload['shipping_fee_collected'] = 0;
        $payload['session_id'] = $flowToken;
        $payload['saleops_session'] = $flowToken;
        $payload['saleops_client_ref'] = $flowToken;
        $payload['landing_connection_id'] = $connection->id;
        $payload['landing_connection_source_id'] = $source->id;
        $payload['landing_source_type'] = $source->source_type;
        $payload['utm_source'] = $payload['utm_source'] ?? $connection->marketingSource?->utm_source ?? 'landing_connection';
        $payload['utm_campaign'] = $connection->marketingSource?->utm_campaign;
        $payload['is_upsell'] = $source->isSupplemental();
        $payload['item_type'] = $source->isSupplemental() ? 'upsell' : 'product';

        $submission = $input['submission_id'] ?? $input['form_response_id'] ?? $input['lead_id'] ?? null;
        $rawSubmission = is_scalar($submission) ? trim((string) $submission) : '';
        if ($rawSubmission === '') {
            $rawSubmission = $flowToken.'|'.($source->isSupplemental() ? 'supplement' : 'base');
        }

        // Namespace the public reference by connection/source so two landing pages can
        // use the same vendor submission id without colliding inside one tenant.
        $payload['external_submission_id'] = substr($rawSubmission, 0, 255);
        $payload['submission_id'] = 'lc_'.substr(hash('sha256', $connection->id.'|'.$source->id.'|'.$rawSubmission), 0, 40);

        $items = $this->configuredItems($connection, $source, $input);
        if ($items === []) {
            $items = $this->softFormItemLines($input, $source);
        }
        if ($items !== []) {
            $payload['items'] = $items;
            $payload['products'] = collect($items)->pluck('product_name')->implode(', ');
            $payload['product_interest'] = $payload['products'];
        }

        $mappingReport = $this->mappingReport($connection, $source, $input, $items, $flowToken);
        $payload['_landing_webhook_mapping'] = $mappingReport;
        // Chỉ gắn product_interest text thật — không dùng URL monitor hint (tránh thành dòng hàng).
        if (blank($payload['product_interest'] ?? null)
            && $mappingReport['product_candidate_text'] !== ''
            && ! str_starts_with((string) $mappingReport['product_candidate_text'], 'URL landing')
        ) {
            $payload['product_interest'] = $mappingReport['product_candidate_text'];
        }

        return $payload;
    }

    /**
     * Build an audit-friendly map of what the webhook actually sent and what the backend mapped.
     *
     * LadiPage/landing builders often send dynamic field names instead of stable product ids.  This
     * report is deliberately stored with each lead packet so operators can see every received field,
     * which fields were used for customer/session matching, which candidate product fields matched a
     * configured product, and which candidate values still need manual mapping.
     *
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $mappedItems
     * @return array<string, mixed>
     */
    public function mappingReport(
        LandingConnection $connection,
        LandingConnectionSource $source,
        array $input,
        array $mappedItems = [],
        ?string $flowToken = null,
    ): array {
        $fields = $this->flattenSubmittedFields($input);
        $mappedFieldKeys = collect($mappedItems)
            ->map(fn (array $item): ?string => is_scalar($item['meta']['external_field'] ?? null) ? $this->normalize($item['meta']['external_field']) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $mappedExternalValues = collect($mappedItems)
            ->map(fn (array $item): ?string => is_scalar($item['meta']['external_value'] ?? null) ? $this->normalize($item['meta']['external_value']) : null)
            ->filter()
            ->flatMap(fn (string $value) => preg_split('/\s*\|\s*/', $value) ?: [])
            ->map(fn (string $value): string => $this->normalize($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $received = [];
        $productCandidates = [];
        $unmappedProductFields = [];
        $discardedUrlFields = [];
        $flowFields = [];
        $customerFields = [];

        foreach ($fields as $field) {
            $normalizedKey = $this->normalize($field['key']);
            $normalizedValue = $this->normalize($field['value']);
            $role = $this->fieldRole($normalizedKey, $normalizedValue);
            $field['role'] = $role;
            $field['mapped'] = false;

            if ($role === 'discarded_url') {
                $field['mapped'] = false;
                $discardedUrlFields[] = $field;
                $received[] = $field;
                continue;
            }

            if ($role === 'flow_key') {
                $flowFields[] = $field;
                $field['mapped'] = true;
            } elseif (in_array($role, ['customer_name', 'customer_phone', 'customer_address', 'customer_note'], true)) {
                $customerFields[] = $field;
                $field['mapped'] = true;
            } elseif ($role === 'product_candidate') {
                $fieldMatched = in_array($normalizedKey, $mappedFieldKeys, true)
                    || ($normalizedValue !== '' && in_array($normalizedValue, $mappedExternalValues, true));
                $field['mapped'] = $fieldMatched;
                $productCandidates[] = $field;
                if (! $fieldMatched) {
                    $unmappedProductFields[] = $field;
                }
            }

            $received[] = $field;
        }

        $mappedItemSummary = collect($mappedItems)->map(fn (array $item): array => [
            'product_id' => $item['product_id'] ?? null,
            'name' => $item['product_name'] ?? $item['name'] ?? null,
            'quantity' => (int) ($item['quantity'] ?? 1),
            'unit_price' => (int) ($item['unit_price'] ?? $item['price'] ?? 0),
            'item_type' => $item['item_type'] ?? $item['type'] ?? ($source->isSupplemental() ? 'upsell' : 'product'),
            'external_field' => $item['meta']['external_field'] ?? null,
            'external_value' => $item['meta']['external_value'] ?? null,
        ])->values()->all();

        $candidateText = collect($productCandidates)
            ->pluck('value')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '' && ! LandingProductLabel::looksLikeUrl($value))
            ->unique()
            ->implode(', ');

        // Không gắn URL monitor hint vào product_candidate_text — chỉ audit discarded_url_fields.

        return [
            'version' => 'v130',
            'connection_id' => $connection->id,
            'source_id' => $source->id,
            'source_type' => $source->source_type,
            'source_url' => $source->source_url,
            'flow_token' => $flowToken,
            'match_keys_present' => collect($flowFields)->pluck('key')->values()->all(),
            'customer_fields' => $customerFields,
            'received_field_count' => count($received),
            'received_fields' => $received,
            'product_candidate_text' => $candidateText,
            'product_candidates' => $productCandidates,
            'discarded_url_fields' => $discardedUrlFields,
            'mapped_items' => $mappedItemSummary,
            'unmapped_product_fields' => $unmappedProductFields,
            'has_product_mapping_gap' => $mappedItemSummary === [] && ($productCandidates !== [] || $discardedUrlFields !== []),
            'has_no_product_signal' => $mappedItemSummary === [] && $productCandidates === [] && $discardedUrlFields === [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{key:string,label:string,value:string,path:string}>
     */
    private function flattenSubmittedFields(array $input): array
    {
        $fields = [];
        $put = function (string $key, mixed $value, string $path) use (&$fields, &$put): void {
            if (is_array($value)) {
                foreach ($value as $index => $entry) {
                    if (is_scalar($entry) && trim((string) $entry) !== '') {
                        $put($key, $entry, $path.'.'.$index);

                        return;
                    }
                }

                return;
            }
            if (! is_scalar($value)) {
                return;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            $label = trim($key) !== '' ? trim($key) : $path;
            $fields[] = [
                'key' => Str::of($label)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value(),
                'label' => $label,
                'value' => mb_substr($value, 0, 500),
                'path' => $path,
            ];
        };

        foreach ($input as $key => $value) {
            if (! is_string($key) || str_starts_with($key, '_')) {
                continue;
            }
            if (in_array($key, ['fields', 'form_data', 'items'], true)) {
                continue;
            }
            $put($key, $value, $key);
        }

        foreach ((array) Arr::get($input, 'fields', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? $row['key'] ?? $row['field'] ?? 'field_'.$index);
            $put($name, $row['value'] ?? $row['answer'] ?? $row['values'] ?? null, 'fields.'.$index);
        }

        foreach ((array) Arr::get($input, 'form_data', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? $row['key'] ?? $row['field'] ?? 'form_data_'.$index);
            $put($name, $row['value'] ?? $row['answer'] ?? $row['values'] ?? null, 'form_data.'.$index);
        }

        foreach ((array) Arr::get($input, 'items', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? $row['product_name'] ?? 'items_'.$index);
            $put('items_'.$index, $name, 'items.'.$index.'.name');
        }

        return collect($fields)
            ->unique(fn (array $field): string => $field['path'].'|'.$field['key'].'|'.$field['value'])
            ->values()
            ->all();
    }

    private function fieldRole(string $normalizedKey, string $normalizedValue): string
    {
        // Page/tracking URLs must never count as product candidates (LadiPage often
        // posts the landing URL into a field named products / san_pham).
        if (LandingProductLabel::looksLikeUrl($normalizedValue)) {
            return 'discarded_url';
        }

        if (preg_match('/(^|_)(ps_flow|saleops_session|session_id|session_key|saleops_client_ref|flow_token|client_ref|parent_ref|parent_submission_id)($|_)/', $normalizedKey)) {
            return 'flow_key';
        }
        if (preg_match('/(^|_)(phone|dien_thoai|so_dien_thoai|sdt|mobile|tel|landing_phone|phone_landing)($|_)/', $normalizedKey)) {
            return 'customer_phone';
        }
        if (preg_match('/(^|_)(name|full_name|ho_ten|customer_name)($|_)/', $normalizedKey)) {
            return 'customer_name';
        }
        if (preg_match('/(^|_)(address|dia_chi|diachi|shipping_address)($|_)/', $normalizedKey)) {
            return 'customer_address';
        }
        if (preg_match('/(^|_)(message|note|ghi_chu|tin_nhan)($|_)/', $normalizedKey)) {
            return 'customer_note';
        }
        if (preg_match('/(combo|package|goi|san_pham|product|mua_them|muathem|upsell|addon|add_on|sku|form_item|(^|_)sp($|_))/', $normalizedKey)) {
            return 'product_candidate';
        }
        if (preg_match('/(combo|goi|mua them|upsell|san pham|\d+\s*k|\d+\s*vn)/i', $normalizedValue)) {
            return 'product_candidate';
        }

        return 'unmapped_field';
    }

    /** @param array<string, mixed> $input @return list<array<string, mixed>> */
    private function configuredItems(LandingConnection $connection, LandingConnectionSource $source, array $input): array
    {
        $mappings = $connection->products
            ->filter(fn (LandingConnectionProduct $mapping) => (
                $mapping->landing_connection_source_id === null
                || (int) $mapping->landing_connection_source_id === (int) $source->id
            ) && $mapping->product?->is_active)
            ->values();

        // A mapping without external_field is a fixed bundle line and is always included.
        // Conditional mappings are selected by the submitted field; when none match, configured
        // defaults are used. This lets a connection express: fixed base items + one selected package.
        $fixed = $mappings->filter(fn (LandingConnectionProduct $mapping) => ! filled($mapping->external_field));
        $conditional = $mappings->filter(fn (LandingConnectionProduct $mapping) => filled($mapping->external_field));
        $matchedConditional = $conditional->filter(fn (LandingConnectionProduct $mapping) => $this->matches($mapping, $input));

        if ($matchedConditional->isEmpty()) {
            $matchedConditional = $conditional->filter(fn (LandingConnectionProduct $mapping) => $mapping->is_default);
        }

        $matched = $fixed
            ->concat($matchedConditional)
            ->unique(fn (LandingConnectionProduct $mapping) => $mapping->id ?: spl_object_id($mapping))
            ->values();

        return $matched->map(function (LandingConnectionProduct $mapping) use ($source): array {
            $product = $mapping->product;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'product_name' => $product->name,
                'unit_price' => $mapping->unit_price_override ?? $product->unit_price,
                'price' => $mapping->unit_price_override ?? $product->unit_price,
                'quantity' => max(1, $mapping->quantity),
                'item_type' => $source->isSupplemental() ? 'upsell' : $mapping->item_type,
                'type' => $source->isSupplemental() ? 'upsell' : $mapping->item_type,
                'origin' => 'landing_connection',
                'meta' => [
                    'landing_connection_product_id' => $mapping->id,
                    'source_id' => $source->id,
                    'external_field' => $mapping->external_field,
                    'external_value' => $mapping->external_value,
                ],
            ];
        })->values()->all();
    }

    /**
     * LadiPage hay gửi gói/upsell dạng form_item / form_item[2] / form_item12 (string hoặc array text).
     * Không có giá catalog → dòng text-only (unit_price = 0), sale sửa tay sau.
     *
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function softFormItemLines(array $input, LandingConnectionSource $source): array
    {
        $lines = [];
        $seen = [];

        $push = function (string $label, string $fieldKey) use (&$lines, &$seen, $source): void {
            $safe = LandingProductLabel::sanitizeName($label);
            if ($safe === null || isset($seen[mb_strtolower($safe)])) {
                return;
            }
            $seen[mb_strtolower($safe)] = true;

            $price = 0;
            if (preg_match('/([0-9][0-9.,]*)\s*(k|nghìn|nghin|ngàn|ngan|đ|vnđ|vnd|tr|triệu|trieu)\b/iu', $safe, $m)) {
                $aroundOffset = mb_stripos($safe, $m[0]);
                $around = $aroundOffset === false ? '' : mb_substr($safe, max(0, $aroundOffset - 8), mb_strlen($m[0]) + 16);
                if (preg_match('/(ship|phí\s*vc|phi\s*vc|vận\s*chuyển|van\s*chuyen)/iu', $around) !== 1) {
                    $price = \App\Support\MoneyParser::parse($m[1].$m[2]);
                }
            }

            $lines[] = [
                'product_id' => null,
                'name' => $safe,
                'product_name' => $safe,
                'unit_price' => $price,
                'price' => $price,
                'quantity' => 1,
                'item_type' => $source->isSupplemental() ? 'upsell' : 'combo',
                'type' => $source->isSupplemental() ? 'upsell' : 'combo',
                'origin' => 'landing_form_item',
                'meta' => [
                    'external_field' => $fieldKey,
                    'external_value' => $safe,
                    'raw_label' => $label,
                    'text_only' => $price <= 0,
                ],
            ];
        };

        foreach ($input as $key => $value) {
            if (! is_string($key) || preg_match('/^form_item/i', $key) !== 1) {
                continue;
            }
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $entry) {
                if (is_scalar($entry) && trim((string) $entry) !== '') {
                    $push((string) $entry, $key);
                }
            }
        }

        return $lines;
    }

    /** @param array<string, mixed> $input */
    private function matches(LandingConnectionProduct $mapping, array $input): bool
    {
        $actual = $this->fieldValue($input, (string) $mapping->external_field);
        $actualValues = is_array($actual) ? $actual : [$actual];
        $expectedValues = preg_split('/\s*\|\s*/', (string) $mapping->external_value) ?: [];
        $normalizedExpected = array_values(array_filter(
            array_map(fn (mixed $value): string => $this->normalize($value), $expectedValues),
            fn (string $value): bool => $value !== '',
        ));

        if ($normalizedExpected === []) {
            return false;
        }

        foreach ($actualValues as $value) {
            $normalized = $this->normalize($value);
            if ($normalized !== '' && in_array($normalized, $normalizedExpected, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $input */
    private function fieldValue(array $input, string $field): mixed
    {
        $actual = Arr::get($input, $field);
        if ($actual !== null || Arr::has($input, $field)) {
            return $actual;
        }

        $fields = $input['fields'] ?? null;
        if (! is_array($fields)) {
            return null;
        }

        if (array_key_exists($field, $fields)) {
            return $fields[$field];
        }

        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = $row['name'] ?? $row['key'] ?? $row['field'] ?? null;
            if (is_scalar($name) && $this->normalize($name) === $this->normalize($field)) {
                return $row['value'] ?? $row['answer'] ?? $row['values'] ?? null;
            }
        }

        return null;
    }

    private function normalize(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return Str::of((string) $value)->ascii()->lower()->squish()->value();
    }
}
