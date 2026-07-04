<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Leads\ManualLeadImportService;
use App\Support\SpreadsheetLeadReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManualLeadController extends Controller
{
    public function __construct(
        private readonly ManualLeadImportService $importer,
    ) {}

    /** Tạo 1 lead lẻ nhập tay. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'unit_price' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
        ] + $this->allocationRules($request));

        $sales = $this->resolveSales($request);
        $forceSale = $sales->first();

        $lead = $this->importer->createSingle($validated, $forceSale);

        if ($lead->status->value === 'failed') {
            return back()->with('error', $lead->error_message ?? __('messages.manual_lead.create_failed'));
        }

        if ($lead->status->value === 'duplicate') {
            return back()->with('error', $lead->error_message ?? __('messages.manual_lead.duplicate'));
        }

        return back()->with('success', __('messages.manual_lead.created'));
    }

    /** Import lead từ file CSV/Excel (.csv, .txt, .xls, .xlsx), tự khớp cột theo ý nghĩa. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ] + $this->allocationRules($request));

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, SpreadsheetLeadReader::ALLOWED, true)) {
            return back()->with('error', __('messages.manual_lead.bad_file'));
        }

        try {
            $sheets = SpreadsheetLeadReader::sheets($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('messages.manual_lead.read_failed'));
        }

        $sales = $this->resolveSales($request);

        // Chọn sheet có cột SĐT (file mẫu có thể có thêm sheet danh mục).
        $chosen = null;
        $headers = [];
        $detection = null;
        $fallbackHeaders = [];
        $fallbackDetection = null;

        foreach ($sheets as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $h = array_map('strval', $rows[0]);
            $d = $this->importer->detectMapping($h);

            if ($fallbackDetection === null) {
                $fallbackHeaders = $h;
                $fallbackDetection = $d;
            }

            if (in_array('phone', $d['map'], true)) {
                $chosen = $rows;
                $headers = $h;
                $detection = $d;
                break;
            }
        }

        if ($detection === null) {
            if ($fallbackDetection === null) {
                return back()->with('error', __('messages.manual_lead.csv_empty'));
            }

            // Có dữ liệu nhưng không tìm thấy cột SĐT ở bất kỳ sheet nào.
            return back()->with('importResult', [
                'ok' => false,
                'message' => __('messages.manual_lead.no_phone_column'),
                'mapping' => $this->mappingForView($fallbackHeaders, $fallbackDetection),
                'unmatched' => $fallbackDetection['unmatched'],
            ]);
        }

        // Giới hạn để tránh file quá lớn treo request.
        $dataRows = array_slice($chosen, 1, 5000);

        $summary = $this->importer->importRows($dataRows, $detection['map'], $sales->all());

        return back()->with('importResult', array_merge($summary, [
            'ok' => true,
            'mapping' => $this->mappingForView($headers, $detection),
            'unmatched' => $detection['unmatched'],
        ]));
    }

    /**
     * Quy tắc validate cho lựa chọn chia số (dùng chung cho form lẻ & import).
     *
     * @return array<string, mixed>
     */
    private function allocationRules(Request $request): array
    {
        return [
            'allocation_mode' => ['nullable', Rule::in(['default', 'manual'])],
            'sale_user_ids' => [
                Rule::requiredIf(fn () => $request->input('allocation_mode') === 'manual'),
                'array',
            ],
            'sale_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_SALES),
            ],
        ];
    }

    /**
     * Sale được chọn khi chia thủ công. Rỗng nếu chia theo cấu hình mặc định.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveSales(Request $request): \Illuminate\Support\Collection
    {
        if ($request->input('allocation_mode') !== 'manual') {
            return collect();
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('sale_user_ids', []))));

        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->where('role', User::ROLE_SALES)
            ->get();
    }

    /**
     * @param  list<string>  $headers
     * @param  array{map: array<int,string>, labels: array<string,string>, unmatched: list<string>}  $detection
     * @return list<array{header:string, field:string}>
     */
    private function mappingForView(array $headers, array $detection): array
    {
        $result = [];

        foreach ($detection['map'] as $index => $field) {
            $result[] = ['header' => (string) ($headers[$index] ?? ''), 'field' => $field];
        }

        return $result;
    }
}
