<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Đọc file danh sách lead từ nhiều định dạng: .csv/.txt (text) và .xls/.xlsx (Excel).
 * Trả về từng sheet dưới dạng mảng dòng để controller tự chọn sheet chứa cột SĐT.
 */
class SpreadsheetLeadReader
{
    public const ALLOWED = ['csv', 'txt', 'xls', 'xlsx'];

    /**
     * @return array<string, list<list<string>>> tên sheet => danh sách dòng (đã trim, bỏ dòng trống)
     */
    public static function sheets(string $path, string $ext): array
    {
        $ext = strtolower($ext);

        if (in_array($ext, ['csv', 'txt'], true)) {
            return ['CSV' => self::parseCsv($path)];
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $out = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            // formatData=true để giữ nguyên chuỗi hiển thị (vd SĐT có số 0 đầu khi ô định dạng text).
            $rows = $sheet->toArray(null, true, true, false);
            $out[$sheet->getTitle()] = self::cleanRows($rows);
        }

        return $out;
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     * @return list<list<string>>
     */
    private static function cleanRows(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $vals = array_map(static fn ($v) => trim((string) ($v ?? '')), array_values($row));

            if (implode('', $vals) === '') {
                continue; // bỏ dòng hoàn toàn trống
            }

            $clean[] = $vals;
        }

        return $clean;
    }

    /**
     * Đọc CSV → mảng dòng. Tự nhận diện dấu phân cách (, ; hoặc tab) & bỏ BOM.
     *
     * @return list<list<string>>
     */
    private static function parseCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = self::detectDelimiter($firstLine);

        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_map(static fn ($v) => trim((string) $v), str_getcsv($line, $delimiter, '"', '\\'));
        }

        return $rows;
    }

    private static function detectDelimiter(string $line): string
    {
        $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($candidates);

        return (string) array_key_first($candidates) ?: ',';
    }
}
