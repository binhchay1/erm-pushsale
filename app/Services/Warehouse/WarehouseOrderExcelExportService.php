<?php

namespace App\Services\Warehouse;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Operations\WarehouseOperationService;
use App\Services\Settings\FeatureSettingsService;
use App\Support\ReportExcelExporter;
use App\Support\ShippingProviders;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WarehouseOrderExcelExportService
{
    public function __construct(
        private readonly FeatureSettingsService $featureSettings,
        private readonly WarehouseOperationService $warehouseOperations,
    ) {}

    /** @return list<array{key:string,title:string,tone:string}> */
    public function fabButtons(): array
    {
        return collect(config('warehouse_excel_export.profiles', []))
            ->map(fn (array $profile) => [
                'key' => $profile['key'],
                'title' => $profile['title'],
                'tone' => $profile['tone'] ?? 'primary',
                'icon' => 'file-excel-o',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    public function export(string $type, array $ids, ReportFilterData $filter, ?User $actor): Response
    {
        $profile = config("warehouse_excel_export.profiles.{$type}");
        if (! is_array($profile)) {
            throw ValidationException::withMessages(['type' => 'Kiểu xuất Excel không hợp lệ.']);
        }

        $this->assertCanExport($actor);
        $this->assertNotThrottled($actor);
        $this->hitThrottle($actor);

        $columns = $this->resolveColumns();
        $orders = $this->resolveOrders($ids, $filter, $actor);
        if ($orders->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'Không có đơn để xuất theo lựa chọn / bộ lọc hiện tại.']);
        }

        $rows = $this->buildRows($orders, $columns);
        $maxRows = (int) config('warehouse_excel_export.max_rows', 5000);
        if (count($rows) > $maxRows) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        $appSlug = Str::slug((string) config('app.name'), '_');
        if ($appSlug === '') {
            $appSlug = 'export';
        }

        $filename = sprintf(
            '%s.%s.%s',
            $profile['filename_prefix'] ?? $appSlug,
            now()->format('d_m_H_i_s'),
            $type
        );

        $excelColumns = collect($columns)->map(fn (array $col) => [
            'key' => $col['key'],
            'label' => $col['label'],
        ])->all();

        $assocRows = array_map(function (array $row) use ($columns): array {
            $assoc = [];
            foreach ($columns as $index => $col) {
                $assoc[$col['key']] = $row[$index] ?? '';
            }

            return $assoc;
        }, $rows);

        return ReportExcelExporter::download($filename, $assocRows, $excelColumns, [
            'title' => $profile['title'],
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'brand' => (string) config('app.name'),
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Order>
     */
    public function resolveOrders(array $ids, ReportFilterData $filter, ?User $actor): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $max = (int) config('warehouse_excel_export.max_rows', 5000);

        if ($ids !== []) {
            return Order::query()
                ->with($this->eagerLoads())
                ->when($actor?->company_id, fn ($q, $companyId) => $q->where('company_id', $companyId))
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->limit(min(count($ids), $max))
                ->get();
        }

        return $this->warehouseOperations->filteredOrdersQuery($filter)
            ->with($this->eagerLoads())
            ->orderByDesc('data_arrived_at')
            ->orderByDesc('id')
            ->limit($max)
            ->get();
    }

    /** @return list<string> */
    private function eagerLoads(): array
    {
        return [
            'items.product',
            'warehouse.manager',
            'saleUser',
            'marketerUser',
            'warehouseCareUser',
            'marketingSource',
            'landingConnection',
            'landingConnectionSource',
            'shipments' => fn ($q) => $q->latest('id'),
            'internalMessages' => fn ($q) => $q->latest('id')->limit(1),
        ];
    }

    /** @return list<array{key:string,label:string}> */
    private function resolveColumns(): array
    {
        $settingKey = (string) config('warehouse_excel_export.columns_setting', 'SettingExcelAccounting');
        $control = collect($this->featureSettings->controls())->firstWhere('key', $settingKey) ?? [];
        $options = collect($control['options'] ?? [])->keyBy(fn ($opt) => (string) ($opt['value'] ?? ''));
        $selected = $this->featureSettings->value($settingKey, $control['default'] ?? []);
        if (! is_array($selected) || $selected === []) {
            $selected = $control['default'] ?? [];
        }

        return collect($selected)
            ->map(fn ($key) => (string) $key)
            ->filter()
            ->unique()
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => (string) ($options[$key]['label'] ?? $key),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  list<array{key:string,label:string}>  $columns
     * @return list<list<mixed>>
     */
    private function buildRows(Collection $orders, array $columns): array
    {
        $keys = array_column($columns, 'key');
        $expandLines = collect($keys)->intersect(config('warehouse_excel_export.line_item_columns', []))->isNotEmpty();
        $rows = [];
        $stt = 0;

        foreach ($orders as $order) {
            $items = $expandLines
                ? ($order->items->isNotEmpty() ? $order->items : collect([null]))
                : collect([null]);

            foreach ($items as $item) {
                $stt++;
                $row = [];
                foreach ($keys as $key) {
                    $row[] = $this->cellValue($key, $order, $item instanceof OrderItem ? $item : null, $stt);
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function cellValue(string $key, Order $order, ?OrderItem $item, int $stt): mixed
    {
        $shipment = $order->shipments->first();
        $geo = is_array($order->shipping_geo) ? $order->shipping_geo : [];
        $provider = (string) ($order->shipping_provider ?: '');
        $providerLabel = $provider !== ''
            ? (string) (config("shipping_partners.providers.{$provider}.label")
                ?: (ShippingProviders::label($provider) ?: $provider))
            : (string) ($order->shipping_method ?: 'Thủ công');
        $status = DeliveryStatus::tryFrom((string) $order->delivery_status);
        $cod = (int) ($order->amount_to_collect ?: max(0, (int) $order->total - (int) $order->deposit));
        $lineQty = (int) ($item?->quantity ?? $order->items->sum('quantity'));
        $linePrice = (int) ($item?->unit_price ?? 0);
        $lineName = (string) ($item?->product_name ?? '');
        $lineSku = (string) ($item?->product?->sku ?? '');
        $lineAmount = $item ? ((int) $item->unit_price * (int) $item->quantity) : (int) $order->subtotal;
        $lineDiscount = (int) ($item?->discount_amount ?? 0);

        return match ($key) {
            'STT' => $stt,
            'Id' => $order->id,
            'MaDon' => $order->order_code,
            'NgayTao_Text' => $order->data_arrived_at?->format('d/m/Y H:i:s') ?? '',
            'TenLanding' => $order->marketingSource?->name ?: $order->landingConnection?->name ?: '',
            'LandingUrl' => $order->landingConnectionSource?->source_url
                ?: $order->landingConnection?->success_url
                ?: '',
            'TenKenhQuangCao' => (string) ($order->utm_channel ?? ''),
            'MarketingDisplayName' => $order->marketerUser?->name ?? '',
            'MarketingUsername' => $order->marketerUser?->email ?? '',
            'MarketingMaNV' => '',
            'UTMAgent' => (string) ($order->utm_agent ?? ''),
            'UTMCampaign' => (string) ($order->utm_campaign ?? ''),
            'UTMContent' => (string) ($order->utm_content ?? ''),
            'UTMChannel' => (string) ($order->utm_channel ?? ''),
            'UTMMedium' => (string) ($order->utm_medium ?? ''),
            'UTMSource' => (string) ($order->utm_source ?? ''),
            'UTMTerm' => (string) ($order->utm_term ?? ''),
            'KhachHangName' => $order->customer_name,
            'KhachHangPhone' => $order->customer_phone,
            'KhachHangMessage' => $order->customer_note,
            'SaleDisplayName' => $order->saleUser?->name ?? '',
            'SaleUsername' => $order->saleUser?->email ?? '',
            'SaleMaNV' => '',
            'SaleTacNghiepCanTen' => $order->operation_stage,
            'SaleTacNghiepKetQuaTen' => $order->operation_result,
            'SaleTacNghiepNgayCapNhat_Text' => $order->operation_updated_at?->format('d/m/Y H:i:s') ?? '',
            'SaleNgayNhanData_Text' => $order->assigned_at?->format('d/m/Y H:i:s')
                ?? $order->data_arrived_at?->format('d/m/Y H:i:s')
                ?? '',
            'DonHangNgayChot_Text' => $order->closed_at?->format('d/m/Y H:i:s') ?? '',
            'SaleIdTrangThaiDon' => $order->closed_at ? 'Đã chốt' : 'Chưa chốt',
            'SaleTacNghiepGhiChu' => $order->sale_operation_note ?? '',
            'TenKho' => $order->warehouse?->name ?? '',
            'QuanKhoUsername' => $order->warehouse?->manager?->email ?? '',
            'DonHangTenSanPham' => $lineName,
            'DonHangTenSanPham_SoLuong' => $lineName !== '' ? "{$lineName} x{$lineQty}" : '',
            'DonHangMaSanPham' => $lineSku,
            'DonHangMaSanPham_SoLuong' => $lineSku !== '' ? "{$lineSku} x{$lineQty}" : '',
            'DonHangDonGia' => $linePrice,
            'DonHangSoLuong' => $lineQty,
            'DonHangTongSoLuong' => (int) $order->items->sum('quantity'),
            'DonHangCanNang' => (float) ($item?->product?->weight ?? 0),
            'DonHangTongCanNang' => (float) $order->items->sum(fn ($row) => ((float) ($row->product?->weight ?? 0)) * (int) $row->quantity),
            'DonHangThanhTien' => $lineAmount,
            'DonHangTongThanhTien' => (int) $order->subtotal,
            'DonHangChietKhauSanPham' => $lineDiscount,
            'DonHangChietKhauTheoDon' => (int) $order->discount,
            'DonHangChietKhau' => (int) $order->discount + $lineDiscount,
            'DonHangGiaCOD' => $cod,
            'DonHangTongTien' => (int) $order->total,
            'DonHangDatCoc' => (int) $order->deposit,
            'DonHangKhachCanThanhToan' => $cod,
            'GiaoHangCOD' => (int) $order->cod_fee,
            'DonHangHoTroCOD' => (int) $order->shipping_support_fee,
            'GiaoHangHoTen' => $order->receiver_name ?: $order->customer_name,
            'GiaoHangSoDienThoai' => $order->receiver_phone ?: $order->customer_phone,
            'GiaoHangGhiChu' => $order->shipping_notes ?: $order->customer_note,
            'GiaoHangDiaChi' => $order->effectiveShippingAddress(),
            'GiaoHangTenTinh' => (string) ($geo['province'] ?? ''),
            'GiaoHangTenHuyen' => (string) ($geo['district'] ?? ''),
            'GiaoHangTenXa' => (string) ($geo['ward'] ?? ''),
            'GiaoHangDiaChiTongHop' => trim(implode(', ', array_filter([
                $order->effectiveShippingAddress(),
                $geo['ward'] ?? null,
                $geo['district'] ?? null,
                $geo['province'] ?? null,
            ]))),
            'MaDonGiaoVan' => $shipment?->tracking_number ?: $order->tracking_number,
            'TenPhuongThucGiaoHang' => $providerLabel,
            'GiaoHangTransport' => (string) ($order->shipping_method ?: ($geo['service'] ?? $geo['service_code'] ?? '')),
            'TenTrangThaiGiaoHang' => $status?->label() ?? (string) $order->delivery_status,
            'LastMessage' => $order->internalMessages->first()?->message ?? $order->internal_recon_note,
            'NgayTacNghiepCareDon_Text' => $order->warehouse_care_updated_at?->format('d/m/Y H:i:s') ?? '',
            'CareDonUsername' => $order->warehouseCareUser?->name ?? $order->warehouseCareUser?->email ?? '',
            'NgayCapNhatTrangThaiGiaoHang_Text' => $order->last_delivery_event_at?->format('d/m/Y H:i:s') ?? '',
            'NguoiCapNhatTrangThaiGiaoHang' => '',
            'GhiChuKeToan' => $order->accounting_notes,
            'IsDoiSoatNoiBo_Text' => $order->reconciliation_status,
            'DoiSoatNoiBoNgayCapNhat_Text' => $order->settlement_matched_at?->format('d/m/Y H:i:s') ?? '',
            'NgayDangDon_Text' => $shipment?->created_at?->format('d/m/Y H:i:s') ?? '',
            'SignPartCod' => (int) ($order->settled_cod_amount ?: 0),
            'DonHangTenCombo' => $item && $item->item_type === 'combo' ? $lineName : '',
            'DonHangMaCombo' => $item && $item->item_type === 'combo' ? $lineSku : '',
            default => '',
        };
    }

    private function assertCanExport(?User $actor): void
    {
        if (! $actor) {
            throw ValidationException::withMessages(['export' => 'Bạn cần đăng nhập để xuất Excel.']);
        }

        if ($actor->role === User::ROLE_ADMIN) {
            return;
        }

        $settingKey = (string) config('warehouse_excel_export.permission_setting', 'SettingExcelPermission');
        $raw = $this->featureSettings->value($settingKey, []);
        $parts = is_array($raw) ? $raw : (preg_split('/[;,]+/', (string) $raw) ?: []);
        $allowed = collect($parts)
            ->map(fn ($item) => mb_strtolower(trim((string) $item)))
            ->filter()
            ->values();

        if ($allowed->isEmpty()) {
            return;
        }

        // Default Pushsale demo (vd. ttgroup2.admin) không dùng để khóa user ERM.
        $onlyDemoAccounts = $allowed->every(fn (string $item) => (bool) preg_match('/^[a-z0-9._-]+\.admin$/i', $item));
        if ($onlyDemoAccounts) {
            return;
        }

        $candidates = collect([
            $actor->email,
            $actor->name,
            strstr((string) $actor->email, '@', true) ?: null,
        ])->filter()->map(fn ($v) => mb_strtolower((string) $v));

        if ($candidates->intersect($allowed)->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'Tài khoản chưa được phân quyền xuất Excel (cài đặt hệ thống).']);
        }
    }

    private function assertNotThrottled(?User $actor): void
    {
        $key = $this->throttleKey($actor);
        $limit = max(1, (int) config('warehouse_excel_export.throttle_per_minute', 3));
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'export' => "Bạn xuất Excel quá nhanh. Thử lại sau {$seconds} giây để tránh quá tải máy chủ.",
            ]);
        }
    }

    private function hitThrottle(?User $actor): void
    {
        RateLimiter::hit($this->throttleKey($actor), 60);
    }

    private function throttleKey(?User $actor): string
    {
        return 'warehouse-excel-export|'.($actor?->id ?: ('guest:'.(string) request()->ip()));
    }
}
