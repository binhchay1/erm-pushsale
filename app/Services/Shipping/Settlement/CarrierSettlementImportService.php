<?php

namespace App\Services\Shipping\Settlement;

use App\Models\CarrierSettlementBatch;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class CarrierSettlementImportService
{
    public function __construct(private readonly CarrierSettlementSyncService $sync) {}

    /**
     * CSV cột hỗ trợ (header không phân biệt hoa thường):
     * tracking_number, partner_order_code, cod_amount, carrier_fee, net_amount, transaction_code, settled_at
     */
    public function importCsv(
        string $provider,
        UploadedFile $file,
        ?string $settlementCode = null,
        ?Carbon $periodFrom = null,
        ?Carbon $periodTo = null,
    ): array {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw new RuntimeException(__('messages.settlement.file_unreadable'));
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException(__('messages.settlement.file_unreadable'));
        }

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => Str::snake(Str::lower(trim((string) $h))), $data);

                continue;
            }

            if (count(array_filter($data)) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($header as $i => $key) {
                $assoc[$key] = $data[$i] ?? null;
            }

            $rows[] = $this->normalizeRow($assoc);
        }

        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException(__('messages.settlement.empty_file'));
        }

        $code = $settlementCode ?: 'import-'.now()->format('Ymd-His');
        $batch = $this->sync->ingestBatch(
            $provider,
            CarrierSettlementBatch::SOURCE_IMPORT,
            $code,
            $rows,
            $periodFrom,
            $periodTo,
            ['filename' => $file->getClientOriginalName()],
        );

        return [
            'batch_id' => $batch->id,
            'settlement_code' => $batch->settlement_code,
            'lines_total' => $batch->lines_total,
            'lines_matched' => $batch->lines_matched,
            'lines_unmatched' => $batch->lines_unmatched,
            'cod_total' => $batch->cod_total,
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function normalizeRow(array $row): array
    {
        $tracking = $row['tracking_number'] ?? $row['tracking'] ?? $row['waybill'] ?? $row['billcode'] ?? null;
        $partner = $row['partner_order_code'] ?? $row['order_code'] ?? $row['order_number'] ?? null;
        $cod = $row['cod_amount'] ?? $row['cod'] ?? $row['money_collection'] ?? $row['pick_money'] ?? 0;

        return [
            'tracking_number' => filled($tracking) ? (string) $tracking : null,
            'partner_order_code' => filled($partner) ? (string) $partner : null,
            'cod_amount' => (int) preg_replace('/[^\d-]/', '', (string) $cod),
            'carrier_fee' => (int) preg_replace('/[^\d-]/', '', (string) ($row['carrier_fee'] ?? $row['fee'] ?? 0)),
            'net_amount' => isset($row['net_amount']) ? (int) preg_replace('/[^\d-]/', '', (string) $row['net_amount']) : null,
            'transaction_code' => filled($row['transaction_code'] ?? null) ? (string) $row['transaction_code'] : null,
            'settled_at' => $row['settled_at'] ?? $row['settlement_date'] ?? null,
            'raw_payload' => $row,
        ];
    }
}
