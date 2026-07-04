<?php

namespace App\Services\Leads;

use App\Enums\LeadIngestionStatus;
use App\Integrations\Manual\ManualLeadDriver;
use App\Models\LeadIngestion;
use App\Models\Product;
use App\Models\User;
use App\Support\MoneyParser;
use Illuminate\Support\Str;

/**
 * Nhập lead thủ công: 1 lead lẻ hoặc import CSV có TỰ ĐỘNG khớp cột theo ý nghĩa.
 *
 * Ví dụ cột "Họ và tên" / "Tên KH" / "name" đều được ánh xạ về customer_name;
 * "SĐT" / "Số điện thoại" / "phone" → customer_phone... không phân biệt hoa/thường & dấu.
 */
class ManualLeadImportService
{
    public function __construct(
        private readonly LeadIngestionService $leads,
    ) {}

    /**
     * Từ điển khớp cột: field chuẩn => các tên cột (alias) đã chuẩn hoá (bỏ dấu, bỏ ký tự đặc biệt).
     *
     * @return array<string, list<string>>
     */
    public static function columnDictionary(): array
    {
        return [
            'name' => ['name', 'hoten', 'hovaten', 'ten', 'tenkh', 'tenkhach', 'tenkhachhang', 'hotenkhachhang', 'khachhang', 'fullname', 'customername', 'hovatenkhachhang'],
            'phone' => ['phone', 'sdt', 'sodt', 'sodienthoai', 'dienthoai', 'phonenumber', 'mobile', 'tel', 'customerphone', 'dienthoaikhachhang', 'lienhe'],
            'address' => ['address', 'diachi', 'diachinhan', 'diachinhanhang', 'diachigiaohang', 'diachigiao', 'shippingaddress', 'noigiao'],
            'product' => ['product', 'sanpham', 'sp', 'tensanpham', 'mathang', 'productname', 'goisanpham', 'combo'],
            'quantity' => ['quantity', 'soluong', 'sl', 'qty', 'amount'],
            'unit_price' => ['price', 'gia', 'giaban', 'dongia', 'unitprice', 'giasanpham'],
            'discount' => ['discount', 'chietkhau', 'giamgia', 'khuyenmai'],
            'note' => ['note', 'notes', 'ghichu', 'message', 'tinnhan', 'tinnhankhachhang', 'loinhan', 'remark', 'noidung', 'tacnghiep'],
            'utm_source' => ['source', 'nguon', 'utmsource', 'kenh', 'nguonlead', 'tennguondulieu', 'nguondulieu', 'nguonlieu'],
            'utm_campaign' => ['campaign', 'chiendich', 'utmcampaign'],
        ];
    }

    /** Chuẩn hoá tên cột về dạng so khớp: bỏ dấu, thường hoá, bỏ ký tự không phải chữ/số. */
    public static function normalizeHeader(string $header): string
    {
        $ascii = Str::of($header)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return $ascii;
    }

    /**
     * Khớp danh sách cột CSV → field chuẩn. Mỗi field chỉ nhận 1 cột (cột đầu khớp thắng).
     *
     * @param  list<string>  $headers
     * @return array{map: array<int, string>, labels: array<string, string>, unmatched: list<string>}
     */
    public function detectMapping(array $headers): array
    {
        $dict = self::columnDictionary();
        $map = [];
        $labels = [];
        $taken = [];

        $norms = [];
        foreach ($headers as $index => $header) {
            $norms[$index] = self::normalizeHeader((string) $header);
        }

        // Lượt 1: khớp CHÍNH XÁC cho toàn bộ cột trước — cột đúng nghĩa luôn thắng heuristic.
        foreach ($norms as $index => $norm) {
            if ($norm === '') {
                continue;
            }
            foreach ($dict as $field => $aliases) {
                if (isset($taken[$field])) {
                    continue;
                }
                if (in_array($norm, $aliases, true)) {
                    $map[$index] = $field;
                    $labels[$field] = (string) $headers[$index];
                    $taken[$field] = true;
                    break;
                }
            }
        }

        // Lượt 2: khớp "chứa" cho các cột còn lại (vd "diachinhanhang" chứa "diachi").
        foreach ($norms as $index => $norm) {
            if ($norm === '' || isset($map[$index])) {
                continue;
            }
            // Bỏ qua các cột mang tính phân loại/tác nghiệp không phải dữ liệu lead (vd "Sale(username)").
            if (str_contains($norm, 'username') || str_contains($norm, 'saleusername')) {
                continue;
            }
            foreach ($dict as $field => $aliases) {
                if (isset($taken[$field])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if (strlen($alias) >= 3 && str_contains($norm, $alias)) {
                        $map[$index] = $field;
                        $labels[$field] = (string) $headers[$index];
                        $taken[$field] = true;
                        break 2;
                    }
                }
            }
        }

        $unmatched = [];
        foreach ($headers as $index => $header) {
            if (! isset($map[$index])) {
                $unmatched[] = (string) $header;
            }
        }

        return ['map' => $map, 'labels' => $labels, 'unmatched' => $unmatched];
    }

