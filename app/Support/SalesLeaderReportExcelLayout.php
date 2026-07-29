<?php

namespace App\Support;

/**
 * Excel layout for sales-leader reports 4.6.1–4.6.5 so export headers match on-screen tables.
 */
final class SalesLeaderReportExcelLayout
{
    /**
     * @param  list<array{key: string, label: string, format?: string}>  $leafColumns
     * @param  array<string, mixed>  $summary
     * @return array{columns: list<array{key: string, label: string, format?: string}>, header_rows: list<list<array{label: string, colspan?: int, rowspan?: int}>>}
     */
    public static function forPage(string $pageCode, array $leafColumns, array $summary = []): array
    {
        $stages = array_values(array_filter(
            (array) ($summary['stages'] ?? []),
            fn ($stage) => is_array($stage) && filled($stage['key'] ?? null),
        ));

        return match ($pageCode) {
            '4.6.1' => self::operationConversion($leafColumns, $stages),
            '4.6.2' => self::salesWork($leafColumns, $stages),
            '4.6.3' => self::salesTeam($leafColumns),
            '4.6.4' => self::salesData($leafColumns),
            '4.6.5' => self::salesOptimization(self::withOptimizationCallColumns($leafColumns)),
            default => [
                'columns' => $leafColumns,
                'header_rows' => [array_map(
                    fn (array $column) => ['label' => (string) $column['label']],
                    $leafColumns,
                )],
            ],
        };
    }

