<?php

namespace App\Http\Controllers\Admin\Pushsale;

use App\Http\Controllers\Controller;
use App\Services\NavigationService;
use App\Services\Pushsale\PageResourceManager;
use App\Services\Pushsale\PushsalePageService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

abstract class BasePushsalePageController extends Controller
{
    protected string $pageCode;

    /**
     * Trang import/upload không cần rows + filterOptions nặng (1000 leads / 2000 SP).
     * Override = true ở LeadImport* để GET < 2s.
     */
    protected bool $lightweightIndex = false;

    public function __construct(
        protected readonly PushsalePageService $pages,
        protected readonly PageResourceManager $resources,
        protected readonly NavigationService $navigation,
    ) {}

    public function index(Request $request): Response|StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorizePage($request);
        $schema = $this->pages->schema($this->pageCode);
        $templateCode = (string) ($schema['template_alias'] ?? $this->pageCode);
        // `component` trong config/pushsale_pages.php là đường dẫn Inertia đầy đủ
        // theo domain, ví dụ 'Admin/Hr/WorkShifts'.
        $component = (string) ($schema['component'] ?? '');
        $dialogResources = [];
        $result = [
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0, 'from' => 0, 'to' => 0],
            'summary' => [],
        ];
        $filterOptions = [];
        $pageRuntimeError = null;

        if ($this->lightweightIndex) {
            return Inertia::render($component, [
                'schema' => array_merge($schema, [
                    'form_fields' => [],
                    'dialog_resource_schemas' => [],
                ]),
                'rows' => [],
                'pagination' => $result['meta'],
                'summary' => [],
                'filterOptions' => [],
                'routeUrl' => '/'.$request->path(),
                'templateHtml' => '',
                'dialogTemplates' => [],
                'activeMenuCode' => $this->activeMenuCodeFromRequest($request),
                'pageRuntimeError' => null,
            ]);
        }

        try {
            $result = $this->pages->rows($this->pageCode, $request);

            if ($request->boolean('export') || in_array((string) $request->query('export'), ['1', 'xls', 'excel', 'csv'], true)) {
                return $this->export($schema, $result, $request);
            }

            $this->recordFilterHistoryIfNeeded($request, $schema, $result);

            foreach ((array) ($schema['dialog_resources'] ?? []) as $dialogCode => $resourceKey) {
                $alias = $this->dialogAlias((string) $dialogCode);
                $dialogResources[$dialogCode] = [
                    'alias' => $alias,
                    'resource_key' => $resourceKey,
                    'fields' => $this->resources->formFields((string) $resourceKey),
                    'records' => $this->resources->records((string) $resourceKey),
                    'store_url' => url($request->path().'/dialogs/'.$alias.'/records'),
                ];
            }
        } catch (Throwable $exception) {
            $isExport = $request->boolean('export')
                || in_array((string) $request->query('export'), ['1', 'xls', 'excel', 'csv'], true);
            if ($isExport) {
                throw $exception;
            }

            report($exception);
            $pageRuntimeError = (bool) config('app.debug')
                ? $exception->getMessage()
                : 'Trang này đang thiếu dữ liệu hoặc cấu hình bảng. Vui lòng chạy migrate/cache clear rồi thử lại.';
        }

        if ($pageRuntimeError === null) {
            try {
                $filterOptions = $this->pages->filterOptions($this->pageCode);
            } catch (Throwable $exception) {
                report($exception);
                $filterOptions = [];
                if ((bool) config('app.debug')) {
                    $pageRuntimeError = 'Không tải được dữ liệu bộ lọc: '.$exception->getMessage();
                }
            }
        }

        return Inertia::render($component, [
            'schema' => array_merge($schema, [
                'form_fields' => $schema['form_fields'] ?? ($schema['resource_key'] ?? null ? $this->safeFormFields((string) $schema['resource_key']) : []),
                'dialog_resource_schemas' => $dialogResources,
            ]),
            'rows' => $result['data'],
            'pagination' => $result['meta'],
            'summary' => $result['summary'] ?? [],
            'filterOptions' => $filterOptions,
            'routeUrl' => '/'.$request->path(),
            'templateHtml' => $this->templateHtml($templateCode),
            'dialogTemplates' => collect($schema['dialogs'] ?? [])->mapWithKeys(
                fn (string $dialog): array => [$dialog => $this->templateHtml($dialog)],
            )->all(),
            'activeMenuCode' => $this->activeMenuCodeFromRequest($request),
            'pageRuntimeError' => $pageRuntimeError,
        ]);
    }


    protected function activeMenuCodeFromRequest(Request $request): string
    {
        $queryCode = trim((string) $request->query('menu_code', ''));
        if ($queryCode !== '') {
            return $queryCode;
        }

        return $this->pageCode;
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->mainResourceKey();
        abort_unless($resourceKey, 405);
        $payload = $this->payload($request);
        $record = $this->resources->create($resourceKey, $payload, $request->user());

        return $this->savedResponse($request, $record->toArray(), 201, 'Đã thêm dữ liệu.');
    }

    public function update(Request $request, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->mainResourceKey();
        abort_unless($resourceKey, 405);
        $model = $this->resources->find($resourceKey, $record);
        $model = $this->resources->update($resourceKey, $model, $this->payload($request), $request->user());

        return $this->savedResponse($request, $model->toArray(), 200, 'Đã cập nhật dữ liệu.');
    }

    public function destroy(Request $request, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->mainResourceKey();
        abort_unless($resourceKey, 405);
        $model = $this->resources->find($resourceKey, $record);
        $this->resources->delete($resourceKey, $model);

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã xóa dữ liệu.');
    }

    public function storeDialog(Request $request, string $dialog): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->dialogResourceKey($dialog);
        $record = $this->resources->create($resourceKey, $this->payload($request), $request->user());

        return $this->savedResponse($request, $record->toArray(), 201, 'Đã lưu dữ liệu dialog.');
    }

    public function updateDialog(Request $request, string $dialog, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->dialogResourceKey($dialog);
        $model = $this->resources->find($resourceKey, $record);
        $model = $this->resources->update($resourceKey, $model, $this->payload($request), $request->user());

        return $this->savedResponse($request, $model->toArray(), 200, 'Đã cập nhật dữ liệu dialog.');
    }

    public function destroyDialog(Request $request, string $dialog, int $record): RedirectResponse|JsonResponse
    {
        $this->authorizePage($request);
        $resourceKey = $this->dialogResourceKey($dialog);
        $this->resources->delete($resourceKey, $this->resources->find($resourceKey, $record));

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã xóa dữ liệu dialog.');
    }

    /** @return array<int, array<string, mixed>> */
    protected function safeFormFields(string $resourceKey): array
    {
        try {
            return $this->resources->formFields($resourceKey);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    protected function mainResourceKey(): ?string
    {
        $schema = $this->pages->schema($this->pageCode);
        $resourceKey = $schema['resource_key'] ?? null;

        return is_string($resourceKey) && $this->resources->isEditable($resourceKey) ? $resourceKey : null;
    }

    protected function dialogResourceKey(string $alias): string
    {
        $schema = $this->pages->schema($this->pageCode);
        foreach ((array) ($schema['dialog_resources'] ?? []) as $dialogCode => $resourceKey) {
            if ($this->dialogAlias((string) $dialogCode) === $alias) {
                abort_unless($this->resources->isEditable((string) $resourceKey), 405);

                return (string) $resourceKey;
            }
        }

        abort(404);
    }

    protected function dialogAlias(string $dialogCode): string
    {
        if (str_contains($dialogCode, 'create')) return 'create';
        if (str_contains($dialogCode, 'ph#U00e2n')) return 'category';
        if (str_contains($dialogCode, 'gi#U00e1')) return 'attribute-value';
        if (str_contains($dialogCode, 'thu#U1ed9c')) return 'attribute';

        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $dialogCode)), '-');
    }

    /** @return array<string, mixed> */
    protected function payload(Request $request): array
    {
        return $request->validate([
            'payload' => ['required', 'array'],
            'payload.*' => ['nullable'],
        ])['payload'];
    }

    protected function authorizePage(Request $request): void
    {
        try {
            $tree = $this->navigation->forUser($request->user());
        } catch (Throwable $exception) {
            report($exception);
            abort(403, 'Không tải được cây menu phân quyền.');
        }

        abort_unless($this->treeContainsCode($tree, $this->pageCode), 403);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function treeContainsCode(array $items, string $pageCode): bool
    {
        foreach ($items as $item) {
            if ((string) ($item['code'] ?? '') === $pageCode) return true;
            if ($this->treeContainsCode((array) ($item['children'] ?? []), $pageCode)) return true;
        }

        return false;
    }

    private function templateHtml(string $templateCode): string
    {
        $path = public_path('pushsale-templates/'.$templateCode.'.html');

        return File::exists($path) ? File::get($path) : '';
    }

    /** @param array<string, mixed> $record */
    private function savedResponse(Request $request, array $record, int $status, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message, 'record' => $record], $status)
            : back()->with('success', $message);
    }

    /** @param array<string, mixed> $schema @param array{data?: array<int, array<string, mixed>>, meta?: array<string, mixed>, summary?: array<string, mixed>} $result */
    private function recordFilterHistoryIfNeeded(Request $request, array $schema, array $result): void
    {
        if (Str::startsWith($this->pageCode, '1.7.')) {
            return;
        }

        $filters = $this->meaningfulFilterPayload($request);
        if ($filters === []) {
            return;
        }

        try {
            $pageTitle = (string) ($schema['title'] ?? 'Trang '.$this->pageCode);
            $filterLabel = $this->buildFilterLabel($pageTitle, $filters);

            ActivityLogger::log(ActivityLogger::DATA_FILTER_SEARCHED, null, [
                'page_code' => $this->pageCode,
                'page_title' => $pageTitle,
                'filters' => $filters,
                'filter_label' => $filterLabel,
                'date_type' => $filters['date_type'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'closed_status' => $filters['closed_status'] ?? null,
                'closing_status' => $this->labelForFilterValue('closed_status', $filters['closed_status'] ?? null),
                'closing_status_label' => $this->labelForFilterValue('closed_status', $filters['closed_status'] ?? null),
                'delivery_status' => $filters['delivery_status'] ?? null,
                'delivery_status_label' => $this->labelForFilterValue('delivery_status', $filters['delivery_status'] ?? null),
                'result_count' => Arr::get($result, 'meta.total'),
                'actor_name' => $request->user()?->name,
            ], $filterLabel, $request->user());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array<string, string> */
    private function meaningfulFilterPayload(Request $request): array
    {
        $ignored = [
            'page', 'per_page', 'export', '_token', '_method', 'sort',
        ];
        $allowed = [
            'search', 'date_from', 'date_to', 'date_type', 'closed_status', 'delivery_status',
            'operation_result', 'operation_state', 'operation_stage', 'customer_type',
            'sale_leader_id', 'sale_team_id', 'sale_id', 'marketer_leader_id', 'marketer_team_id',
            'marketer_id', 'product_id', 'source_id', 'warehouse_id', 'allocation_status',
            'shipping_method', 'internal_reconciliation_status', 'duplicate_status',
            'care_operation_status', 'care_user_id', 'warehouse_user_id', 'status',
        ];

        $filters = [];
        foreach ($request->query() as $key => $value) {
            $key = (string) $key;
            if (in_array($key, $ignored, true) || ! in_array($key, $allowed, true)) {
                continue;
            }

            $value = is_array($value) ? implode(',', array_filter(array_map('strval', $value))) : trim((string) $value);
            if ($value === '' || in_array($value, ['-1', 'all'], true)) {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    /** @param array<string, string> $filters */
    private function buildFilterLabel(string $pageTitle, array $filters): string
    {
        $labels = [];
        foreach ($filters as $key => $value) {
            $labels[] = $this->filterKeyLabel($key).': '.$this->labelForFilterValue($key, $value);
        }

        return $pageTitle.($labels !== [] ? ' · '.implode(' · ', array_slice($labels, 0, 4)) : '');
    }

    private function filterKeyLabel(string $key): string
    {
        return match ($key) {
            'search' => 'Từ khóa',
            'date_from' => 'Từ ngày',
            'date_to' => 'Đến ngày',
            'date_type' => 'Kiểu ngày',
            'closed_status' => 'Trạng thái chốt đơn',
            'delivery_status' => 'Trạng thái giao hàng',
            'operation_result' => 'Kết quả tác nghiệp',
            'operation_state' => 'Trạng thái tác nghiệp',
            'operation_stage' => 'Lần tác nghiệp',
            'customer_type' => 'Loại khách',
            'sale_leader_id' => 'Trưởng nhóm sale',
            'sale_team_id' => 'Nhóm sale',
            'sale_id' => 'Sale',
            'marketer_leader_id' => 'Trưởng nhóm marketing',
            'marketer_team_id' => 'Nhóm marketing',
            'marketer_id' => 'Marketing',
            'product_id' => 'Sản phẩm',
            'source_id' => 'Nguồn data',
            'warehouse_id' => 'Kho',
            'allocation_status' => 'Phân bổ',
            'shipping_method' => 'Vận chuyển',
            'internal_reconciliation_status' => 'Đối soát',
            'duplicate_status' => 'Trùng số',
            'care_operation_status' => 'Care đơn',
            'care_user_id' => 'Nhân sự care',
            'warehouse_user_id' => 'Nhân sự kho',
            default => Str::headline(str_replace('_', ' ', $key)),
        };
    }

    private function labelForFilterValue(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        if (in_array($key, ['date_from', 'date_to'], true)) {
            try {
                return CarbonImmutable::parse($value)->format('d/m/Y');
            } catch (Throwable) {
                return $value;
            }
        }

        if ($key === 'closed_status') {
            return match ($value) {
                '1', 'closed', 'Đã chốt đơn' => 'Đã chốt đơn',
                '0', 'pending', 'Chưa chốt đơn' => 'Chưa chốt đơn',
                default => $value,
            };
        }

        if ($key === 'delivery_status') {
            return match ($value) {
                '1' => 'Chờ vận đơn',
                '2' => 'Giao ngay',
                '3' => 'Hoãn giao hàng',
                '4' => 'Hủy vận đơn',
                '5' => 'Hủy đăng đơn',
                '20' => 'Đã đăng',
                '21', '23' => 'Đang lấy hàng',
                '22' => 'Không lấy được hàng',
                '30' => 'Đang giao hàng',
                '31' => 'Đã giao hàng',
                '32' => 'Đã thanh toán',
                '33' => 'Không giao được',
                '34' => 'Yêu cầu giao lại',
                '35' => 'Giao hàng một phần',
                '40' => 'Đang hoàn',
                '41' => 'Đã hoàn',
                '50' => 'Bồi hoàn',
                default => $value,
            };
        }

        if ($key === 'date_type') {
            return match ($value) {
                'SaleNgayNhanData' => 'Ngày sale nhận data',
                'NgayTao' => 'Ngày data về hệ thống',
                'SaleTacNghiepNgayCapNhat' => 'Ngày sale tác nghiệp',
                'DonHangNgayChot' => 'Ngày sale chốt đơn',
                'NgayDangDon' => 'Ngày đăng đơn',
                'NgayChoXuat' => 'Ngày sale tác nghiệp tiếp',
                'NgayCapNhatTrangThaiGiaoHang' => 'Ngày cập nhật trạng thái giao hàng',
                'DoiSoatNoiBoNgayCapNhat' => 'Ngày đối soát',
                'CareDonNgayNhan' => 'Ngày nhận care đơn',
                'NgayTacNghiepCareDon' => 'Ngày cập nhật care đơn',
                default => $value,
            };
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array{data?: list<array<string, mixed>>, summary?: array<string, mixed>}  $result
     */
    private function export(array $schema, array $result, Request $request): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $rows = array_values($result['data'] ?? []);
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : null;
        if ($totals) {
            $totals['_is_total'] = true;
            if (! isset($totals['index'])) {
                $totals['index'] = '';
            }
            if (! isset($totals['sale']) && ! isset($totals['sale_name'])) {
                $totals['sale'] = 'Tổng';
            }
            array_unshift($rows, $totals);
        }

        $columns = $this->resolveExportColumns($schema, $summary);
        $pageCode = (string) ($schema['code'] ?? $this->pageCode);
        $layout = str_starts_with($pageCode, '4.6.')
            ? \App\Support\SalesLeaderReportExcelLayout::forPage($pageCode, $columns, $summary)
            : ['columns' => $columns, 'header_rows' => null];
        $columns = $layout['columns'];
        $rows = $this->formatExportRows($rows, $columns);

        $basename = \App\Support\ReportExportIdentity::basename(
            str_replace('.', '-', $pageCode),
            now()->format('Ymd-His'),
        );
        $export = strtolower((string) $request->query('export', 'excel'));

        if ($export === 'csv') {
            return \App\Support\ReportCsvExporter::download($basename.'.csv', $rows, $columns);
        }

        // export=1 / excel / xls → Excel HTML khớp layout bảng trên UI.
        return \App\Support\ReportExcelExporter::download($basename, $rows, $columns, [
            'brand' => \App\Support\ReportExportIdentity::brand(),
            'title' => (string) ($schema['title'] ?? $this->pageCode),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'generated_at' => now()->format('Y-m-d H:i'),
            'period_label' => 'Kỳ báo cáo',
            'generated_label' => 'Xuất lúc',
            'header_rows' => $layout['header_rows'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $summary
     * @return list<array{key: string, label: string, format?: string}>
     */
    private function resolveExportColumns(array $schema, array $summary): array
    {
        $display = array_values(array_filter(
            (array) ($schema['display_columns'] ?? []),
            fn ($column) => is_array($column) && filled($column['key'] ?? null),
        ));
        $legacy = array_values(array_filter(
            (array) ($schema['columns'] ?? []),
            fn ($column) => is_array($column) && filled($column['key'] ?? null),
        ));

        $source = $display !== [] ? $display : $legacy;
        $stageLabels = [];
        foreach ((array) ($summary['stages'] ?? []) as $stage) {
            if (is_array($stage) && filled($stage['key'] ?? null)) {
                $stageLabels[(string) $stage['key']] = (string) ($stage['label'] ?? $stage['key']);
            }
        }

        $columns = [];
        foreach ($source as $column) {
            $key = (string) $column['key'];
            // Bỏ cột nhóm (call_1) — chỉ xuất cột lá khớp UI (call_1_contacts…).
            if ($display === [] && isset($stageLabels[$key])) {
                continue;
            }

            $columns[] = [
                'key' => $key,
                'label' => filled($column['label'] ?? null)
                    ? (string) $column['label']
                    : $this->exportColumnLabel($key, $stageLabels),
                'format' => (string) ($column['format'] ?? 'text'),
            ];
        }

        return $columns !== [] ? $columns : [
            ['key' => 'index', 'label' => 'STT', 'format' => 'text'],
            ['key' => 'sale', 'label' => 'SALE', 'format' => 'text'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @return list<array<string, mixed>>
     */
    private function formatExportRows(array $rows, array $columns): array
    {
        return array_map(function (array $row) use ($columns): array {
            foreach ($columns as $column) {
                $key = (string) $column['key'];
                $value = $row[$key] ?? null;
                if (is_array($value) || is_object($value)) {
                    $row[$key] = '';
                    continue;
                }

                $format = (string) ($column['format'] ?? 'text');
                if ($value === null || $value === '') {
                    continue;
                }

                if ($format === 'percent' && is_numeric($value)) {
                    $numeric = (float) $value;
                    $row[$key] = (fmod($numeric, 1.0) === 0.0 ? (string) (int) $numeric : number_format($numeric, 2, '.', '')).' %';
                    continue;
                }

                if (in_array($format, ['number', 'currency'], true) && is_numeric($value)) {
                    $row[$key] = number_format((float) $value, 0, ',', '.');
                    continue;
                }

                if ($key === 'receive_data') {
                    $row[$key] = ((string) $value === '1' || $value === true || $value === 'Có') ? 'Có' : 'Không';
                }
            }

            if (($row['sale'] ?? '') === '' && ($row['sale_name'] ?? '') !== '') {
                $row['sale'] = $row['sale_name'];
            }

            return $row;
        }, $rows);
    }

    /** @param  array<string, string>  $stageLabels */
    private function exportColumnLabel(string $key, array $stageLabels): string
    {
        static $base = [
            'index' => 'STT',
            'sale' => 'SALE',
            'sale_account' => 'Tài khoản',
            'total_contacts' => 'Tổng contact',
            'total_closed' => 'Tổng chốt đơn',
            'total_rate' => 'Tổng tỷ lệ',
            'total_revenue' => 'Tổng doanh số',
            'revenue' => 'Tổng doanh số',
            'untouched' => 'Tổng contact chưa TN',
            'total_untouched' => 'Tổng contact chưa TN',
            'received' => 'Contact nhận',
            'duplicate' => 'Contact trùng',
            'unique' => 'Contact không trùng',
            'receive_data' => 'Nhận dữ liệu',
            'new_contacts' => 'KH mới - Contact',
            'new_closed' => 'KH mới - Chốt đơn',
            'new_rate' => 'KH mới - Tỷ lệ chốt',
            'new_products' => 'KH mới - Số sản phẩm',
            'new_revenue' => 'KH mới - Doanh số tạm tính',
            'old_contacts' => 'KH cũ - Contact',
            'old_closed' => 'KH cũ - Chốt đơn',
            'old_rate' => 'KH cũ - Tỷ lệ chốt',
            'old_products' => 'KH cũ - Số sản phẩm',
            'old_revenue' => 'KH cũ - Doanh số tạm tính',
            'provisional_revenue' => 'Doanh số tạm tính',
            'cod_fee' => 'Phí COD',
            'cod_support' => 'Hỗ trợ COD',
            'discount' => 'CK',
            'deposit' => 'Đặt cọc',
            'after_discount_revenue' => 'Doanh số tạm tính sau chiết khấu',
            'kpi_revenue' => 'KPI doanh số',
            'kpi_rate' => 'Tỷ lệ KPI',
            'yesterday_rate' => 'Hôm qua - % chốt đơn',
            'yesterday_revenue' => 'Hôm qua - Doanh số',
            'last_month_rate' => 'Tháng trước - % chốt đơn',
            'last_month_revenue' => 'Tháng trước - Doanh số',
            'this_month_rate' => 'Tháng này - % chốt đơn',
            'this_month_revenue' => 'Tháng này - Doanh số',
            'success_revenue' => 'Doanh số thành công',
            'contacts' => 'Contact tổng',
            'allocated_total' => 'Contact được chia - Tổng',
            'allocated_duplicate' => 'Contact được chia - Trùng',
            'allocated_unique' => 'Contact được chia - Không trùng',
            'closed_contacts' => 'Contact chốt đơn',
            'close_rate' => 'Tỷ lệ chốt đơn',
            'avg_order_value' => 'Giá trị TB đơn',
            'products_per_order' => 'Số sản phẩm/Đơn',
            'revenue_per_contact' => 'Doanh số tạm tính/Contact được chia',
            'cancelled_revenue' => 'Doanh số hủy',
            'returned_revenue' => 'Doanh số hoàn',
        ];

        if (isset($base[$key])) {
            return $base[$key];
        }

        foreach ($stageLabels as $stageKey => $stageLabel) {
            if ($key === $stageKey.'_contacts') {
                return $stageLabel.' - Số contact';
            }
            if ($key === $stageKey.'_closed') {
                return $stageLabel.' - Chốt đơn';
            }
            if ($key === $stageKey.'_rate') {
                return $stageLabel.' - Tỷ lệ chốt';
            }
            if ($key === $stageKey.'_revenue') {
                return $stageLabel.' - Doanh số';
            }
            if ($key === $stageKey.'_untouched') {
                return $stageLabel.' - Chưa TN';
            }
        }

        return Str::of($key)->replace('_', ' ')->title()->toString();
    }
}
