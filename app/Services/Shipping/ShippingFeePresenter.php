<?php

namespace App\Services\Shipping;

/**
 * Chuẩn hóa kết quả tính phí từ nhiều hãng VC → mảng dòng hiển thị cho giao diện.
 */
class ShippingFeePresenter
{
    /**
     * @param  array<string, mixed>  $response
     * @return array{
     *     success: bool,
     *     message: ?string,
     *     lines: list<array{label: string, value: string, highlight?: bool}>,
     *     options: list<array{label: string, value: string, note?: ?string}>
     * }
     */
    public function present(array $response): array
    {
        $success = (bool) ($response['success'] ?? true);
        $message = isset($response['message']) ? (string) $response['message'] : null;
        $data = $response['data'] ?? $response['raw']['data'] ?? null;

        if (! $success) {
            return [
                'success' => false,
                'message' => $message ?: __('shipping.fee.unavailable'),
                'lines' => [],
                'options' => [],
            ];
        }

        if (is_array($data) && $this->isListOfServices($data)) {
            $options = collect($data)->map(function (array $row) {
                $name = (string) ($row['SERVICE_NAME'] ?? $row['service_name'] ?? $row['name'] ?? __('shipping.fee.service_package'));
                $fee = $this->money($row['MONEY_TOTAL'] ?? $row['money_total'] ?? $row['fee'] ?? 0);
                $time = $row['KPI_HT'] ?? $row['delivery_time'] ?? null;

                return [
                    'label' => $name,
                    'value' => $this->formatMoney($fee),
                    'note' => $time ? __('shipping.fee.eta_prefix', ['time' => $time]) : null,
                ];
            })->values()->all();

            $cheapest = collect($data)->sortBy(fn ($r) => (int) ($r['MONEY_TOTAL'] ?? $r['money_total'] ?? 0))->first();

            return [
                'success' => true,
                'message' => $message,
                'lines' => $cheapest ? [[
                    'label' => __('shipping.fee.lowest'),
                    'value' => $this->formatMoney($this->money($cheapest['MONEY_TOTAL'] ?? $cheapest['money_total'] ?? 0)),
                    'highlight' => true,
                ]] : [],
                'options' => $options,
            ];
        }

        $payload = is_array($data) ? $data : [];

        if (isset($payload['fee']) && is_array($payload['fee'])) {
            $payload = $payload['fee'];
        }

        $lines = $this->linesFromPayload($payload);

        if ($lines === [] && isset($response['fee'])) {
            $lines = $this->linesFromPayload(['fee' => $response['fee']]);
        }

        return [
            'success' => true,
            'message' => $message,
            'lines' => $lines,
            'options' => [],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function linesFromPayload(array $payload): array
    {
        $map = [
            __('shipping.fee.shipping') => $payload['fee'] ?? $payload['ship_fee'] ?? $payload['MONEY_TOTAL'] ?? $payload['total_fee'] ?? null,
            __('shipping.fee.insurance') => $payload['insurance_fee'] ?? $payload['MONEY_COLLECTION_FEE'] ?? null,
            __('shipping.fee.cod') => $payload['cod_fee'] ?? $payload['codFee'] ?? null,
            __('shipping.fee.remote') => $payload['extFees'] ?? $payload['ext_fee'] ?? null,
            __('shipping.fee.total') => $payload['total'] ?? $payload['MONEY_TOTALFEE'] ?? $payload['MONEY_TOTAL'] ?? null,
            __('shipping.fee.eta') => $payload['delivery_time'] ?? $payload['estimated_deliver_time'] ?? $payload['leadtime'] ?? null,
        ];

        $lines = [];
        $highlightSet = false;
        $highlightKeys = [__('shipping.fee.shipping'), __('shipping.fee.total')];
        $etaKey = __('shipping.fee.eta');

        foreach ($map as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $isMoney = $label !== $etaKey;
            $formatted = $isMoney ? $this->formatMoney($this->money($value)) : (string) $value;
            $highlight = ! $highlightSet && in_array($label, $highlightKeys, true);

            if ($highlight) {
                $highlightSet = true;
            }

            $lines[] = [
                'label' => $label,
                'value' => $formatted,
                'highlight' => $highlight,
            ];
        }

        return $lines;
    }

    /** @param  array<int|string, mixed>  $data */
    private function isListOfServices(array $data): bool
    {
        if ($data === [] || ! array_is_list($data)) {
            return false;
        }

        $first = $data[0];

        return is_array($first) && (
            isset($first['SERVICE_NAME']) || isset($first['service_name']) || isset($first['MONEY_TOTAL'])
        );
    }

    private function money(mixed $value): int
    {
        return (int) preg_replace('/[^\d-]/', '', (string) $value);
    }

    private function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' ₫';
    }
}