    /**
     * Keep only leaf metrics for stages currently visible on the page.
     *
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @param  list<array{key: string, label?: string}>  $stages
     * @return list<array{key: string, label: string, format?: string}>
     */
    public static function filterStageLeaves(array $columns, array $stages): array
    {
        if ($stages === []) {
            return $columns;
        }

        $allowed = [];
        foreach ($stages as $stage) {
            $allowed[(string) $stage['key']] = true;
        }

        return array_values(array_filter($columns, function (array $column) use ($allowed): bool {
            $key = (string) $column['key'];
            if (! preg_match('/^(call_\d+|care_\d+|skipped)_/', $key, $matches)) {
                return true;
            }

            return isset($allowed[$matches[1]]);
        }));
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @param  list<array{key: string, label?: string}>  $stages
     * @return array{columns: list<array{key: string, label: string, format?: string}>, header_rows: list<list<array{label: string, colspan?: int, rowspan?: int}>>}
     */
    private static function operationConversion(array $columns, array $stages): array
    {
        $columns = self::filterStageLeaves($columns, $stages);
        $row1 = [
            ['label' => 'STT', 'rowspan' => 2],
            ['label' => 'SALE', 'rowspan' => 2],
            ['label' => 'Tổng contact', 'rowspan' => 2],
            ['label' => 'Tổng chốt đơn', 'rowspan' => 2],
            ['label' => 'Tổng tỷ lệ', 'rowspan' => 2],
            ['label' => 'Tổng doanh số', 'rowspan' => 2],
        ];
        $row2 = [];
        foreach ($stages as $stage) {
            $row1[] = ['label' => (string) ($stage['label'] ?? $stage['key']), 'colspan' => 4];
            $row2[] = ['label' => 'Số contact'];
            $row2[] = ['label' => 'Chốt đơn'];
            $row2[] = ['label' => 'Tỷ lệ chốt'];
            $row2[] = ['label' => 'Doanh số'];
        }

        return ['columns' => $columns, 'header_rows' => [$row1, $row2]];
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @param  list<array{key: string, label?: string}>  $stages
     * @return array{columns: list<array{key: string, label: string, format?: string}>, header_rows: list<list<array{label: string, colspan?: int, rowspan?: int}>>}
     */
    private static function salesWork(array $columns, array $stages): array
    {
        $columns = self::filterStageLeaves($columns, $stages);
        $row1 = [
            ['label' => 'STT', 'rowspan' => 2],
            ['label' => 'SALE', 'rowspan' => 2],
            ['label' => 'Tổng contact', 'rowspan' => 2],
            ['label' => 'Tổng contact chưa TN', 'rowspan' => 2],
        ];
        $row2 = [];
        foreach ($stages as $stage) {
            $row1[] = ['label' => (string) ($stage['label'] ?? $stage['key']), 'colspan' => 2];
            $row2[] = ['label' => 'Số contact'];
            $row2[] = ['label' => 'Chưa TN'];
        }

        return ['columns' => $columns, 'header_rows' => [$row1, $row2]];
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @return array{columns: list<array{key: string, label: string, format?: string}>, header_rows: list<list<array{label: string, colspan?: int, rowspan?: int}>>}
     */
    private static function salesTeam(array $columns): array
    {
        return [
            'columns' => $columns,
            'header_rows' => [
                [
                    ['label' => 'STT', 'rowspan' => 2],
                    ['label' => 'SALE', 'rowspan' => 2],
                    ['label' => 'KHÁCH HÀNG MỚI', 'colspan' => 5],
                    ['label' => 'KHÁCH HÀNG CŨ', 'colspan' => 5],
                    ['label' => 'TỔNG CHUNG', 'colspan' => 8],
                ],
                [
                    ['label' => 'Contact'],
                    ['label' => 'Chốt đơn'],
                    ['label' => 'Tỷ lệ chốt (%)'],
                    ['label' => 'Số sản phẩm'],
                    ['label' => 'Doanh số tạm tính'],
                    ['label' => 'Contact'],
                    ['label' => 'Chốt đơn'],
                    ['label' => 'Tỷ lệ chốt (%)'],
                    ['label' => 'Số sản phẩm'],
                    ['label' => 'Doanh số tạm tính'],
                    ['label' => 'Doanh số tạm tính'],
                    ['label' => 'Phí COD'],
                    ['label' => 'Hỗ trợ COD'],
                    ['label' => 'CK'],
                    ['label' => 'Đặt cọc'],
                    ['label' => 'Doanh số tạm tính sau chiết khấu'],
                    ['label' => 'KPI doanh số'],
                    ['label' => 'Tỷ lệ (%)'],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @return array{columns: list<array{key: string, label: string, format?: string}>, header_rows: list<list<array{label: string, colspan?: int, rowspan?: int}>>}
     */
    private static function salesData(array $columns): array
    {
        return [
            'columns' => $columns,
            'header_rows' => [
                [
                    ['label' => 'STT', 'rowspan' => 2],
                    ['label' => 'Sale', 'rowspan' => 2],
                    ['label' => 'Contact nhận', 'rowspan' => 2],
                    ['label' => 'Contact trùng', 'rowspan' => 2],
                    ['label' => 'Contact không trùng', 'rowspan' => 2],
                    ['label' => 'Chỉ số hôm qua', 'colspan' => 2],
                    ['label' => 'Chỉ số tháng trước', 'colspan' => 2],
                    ['label' => 'Chỉ số tháng này', 'colspan' => 2],
                    ['label' => 'Nhận dữ liệu', 'rowspan' => 2],
                ],
                [
                    ['label' => '% chốt đơn'],
                    ['label' => 'Doanh số'],
                    ['label' => '% chốt đơn'],
                    ['label' => 'Doanh số'],
                    ['label' => '% chốt đơn'],
                    ['label' => 'Doanh số'],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @return list<array{key: string, label: string, format?: string}>
     */
    private static function withOptimizationCallColumns(array $columns): array
    {
        $byKey = [];
        foreach ($columns as $column) {
            $byKey[(string) $column['key']] = $column;
        }

        $ordered = [
            'index',
            'sale',
            'receive_data',
            'provisional_revenue',
            'success_revenue',
            'contacts',
            'allocated_total',
            'allocated_duplicate',
            'allocated_unique',
            'calls_answered_ratio',
            'call_duration_seconds',
            'avg_call_seconds',
            'close_per_answered_rate',
            'closed_contacts',
            'close_rate',
            'avg_order_value',
            'products_per_order',
            'untouched',
            'revenue_per_contact',
            'cancelled_revenue',
            'returned_revenue',
        ];

        $labels = [
            'calls_answered_ratio' => 'Tổng cuộc gọi nghe máy/Tổng cuộc gọi ra',
            'call_duration_seconds' => 'Tổng thời gian gọi ra',
            'avg_call_seconds' => 'Thời gian gọi Trung bình',
            'close_per_answered_rate' => 'Số chốt đơn/Cuộc gọi ra bắt máy (%)',
        ];

        $result = [];
        foreach ($ordered as $key) {
            if (isset($byKey[$key])) {
                $result[] = $byKey[$key];
                continue;
            }
            if (isset($labels[$key])) {
                $result[] = ['key' => $key, 'label' => $labels[$key], 'format' => 'text'];
            }
        }

        return $result !== [] ? $result : $columns;
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @return array{columns: list<array{key: string, label: string, format?: string}>, header_rows: list<list<array{label: string, colspan?: int, rowspan?: int}>>}
     */
    private static function salesOptimization(array $columns): array
    {
        return [
            'columns' => $columns,
            'header_rows' => [
                [
                    ['label' => 'STT', 'rowspan' => 2],
                    ['label' => 'Sale', 'rowspan' => 2],
                    ['label' => 'Nhận dữ liệu', 'rowspan' => 2],
                    ['label' => 'Doanh số tạm tính', 'rowspan' => 2],
                    ['label' => 'Doanh số thành công', 'rowspan' => 2],
                    ['label' => 'Contact tổng', 'rowspan' => 2],
                    ['label' => 'Contact được chia', 'colspan' => 3],
                    ['label' => 'Tổng cuộc gọi nghe máy/Tổng cuộc gọi ra', 'rowspan' => 2],
                    ['label' => 'Tổng thời gian gọi ra', 'rowspan' => 2],
                    ['label' => 'Thời gian gọi Trung bình', 'rowspan' => 2],
                    ['label' => 'Số chốt đơn/Cuộc gọi ra bắt máy (%)', 'rowspan' => 2],
                    ['label' => 'Contact chốt đơn', 'rowspan' => 2],
                    ['label' => 'Tỷ lệ chốt đơn', 'rowspan' => 2],
                    ['label' => 'Giá trị TB đơn', 'rowspan' => 2],
                    ['label' => 'Số sản phẩm/Đơn', 'rowspan' => 2],
                    ['label' => 'Contact chưa tác nghiệp', 'rowspan' => 2],
                    ['label' => 'Doanh số tạm tính/Contact được chia', 'rowspan' => 2],
                    ['label' => 'Doanh số hủy', 'rowspan' => 2],
                    ['label' => 'Doanh số hoàn', 'rowspan' => 2],
                ],
                [
                    ['label' => 'Tổng'],
                    ['label' => 'Trùng'],
                    ['label' => 'Không Trùng'],
                ],
            ],
        ];
    }
}
