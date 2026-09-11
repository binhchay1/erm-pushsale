<?php

namespace App\Services\Warehouse;

use App\Enums\DeliveryStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Order;
use App\Models\ReconciliationImportBatch;
use App\Models\ReconciliationImportRow;
use App\Models\User;
use App\Support\SpreadsheetLeadReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReconciliationBulkService
{
    public const MAX_EXCEL_ROWS = 2000;

    public function __construct(
        private readonly BulkUpdateByCodeService $byCode,
    ) {}

    /**
     * @return array{
     *   found:int,
     *   missing:list<string>,
     *   by_delivery_status:list<array{value:string,label:string,count:int}>,
     *   by_reconciliation:list<array{value:string,label:string,count:int}>,
     *   rows:list<array<string,mixed>>
     * }
     */
    public function inspect(string $codesRaw, string $codeType = 'MHT', bool $isGhtk = false): array
    {
        $codes = $this->byCode->parseCodes($codesRaw);
        if ($codes === []) {
            throw ValidationException::withMessages(['codes' => 'Nhập danh sách mã đơn.']);
        }

        $found = [];
        $missing = [];
        foreach ($codes as $code) {
            $order = $this->resolveOrder($code, $codeType, $isGhtk);
            if (! $order) {
                $missing[] = $code;
                continue;
            }
            $found[] = $order;
        }

        $byDelivery = collect($found)
            ->groupBy(fn (Order $order) => (string) ($order->delivery_status ?: 'unknown'))
            ->map(fn (Collection $group, string $value) => [
                'value' => $value,
                'label' => DeliveryStatus::tryFrom($value)?->label() ?? $value,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $byRecon = collect($found)
            ->groupBy(function (Order $order) {
                $status = (string) ($order->reconciliation_status ?: 'pending');

                return in_array($status, ReconciliationStatus::settledStatuses(), true) ? 'reconciled' : 'pending';
            })
            ->map(fn (Collection $group, string $value) => [
                'value' => $value,
                'label' => $value === 'reconciled' ? 'Đã đối soát' : 'Chưa đối soát',
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        return [
            'found' => count($found),
            'missing' => $missing,
            'by_delivery_status' => $byDelivery,
            'by_reconciliation' => $byRecon,
            'rows' => collect($found)->map(fn (Order $order) => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'tracking_number' => $order->tracking_number,
                'delivery_status' => $order->delivery_status,
                'delivery_status_label' => DeliveryStatus::tryFrom((string) $order->delivery_status)?->label() ?? $order->delivery_status,
                'reconciliation_status' => $order->reconciliation_status,
                'reconciliation_status_label' => ReconciliationStatus::tryFrom((string) $order->reconciliation_status)?->label()
                    ?? ($order->reconciliation_status ?: '—'),
                'reconciled' => in_array((string) $order->reconciliation_status, ReconciliationStatus::settledStatuses(), true),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{message:string,success_count:int,failed_count:int,results:list<array<string,mixed>>}
     */
    public function updateByCodes(
        string $codesRaw,
        string $reconciliationStatus,
        string $codeType = 'MHT',
        bool $isGhtk = false,
        ?string $note = null,
        ?User $actor = null,
    ): array {
        return $this->byCode->execute([
            'action' => 'CAP_NHAT_TTDS',
            'code_type' => $codeType,
            'is_ghtk' => $isGhtk,
            'codes' => $codesRaw,
            'reconciliation_status' => $reconciliationStatus,
            'note' => $note,
        ], $actor);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mau');
        $sheet->fromArray([array_values($this->templateHeaders())], null, 'A1');
        $sheet->fromArray([
            ['PS00000000001PS', '', 'reconciled', 'Đã đối soát'],
            ['', 'TRACKING001', 'pending', ''],
        ], null, 'A2');

        $legend = $spreadsheet->createSheet();
        $legend->setTitle('TrangThai');
        $legend->fromArray(['value', 'label'], null, 'A1');
        $row = 2;
        foreach ($this->statusCatalog() as $status) {
            $legend->fromArray([$status['value'], $status['label']], null, 'A'.$row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'mau-cap-nhat-doi-soat.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{batch:array<string,mixed>,counts:array<string,int>}
     */
    public function uploadExcel(UploadedFile $file, bool $isGhtk, ?User $actor): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (! in_array($ext, SpreadsheetLeadReader::ALLOWED, true)) {
            throw ValidationException::withMessages(['file' => 'Chỉ nhận file csv/xls/xlsx.']);
        }

        $path = $file->getRealPath();
        if (! $path) {
            throw ValidationException::withMessages(['file' => 'Không đọc được file.']);
        }

        $sheets = SpreadsheetLeadReader::sheets($path, $ext);
        $matrix = $sheets[array_key_first($sheets)] ?? [];
        if ($matrix === []) {
            throw ValidationException::withMessages(['file' => 'File trống.']);
        }

        $parsed = $this->parseSpreadsheetMatrix($matrix);
        if ($parsed === []) {
            throw ValidationException::withMessages(['file' => 'Không tìm thấy dòng dữ liệu hợp lệ (cần mã đơn hoặc mã vận đơn).']);
        }
        if (count($parsed) > self::MAX_EXCEL_ROWS) {
            throw ValidationException::withMessages(['file' => 'Danh sách tối đa '.self::MAX_EXCEL_ROWS.' dòng.']);
        }

        return DB::transaction(function () use ($parsed, $isGhtk, $actor, $file) {
            $batch = ReconciliationImportBatch::query()->create([
                'created_by_user_id' => $actor?->id,
                'batch_code' => 'TTDS-'.now()->format('YmdHis').'-'.Str::lower(Str::random(4)),
                'filename' => $file->getClientOriginalName(),
                'is_ghtk' => $isGhtk,
                'state' => 'uploaded',
                'uploaded_at' => now(),
                'meta' => ['headers' => $this->templateHeaders()],
            ]);

            foreach ($parsed as $row) {
                $order = $this->resolveFromExcelRow($row, $isGhtk);
                $status = $this->normalizeStatus($row['reconciliation_status'] ?? null);
                ReconciliationImportRow::query()->create([
                    'batch_id' => $batch->id,
                    'order_id' => $order?->id,
                    'order_code' => $row['order_code'] ?: $order?->order_code,
                    'tracking_number' => $row['tracking_number'] ?: $order?->tracking_number,
                    'reconciliation_status_raw' => $row['reconciliation_status'],
                    'reconciliation_status' => $status,
                    'note' => $row['note'],
                    'process_status' => 'pending',
                    'result_status' => 'pending',
                    'message' => $order ? null : 'Chưa khớp đơn',
                ]);
            }

            $counts = $batch->recount();

            return [
                'batch' => $this->presentBatch($batch->fresh()),
                'counts' => $counts,
            ];
        });
    }

    /**
     * @return array{batch:array<string,mixed>,counts:array<string,int>,results:list<array<string,mixed>>}
     */
    public function applyBatch(ReconciliationImportBatch $batch, ?User $actor): array
    {
        if ($batch->state === 'cleared') {
            throw ValidationException::withMessages(['batch' => 'Batch đã xóa.']);
        }

        $results = [];
        $rows = $batch->rows()->where('process_status', 'pending')->orderBy('id')->get();

        foreach ($rows as $row) {
            try {
                $order = $row->order_id
                    ? Order::query()->find($row->order_id)
                    : $this->resolveFromExcelRow([
                        'order_code' => $row->order_code,
                        'tracking_number' => $row->tracking_number,
                        'reconciliation_status' => $row->reconciliation_status,
                        'note' => $row->note,
                    ], (bool) $batch->is_ghtk);

                if (! $order) {
                    throw ValidationException::withMessages(['order' => 'Không tìm thấy đơn.']);
                }
                if (! filled($row->reconciliation_status)) {
                    throw ValidationException::withMessages(['reconciliation_status' => 'Thiếu trạng thái đối soát.']);
                }

                $payload = [
                    'action' => 'CAP_NHAT_TTDS',
                    'code_type' => 'MHT',
                    'codes' => (string) $order->order_code,
                    'reconciliation_status' => (string) $row->reconciliation_status,
                    'note' => filled($row->note) ? (string) $row->note : null,
                ];
                $result = $this->byCode->execute($payload, $actor);
                if (($result['success_count'] ?? 0) < 1) {
                    $message = $result['results'][0]['message'] ?? 'Không cập nhật được đối soát.';
                    throw ValidationException::withMessages(['reconciliation_status' => $message]);
                }

                $row->forceFill([
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'process_status' => 'processed',
                    'result_status' => 'success',
                    'message' => 'Đã cập nhật',
                    'processed_at' => now(),
                ])->save();

                $results[] = ['id' => $row->id, 'ok' => true, 'order_code' => $order->order_code, 'message' => 'Đã cập nhật'];
            } catch (Throwable $e) {
                $row->forceFill([
                    'process_status' => 'processed',
                    'result_status' => 'error',
                    'message' => $e->getMessage() ?: 'Lỗi',
                    'processed_at' => now(),
                ])->save();
                $results[] = ['id' => $row->id, 'ok' => false, 'order_code' => $row->order_code, 'message' => $row->message];
            }
        }

        $batch->forceFill([
            'state' => 'applied',
            'applied_at' => now(),
        ])->save();
        $counts = $batch->recount();

        return [
            'batch' => $this->presentBatch($batch->fresh()),
            'counts' => $counts,
            'results' => $results,
        ];
    }

    public function clearBatch(ReconciliationImportBatch $batch): void
    {
        $batch->rows()->delete();
        $batch->forceFill([
            'state' => 'cleared',
            'total_count' => 0,
            'processed_count' => 0,
            'pending_count' => 0,
            'success_count' => 0,
            'error_count' => 0,
        ])->save();
    }

    /**
     * @param  array{search?:string,process_status?:string,result_status?:string,page?:int,per_page?:int}  $filters
     * @return array{batch:?array<string,mixed>,counts:array<string,int>,rows:array<string,mixed>,status_catalog:list<array{value:string,label:string}>}
     */
    public function history(?int $batchId, array $filters, ?User $actor): array
    {
        $batchQuery = ReconciliationImportBatch::query()
            ->where('state', '!=', 'cleared')
            ->when($actor, fn ($q) => $q->where('created_by_user_id', $actor->id))
            ->latest('id');

        $batch = $batchId
            ? ReconciliationImportBatch::query()->find($batchId)
            : $batchQuery->first();

        if (! $batch) {
            return [
                'batch' => null,
                'counts' => ['total' => 0, 'processed' => 0, 'pending' => 0, 'success' => 0, 'error' => 0],
                'rows' => ['data' => [], 'meta' => null],
                'status_catalog' => $this->statusCatalog(),
            ];
        }

        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 15)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = $batch->rows()->latest('id');
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('order_code', 'like', '%'.$search.'%')
                    ->orWhere('tracking_number', 'like', '%'.$search.'%');
            });
        }
        if ($process = trim((string) ($filters['process_status'] ?? ''))) {
            $query->where('process_status', $process);
        }
        if ($result = trim((string) ($filters['result_status'] ?? ''))) {
            $query->where('result_status', $result);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $counts = [
            'total' => (int) $batch->total_count,
            'processed' => (int) $batch->processed_count,
            'pending' => (int) $batch->pending_count,
            'success' => (int) $batch->success_count,
            'error' => (int) $batch->error_count,
        ];

        return [
            'batch' => $this->presentBatch($batch),
            'counts' => $counts,
            'rows' => [
                'data' => collect($paginator->items())->map(fn (ReconciliationImportRow $row) => [
                    'id' => $row->id,
                    'order_code' => $row->order_code,
                    'tracking_number' => $row->tracking_number,
                    'reconciliation_status' => $row->reconciliation_status,
                    'reconciliation_status_label' => $row->reconciliation_status
                        ? (ReconciliationStatus::tryFrom($row->reconciliation_status)?->label() ?? $row->reconciliation_status)
                        : ($row->reconciliation_status_raw ?: '—'),
                    'process_status' => $row->process_status,
                    'result_status' => $row->result_status,
                    'message' => $row->message,
                    'processed_at' => $row->processed_at?->format('d/m/Y H:i'),
                ])->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'status_catalog' => $this->statusCatalog(),
        ];
    }

    /** @return list<array{value:string,label:string}> */
    public function statusCatalog(): array
    {
        return [
            ['value' => ReconciliationStatus::Pending->value, 'label' => ReconciliationStatus::Pending->label()],
            ['value' => ReconciliationStatus::Reconciled->value, 'label' => ReconciliationStatus::Reconciled->label()],
        ];
    }

    /** @return array<string,string> key => header label */
    public function templateHeaders(): array
    {
        return [
            'order_code' => 'Mã đơn',
            'tracking_number' => 'Mã giao vận',
            'reconciliation_status' => 'Trạng thái đối soát',
            'note' => 'Ghi chú',
        ];
    }

    private function resolveOrder(string $code, string $codeType, bool $isGhtk): ?Order
    {
        $query = Order::query();
        if ($codeType === 'MGV') {
            return $query->where(function ($builder) use ($code) {
                $builder->where('tracking_number', $code)
                    ->orWhereHas('shipments', function ($shipments) use ($code) {
                        $shipments->where('tracking_number', $code)
                            ->orWhere('partner_order_id', $code)
                            ->orWhere('tracking_id', $code);
                    });
            })->when($isGhtk, function ($builder) {
                $builder->where(function ($inner) {
                    $inner->where('shipping_provider', 'ghtk')
                        ->orWhereHas('shipments', fn ($shipments) => $shipments->where('provider', 'ghtk'));
                });
            })->first();
        }

        return $query->where('order_code', $code)->first();
    }

    /**
     * @param  list<list<string>>  $matrix
     * @return list<array{order_code:?string,tracking_number:?string,reconciliation_status:?string,note:?string}>
     */
    private function parseSpreadsheetMatrix(array $matrix): array
    {
        if ($matrix === []) {
            return [];
        }

        $headerRow = array_map(fn ($cell) => Str::lower(trim((string) $cell)), $matrix[0]);
        $map = $this->mapHeaderIndexes($headerRow);
        $hasHeader = $map !== null;
        $start = $hasHeader ? 1 : 0;
        if (! $hasHeader) {
            $map = ['order_code' => 0, 'tracking_number' => 1, 'reconciliation_status' => 2, 'note' => 3];
        }

        $rows = [];
        for ($i = $start; $i < count($matrix); $i++) {
            $line = $matrix[$i];
            $orderCode = trim((string) ($line[$map['order_code']] ?? ''));
            $tracking = trim((string) ($line[$map['tracking_number']] ?? ''));
            $status = trim((string) ($line[$map['reconciliation_status']] ?? ''));
            $note = trim((string) ($line[$map['note']] ?? ''));
            if ($orderCode === '' && $tracking === '') {
                continue;
            }
            $rows[] = [
                'order_code' => $orderCode !== '' ? $orderCode : null,
                'tracking_number' => $tracking !== '' ? $tracking : null,
                'reconciliation_status' => $status !== '' ? $status : null,
                'note' => $note !== '' ? $note : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headerRow
     * @return array{order_code:int,tracking_number:int,reconciliation_status:int,note:int}|null
     */
    private function mapHeaderIndexes(array $headerRow): ?array
    {
        $aliases = [
            'order_code' => ['mã đơn', 'ma don', 'order_code', 'madon', 'mã pushsale', 'ma pushsale'],
            'tracking_number' => ['mã giao vận', 'ma giao van', 'tracking', 'tracking_number', 'mã vận đơn', 'ma van don'],
            'reconciliation_status' => [
                'trạng thái đối soát', 'trang thai doi soat', 'reconciliation_status',
                'trạng thái cập nhật', 'trang thai cap nhat', 'đối soát', 'doi soat', 'dsnb',
            ],
            'note' => ['ghi chú', 'ghi chu', 'note'],
        ];

        $map = [];
        foreach ($aliases as $key => $names) {
            foreach ($headerRow as $index => $label) {
                $normalized = Str::of($label)->replaceMatches('/\s+/', ' ')->trim()->value();
                if (in_array($normalized, $names, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }

        if (! isset($map['order_code']) && ! isset($map['tracking_number'])) {
            return null;
        }

        return [
            'order_code' => $map['order_code'] ?? 999,
            'tracking_number' => $map['tracking_number'] ?? 999,
            'reconciliation_status' => $map['reconciliation_status'] ?? 999,
            'note' => $map['note'] ?? 999,
        ];
    }

    /**
     * @param  array{order_code:?string,tracking_number:?string,reconciliation_status:?string,note:?string}  $row
     */
    private function resolveFromExcelRow(array $row, bool $isGhtk): ?Order
    {
        if (filled($row['order_code'] ?? null)) {
            $byCode = $this->resolveOrder((string) $row['order_code'], 'MHT', false);
            if ($byCode) {
                return $byCode;
            }
        }
        if (filled($row['tracking_number'] ?? null)) {
            return $this->resolveOrder((string) $row['tracking_number'], 'MGV', $isGhtk);
        }

        return null;
    }

    private function normalizeStatus(?string $raw): ?string
    {
        if (! filled($raw)) {
            return null;
        }
        $value = trim((string) $raw);
        if (ReconciliationStatus::tryFrom($value)) {
            return $value;
        }

        $aliases = [
            'đã đối soát' => ReconciliationStatus::Reconciled->value,
            'da doi soat' => ReconciliationStatus::Reconciled->value,
            'doi soat' => ReconciliationStatus::Reconciled->value,
            'đối soát' => ReconciliationStatus::Reconciled->value,
            'settled' => ReconciliationStatus::Reconciled->value,
            'chưa đối soát' => ReconciliationStatus::Pending->value,
            'chua doi soat' => ReconciliationStatus::Pending->value,
        ];
        $lower = Str::lower($value);
        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }

        $matched = collect(ReconciliationStatus::cases())->first(
            fn (ReconciliationStatus $status) => Str::lower($status->label()) === $lower
                || Str::lower($status->value) === $lower
        );

        return $matched?->value;
    }

    /** @return array<string,mixed> */
    private function presentBatch(ReconciliationImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'filename' => $batch->filename,
            'is_ghtk' => (bool) $batch->is_ghtk,
            'state' => $batch->state,
            'uploaded_at' => $batch->uploaded_at?->format('d/m/Y H:i'),
            'applied_at' => $batch->applied_at?->format('d/m/Y H:i'),
        ];
    }
}
