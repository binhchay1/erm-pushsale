<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvExporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, label: string}>  $columns
     */
    public static function download(string $filename, array $rows, array $columns): StreamedResponse
    {
        $safeName = ReportExportIdentity::sanitizeFilename($filename);
        if (! str_ends_with(strtolower($safeName), '.csv')) {
            $safeName .= '.csv';
        }

        return response()->streamDownload(function () use ($rows, $columns) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, array_column($columns, 'label'));

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $value = $row[$column['key']] ?? '';
                    $line[] = is_scalar($value) || $value === null ? (string) ($value ?? '') : '';
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $safeName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
