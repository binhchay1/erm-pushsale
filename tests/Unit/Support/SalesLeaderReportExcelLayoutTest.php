<?php

namespace Tests\Unit\Support;

use App\Support\SalesLeaderReportExcelLayout;
use PHPUnit\Framework\TestCase;

class SalesLeaderReportExcelLayoutTest extends TestCase
{
    public function test_operation_conversion_filters_stage_leaves_and_builds_two_header_rows(): void
    {
        $columns = [
            ['key' => 'index', 'label' => 'STT', 'format' => 'text'],
            ['key' => 'sale', 'label' => 'SALE', 'format' => 'text'],
            ['key' => 'total_contacts', 'label' => 'Tổng contact', 'format' => 'number'],
            ['key' => 'total_closed', 'label' => 'Tổng chốt đơn', 'format' => 'number'],
            ['key' => 'total_rate', 'label' => 'Tổng tỷ lệ', 'format' => 'percent'],
            ['key' => 'revenue', 'label' => 'Tổng doanh số', 'format' => 'currency'],
            ['key' => 'call_1_contacts', 'label' => 'c1c', 'format' => 'number'],
            ['key' => 'call_1_closed', 'label' => 'c1o', 'format' => 'number'],
            ['key' => 'call_1_rate', 'label' => 'c1r', 'format' => 'percent'],
            ['key' => 'call_1_revenue', 'label' => 'c1v', 'format' => 'currency'],
            ['key' => 'call_2_contacts', 'label' => 'c2c', 'format' => 'number'],
            ['key' => 'call_2_closed', 'label' => 'c2o', 'format' => 'number'],
            ['key' => 'call_2_rate', 'label' => 'c2r', 'format' => 'percent'],
            ['key' => 'call_2_revenue', 'label' => 'c2v', 'format' => 'currency'],
        ];

        $layout = SalesLeaderReportExcelLayout::forPage('4.6.1', $columns, [
            'stages' => [
                ['key' => 'call_1', 'label' => 'Gọi lần 1'],
            ],
        ]);

        $keys = array_column($layout['columns'], 'key');
        $this->assertContains('call_1_contacts', $keys);
        $this->assertNotContains('call_2_contacts', $keys);
        $this->assertCount(2, $layout['header_rows']);
        $this->assertSame(4, (int) ($layout['header_rows'][0][6]['colspan'] ?? 0));
        $this->assertCount(4, $layout['header_rows'][1]);
    }

    public function test_sales_team_header_matches_grouped_ui(): void
    {
        $layout = SalesLeaderReportExcelLayout::forPage('4.6.3', [
            ['key' => 'index', 'label' => 'STT', 'format' => 'text'],
            ['key' => 'sale', 'label' => 'SALE', 'format' => 'text'],
        ]);

        $this->assertSame('KHÁCH HÀNG MỚI', $layout['header_rows'][0][2]['label']);
        $this->assertSame(5, (int) $layout['header_rows'][0][2]['colspan']);
        $this->assertCount(18, $layout['header_rows'][1]);
    }
}
