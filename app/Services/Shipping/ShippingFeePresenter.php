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
                'message' => $message ?: 'Không tính được phí vận chuyển.',
                'lines' => [],
                'options' => [],
            ];
        }

        // VTP getPriceAll — nhiều gói dịch vụ
        if (is_array($data) && $this->isListOfServices($data)) {
            $options = collect($data)->map(function (array $row) {
                $name = (string) ($row['SERVICE_NAME'] ?? $row['service_name'] ?? $row['name'] ?? 'Gói dịch vụ');
                $fee = $this->money($row['MONEY_TOTAL'] ?? $row['money_total'] ?? $row['fee'] ?? 0);
                $time = $row['KPI_HT'] ?? $row['delivery_time'] ?? null;

                return [
                    'label' => $name,
                    'value' => $this->formatMoney($fee),
                    'note' => $time ? "Dự kiến: {$time}" : null,
                ];
            })->values()->all();

            $cheapest = collect($data)->sortBy(fn ($r) => (int) ($r['MONEY_TOTAL'] ?? $r['money_total'] ?? 0))->first();

            return [
                'success' => true,
                'message' => $message,
                'lines' => $cheapest ? [[
                    'label' => 'Phí thấp nhất',
                    'value' => $this->formatMoney($this->money($cheapest['MONEY_TOTAL'] ?? $cheapest['money_total'] ?? 0)),
                    'highlight' => true,
                ]] : [],
                'options' => $options,
            ];
        }

        $payload = is_array($data) ? $data : [];

        // GHTK: { fee, insurance_fee, ... } hoặc nested trong data.fee
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
            'Phí vận chuyển' => $payload['fee'] ?? $payload['ship_fee'] ?? $payload['MONEY_TOTAL'] ?? $payload['total_fee'] ?? null,
            'Phí bảo hiểm' => $payload['insurance_fee'] ?? $payload['MONEY_COLLECTION_FEE'] ?? null,
            'Phí thu hộ (COD)' => $payload['cod_fee'] ?? $payload['codFee'] ?? null,
            'Phí ngoại thành' => $payload['extFees'] ?? $payload['ext_fee'] ?? null,
            'Tổng phí' => $payload['total'] ?? $payload['MONEY_TOTALFEE'] ?? $payload['MONEY_TOTAL'] ?? null,
            'Thời gian giao dự kiến' => $payload['delivery_time'] ?? $payload['estimated_deliver_time'] ?? $payload['leadtime'] ?? null,
        ];

        $lines = [];
        $highlightSet = false;

        foreach ($map as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $isMoney = ! in_array($label, ['Thời gian giao dự kiến'], true);
            $formatted = $isMoney ? $this->formatMoney($this->money($value)) : (string) $value;
            $highlight = ! $highlightSet && in_array($label, ['Phí vận chuyển', 'Tổng phí'], true);

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
        return number_format($amount, 0, ',', '.').'đ';
    }
}
