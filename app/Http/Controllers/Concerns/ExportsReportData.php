<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ReportCsvExporter;
use App\Support\ReportExcelExporter;
use App\Support\ReportExportIdentity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsReportData
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     */
    protected function maybeExportReport(
        Request $request,
        array $rows,
        array $columns,
        string $basename,
        array $meta = [],
    ): StreamedResponse|Response|null {
        $export = $request->query('export');
        $safeBase = ReportExportIdentity::basename($basename);

        if ($export === 'csv') {
            return ReportCsvExporter::download($safeBase.'.csv', $rows, $columns);
        }

        if (in_array($export, ['xls', 'excel', '1'], true)) {
            return ReportExcelExporter::download($safeBase, $rows, $columns, array_merge([
                'brand' => ReportExportIdentity::brand(),
                'generated_at' => now()->format('Y-m-d H:i'),
                'period_label' => __('reports.export.period'),
                'generated_label' => __('reports.export.generated'),
            ], $meta));
        }

        return null;
    }
}