    /**
     * Import nhiều dòng theo mapping cột. Trả về tổng kết để hiển thị.
     *
     * @param  list<list<string>>  $rows  các dòng dữ liệu (không gồm header)
     * @param  array<int, string>  $map   chỉ số cột => field chuẩn
     * @param  list<User>  $forceSales  danh sách sale được chọn khi chia thủ công (rỗng = chia mặc định), chia lần lượt (round-robin)
     * @return array{total:int, created:int, duplicate:int, failed:int, skipped:int, errors: list<array{row:int, message:string}>}
     */
    public function importRows(array $rows, array $map, array $forceSales = []): array
    {
        $summary = ['total' => 0, 'created' => 0, 'duplicate' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];
        $rr = 0;

        foreach ($rows as $i => $row) {
            $lineNo = $i + 2; // +1 header, +1 vì index 0-based
            $data = $this->rowToData($row, $map);

            if (blank($data['phone'] ?? null) && blank($data['name'] ?? null)) {
                $summary['skipped']++;

                continue;
            }

            $summary['total']++;

            $forceSale = null;
            if ($forceSales !== []) {
                $forceSale = $forceSales[$rr % count($forceSales)];
                $rr++;
            }

            try {
                $lead = $this->ingestOne($data, $forceSale);
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = ['row' => $lineNo, 'message' => $e->getMessage()];

                continue;
            }

            match ($lead->status) {
                LeadIngestionStatus::Duplicate => $summary['duplicate']++,
                LeadIngestionStatus::Failed => $summary['failed']++,
                default => $summary['created']++,
            };

            if ($lead->status === LeadIngestionStatus::Failed) {
                $summary['errors'][] = ['row' => $lineNo, 'message' => $lead->error_message ?? 'Lỗi không xác định'];
            }
        }

        return $summary;
    }

    /**
     * Tạo 1 lead lẻ từ dữ liệu form.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSingle(array $data, ?User $forceSale = null): LeadIngestion
    {
        return $this->ingestOne($data, $forceSale);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ingestOne(array $data, ?User $forceSale = null): LeadIngestion
    {
        $payload = [
            'name' => $this->str($data['name'] ?? null),
            'phone' => $this->str($data['phone'] ?? null),
            'address' => $this->str($data['address'] ?? null),
            'product' => $this->str($data['product'] ?? null),
            'note' => $this->str($data['note'] ?? null),
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'discount' => (int) MoneyParser::parse($data['discount'] ?? 0),
            'utm_source' => $this->str($data['utm_source'] ?? null) ?: 'manual',
            'utm_campaign' => $this->str($data['utm_campaign'] ?? null),
            'source_label' => 'Nhập tay',
        ];

        $items = $this->resolveItems(
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            productName: $payload['product'],
            price: (int) MoneyParser::parse($data['unit_price'] ?? 0),
            quantity: $payload['quantity'],
        );

        if ($items !== []) {
            $payload['items'] = $items;
        }

        return $this->leads->ingestManual(new ManualLeadDriver, $payload, $forceSale);
    }

    /**
     * Dựng dòng hàng cho lead: ưu tiên sản phẩm trong danh mục (khớp id hoặc tên).
     *
     * @return list<array<string, mixed>>
     */
    protected function resolveItems(?int $productId, ?string $productName, int $price, int $quantity): array
    {
        $product = null;

        if ($productId) {
            $product = Product::query()->find($productId);
        } elseif (filled($productName)) {
            $product = Product::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($productName))])
                ->first();
        }

        if (! $product && blank($productName)) {
            return [];
        }

        $name = $product?->name ?? trim((string) $productName);
        $unitPrice = $product ? (int) $product->unit_price : $price;

        return [[
            'product_id' => $product?->id,
            'product_name' => $name,
            'name' => $name,
            'unit_price' => max(0, $unitPrice),
            'quantity' => max(1, $quantity),
            'item_type' => $product?->type ?? 'product',
            'origin' => 'manual',
        ]];
    }

    /**
     * @param  list<string>  $row
     * @param  array<int, string>  $map
     * @return array<string, mixed>
     */
    protected function rowToData(array $row, array $map): array
    {
        $data = [];

        foreach ($map as $index => $field) {
            $data[$field] = $row[$index] ?? null;
        }

        return $data;
    }

    protected function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
