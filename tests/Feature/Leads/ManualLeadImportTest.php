<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadAllocationMode;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\ManualLeadImportService;
use App\Support\SpreadsheetLeadReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ManualLeadImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_manual_lead_assigns_to_forced_sale_even_in_manual_mode(): void
    {
        // Hệ thống đang ở chế độ chia tay (pool) → mặc định sẽ KHÔNG auto gán.
        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Manual);
        $sale = User::factory()->create(['role' => UserRole::Sales]);

        $lead = app(ManualLeadImportService::class)->createSingle([
            'name' => 'Chị Tay',
            'phone' => '0905123456',
            'quantity' => 1,
        ], $sale);

        // Chia thủ công → gán thẳng cho sale được chọn, tạo đơn ngay.
        $this->assertSame(LeadIngestionStatus::Processed, $lead->status);
        $order = Order::query()->where('customer_phone', '0905123456')->firstOrFail();
        $this->assertSame($sale->id, $order->sale_user_id);
    }

    public function test_single_manual_lead_without_sale_uses_system_config(): void
    {
        // Pool mode + không chọn sale → lead vào pool chờ chia (Pending, chưa có đơn).
        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Manual);
        User::factory()->create(['role' => UserRole::Sales]);

        $lead = app(ManualLeadImportService::class)->createSingle([
            'name' => 'Chị Auto',
            'phone' => '0906123456',
        ], null);

        $this->assertSame(LeadIngestionStatus::Pending, $lead->status);
        $this->assertSame(0, Order::query()->where('customer_phone', '0906123456')->count());
    }

    public function test_detects_vietnamese_columns_from_sample_template(): void
    {
        $svc = app(ManualLeadImportService::class);

        $detection = $svc->detectMapping(['STT', 'Số điện thoại', 'Tên sản phẩm', 'Tác nghiệp', 'Tên nguồn dữ liệu', 'Danh mục']);

        $this->assertSame('phone', $detection['map'][1]);
        $this->assertSame('product', $detection['map'][2]);
        $this->assertSame('note', $detection['map'][3]);
        $this->assertSame('utm_source', $detection['map'][4]);
        // STT & Danh mục không map vào field lead.
        $this->assertContains('STT', $detection['unmatched']);
        $this->assertContains('Danh mục', $detection['unmatched']);
    }

    public function test_import_rows_round_robin_across_selected_sales(): void
    {
        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Manual);
        $saleA = User::factory()->create(['role' => UserRole::Sales]);
        $saleB = User::factory()->create(['role' => UserRole::Sales]);

        $svc = app(ManualLeadImportService::class);
        $map = [0 => 'phone', 1 => 'name'];
        $rows = [
            ['0905000001', 'KH 1'],
            ['0905000002', 'KH 2'],
            ['0905000003', 'KH 3'],
        ];

        $summary = $svc->importRows($rows, $map, [$saleA, $saleB]);

        $this->assertSame(3, $summary['created']);
        $this->assertSame($saleA->id, Order::query()->where('customer_phone', '0905000001')->value('sale_user_id'));
        $this->assertSame($saleB->id, Order::query()->where('customer_phone', '0905000002')->value('sale_user_id'));
        $this->assertSame($saleA->id, Order::query()->where('customer_phone', '0905000003')->value('sale_user_id'));
    }

    public function test_reads_xlsx_file_and_selects_sheet_with_phone_column(): void
    {
        $spreadsheet = new Spreadsheet;

        // Sheet 1: bảng tham chiếu (không có cột SĐT) — phải bị bỏ qua.
        $ref = $spreadsheet->getActiveSheet();
        $ref->setTitle('Danh mục');
        $ref->fromArray([['Danh mục'], ['Sản phẩm A'], ['Sản phẩm B']], null, 'A1');

        // Sheet 2: bảng dữ liệu lead thật.
        $data = $spreadsheet->createSheet();
        $data->setTitle('data');
        $data->fromArray([
            ['STT', 'Họ tên khách hàng', 'Số điện thoại', 'Tin nhắn', 'Tên sản phẩm', 'Sale(username)', 'Tên nguồn dữ liệu'],
            ['1', 'Nguyễn Văn A', '0905111222', 'Cần tư vấn', 'Combo X', 'tt.sale01', 'Facebook'],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'lead').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);

        try {
            $sheets = SpreadsheetLeadReader::sheets($path, 'xlsx');
            $svc = app(ManualLeadImportService::class);

            $chosen = null;
            $detection = null;
            foreach ($sheets as $rows) {
                if (count($rows) < 2) {
                    continue;
                }
                $d = $svc->detectMapping(array_map('strval', $rows[0]));
                if (in_array('phone', $d['map'], true)) {
                    $chosen = $rows;
                    $detection = $d;
                    break;
                }
            }

            $this->assertNotNull($detection, 'Phải chọn được sheet có cột SĐT');
            $this->assertSame('name', $detection['map'][1]);
            $this->assertSame('phone', $detection['map'][2]);
            $this->assertSame('note', $detection['map'][3]);
            $this->assertSame('product', $detection['map'][4]);
            $this->assertSame('utm_source', $detection['map'][6]);
            $this->assertContains('Sale(username)', $detection['unmatched']);
        } finally {
            @unlink($path);
        }
    }
}
