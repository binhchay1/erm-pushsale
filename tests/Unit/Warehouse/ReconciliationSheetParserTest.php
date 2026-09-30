<?php

namespace Tests\Unit\Warehouse;

use App\Services\Warehouse\ReconciliationSheetParser;
use PHPUnit\Framework\TestCase;

class ReconciliationSheetParserTest extends TestCase
{
    private function parser(): ReconciliationSheetParser
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3).'/config/reconciliation_columns.php';

        return new ReconciliationSheetParser($config);
    }

    public function test_netship_banner_is_skipped_and_cod_goc_wins_over_cod_sau(): void
    {
        $matrix = [
            ['Đối soát', 'AUTO-2026-09-30'],
            ['Khách hàng', 'VIỆT THÀNH SHOP'],
            ['Tiền COD', '239000'],
            ['STT', 'Mã đơn', 'Người nhận', 'Trạng thái', 'Giá trị đơn hàng', 'COD Gốc', 'COD Sau', 'Phí Ship', 'Tổng đối soát'],
            ['1', '151568815318', 'A', 'Hoàn hàng thành công', '12000', '209000', '0', '16000', '-16000'],
            ['2', '152508864673', 'B', 'Giao hàng thành công', '12000', '30000', '30000', '16000', '14000'],
        ];

        $rows = $this->parser()->parse($matrix);

        $this->assertCount(2, $rows);
        $this->assertSame('151568815318', $rows[0]['order_code']);
        $this->assertNull($rows[0]['tracking_number']);
        $this->assertSame('Hoàn hàng thành công', $rows[0]['reconciliation_status']);
        $this->assertSame(209000.0, $rows[0]['excel_cod']);
        $this->assertNull($rows[0]['excel_total']);
        $this->assertSame('reconciled', $this->parser()->normalizeStatus($rows[0]['reconciliation_status']));
        $this->assertSame('reconciled', $this->parser()->normalizeStatus($rows[1]['reconciliation_status']));
        $this->assertNull($this->parser()->normalizeStatus('Đang giao hàng'));
    }

    public function test_internal_template_keeps_order_code_and_cod_columns(): void
    {
        $rows = $this->parser()->parse([
            ['Mã đơn', 'Mã giao vận', 'Trạng thái đối soát', 'Tổng tiền', 'Tiền thu hộ', 'Ghi chú'],
            ['PS00000000001PS', 'TRACK001', 'reconciled', '100000', '90000', 'OK'],
        ]);

        $this->assertSame('PS00000000001PS', $rows[0]['order_code']);
        $this->assertSame('TRACK001', $rows[0]['tracking_number']);
        $this->assertSame('reconciled', $rows[0]['reconciliation_status']);
        $this->assertSame(100000.0, $rows[0]['excel_total']);
        $this->assertSame(90000.0, $rows[0]['excel_cod']);
        $this->assertSame('OK', $rows[0]['note']);
    }

    public function test_file_without_a_header_keeps_fixed_columns(): void
    {
        $rows = $this->parser()->parse([
            ['PS00000000001PS', 'TRACK001', 'pending', '100000', '100000', 'note'],
        ]);

        $this->assertSame('PS00000000001PS', $rows[0]['order_code']);
        $this->assertSame('TRACK001', $rows[0]['tracking_number']);
        $this->assertSame('pending', $rows[0]['reconciliation_status']);
    }
}
