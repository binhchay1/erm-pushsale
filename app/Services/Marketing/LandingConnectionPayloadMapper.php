<?php

namespace App\Services\Marketing;

use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSource;
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
        if ($items !== []) {
            $payload['items'] = $items;
            $payload['products'] = collect($items)->pluck('product_name')->implode(', ');
            $payload['product_interest'] = $payload['products'];
        }

        return $payload;
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
