<?php

namespace App\Support;

use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ReportExcelExporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @param  array<string, mixed>  $meta  title, subtitle, date_from, date_to, generated_at, brand
     */
    public static function download(string $filename, array $rows, array $columns, array $meta = []): Response
    {
        $brand = trim((string) ($meta['brand'] ?? ''));
        if ($brand === '' || str_contains(mb_strtolower($brand), 'pushsale')) {
            $meta['brand'] = ReportExportIdentity::brand();
        }

        $meta['generated_at'] = $meta['generated_at'] ?? now()->format('Y-m-d H:i');

        $html = View::make('exports.report-excel', [
            'meta' => $meta,
            'rows' => $rows,
            'columns' => $columns,
        ])->render();

        $safeName = ReportExportIdentity::sanitizeFilename($filename);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'.xls"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
