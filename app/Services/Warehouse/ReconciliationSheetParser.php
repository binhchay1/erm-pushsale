<?php

namespace App\Services\Warehouse;

use App\Enums\ReconciliationStatus;
use Illuminate\Support\Str;

/**
 * Đọc ma trận spreadsheet đối soát theo tên cột trong config/reconciliation_columns.php.
 * Dòng tiêu đề là dòng khớp nhiều tên cột nhất, nên banner phía trên không cần biết trước vị trí.
 */
class ReconciliationSheetParser
{
    /** @param  array<string, mixed>|null  $columns */
    public function __construct(private ?array $columns = null) {}

    /**
     * @param  list<list<string>>  $matrix
     * @return list<array{order_code:?string,tracking_number:?string,reconciliation_status:?string,excel_total:?float,excel_cod:?float,note:?string}>
     */
    public function parse(array $matrix): array
    {
        if ($matrix === []) {
            return [];
        }

        $headerIndex = $this->findHeaderIndex($matrix);
        if ($headerIndex === null) {
            $map = [
                'order_code' => 0,
                'tracking_number' => 1,
                'reconciliation_status' => 2,
                'excel_total' => 3,
                'excel_cod' => 4,
                'note' => 5,
            ];
            $start = 0;
        } else {
            $map = $this->mapHeader($matrix[$headerIndex]);
            $start = $headerIndex + 1;
        }

        $rows = [];
        for ($i = $start; $i < count($matrix); $i++) {
            $line = $matrix[$i];
            $orderCode = $this->cell($line, $map['order_code'] ?? null);
            $tracking = $this->cell($line, $map['tracking_number'] ?? null);
            $status = $this->cell($line, $map['reconciliation_status'] ?? null);
            $outcome = $this->cell($line, $map['delivery_outcome'] ?? null);
            $note = $this->cell($line, $map['note'] ?? null);
            if ($status === '' && $outcome !== '') {
                $status = $outcome;
            }
            if ($outcome !== '' && ($note === '' || ! str_contains($this->norm($note), $this->norm($outcome)))) {
                $note = $note !== '' ? $outcome.' — '.$note : $outcome;
            }
            $excelTotal = $this->parseMoney($this->rawCell($line, $map['excel_total'] ?? null));
            $excelCod = $this->parseMoney($this->rawCell($line, $map['excel_cod'] ?? null));
            if ($orderCode === '' && $tracking === '') {
                continue;
            }
            if ($this->isHeaderRepeat($orderCode, $tracking)) {
                continue;
            }
            $rows[] = [
                'order_code' => $orderCode !== '' ? $orderCode : null,
                'tracking_number' => $tracking !== '' ? $tracking : null,
                'reconciliation_status' => $status !== '' ? $status : null,
                'excel_total' => $excelTotal,
                'excel_cod' => $excelCod,
                'note' => $note !== '' ? $note : null,
            ];
        }

        return $rows;
    }

    public function normalizeStatus(?string $raw): ?string
    {
        if (! filled($raw)) {
            return null;
        }
        $value = trim((string) $raw);
        if (ReconciliationStatus::tryFrom($value)) {
            return $value;
        }

        $aliases = is_array($this->config()['status_aliases'] ?? null) ? $this->config()['status_aliases'] : [];
        $norm = $this->norm($value);

        return isset($aliases[$norm]) ? (string) $aliases[$norm] : null;
    }

    /**
     * @param  list<list<string>>  $matrix
     */
    private function findHeaderIndex(array $matrix): ?int
    {
        $limit = min(count($matrix), max(1, (int) ($this->config()['header_scan_rows'] ?? 40)));
        $best = null;
        $bestScore = 0;
        for ($i = 0; $i < $limit; $i++) {
            $score = $this->headerScore($matrix[$i]);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $i;
            }
        }

        $min = max(1, (int) ($this->config()['min_header_hits'] ?? 2));

        return $bestScore >= $min ? $best : null;
    }

    /** @param  list<string>  $row */
    private function headerScore(array $row): int
    {
        $roles = [];
        foreach ($row as $cell) {
            $match = $this->bestRole($this->norm((string) $cell));
            if ($match !== null) {
                $roles[$match['role']] = true;
            }
        }

        return count($roles);
    }

    /**
     * @param  list<string>  $headerRow
     * @return array<string, int>
     */
    private function mapHeader(array $headerRow): array
    {
        $best = [];
        foreach ($headerRow as $index => $label) {
            $match = $this->bestRole($this->norm((string) $label));
            if ($match === null) {
                continue;
            }
            $role = $match['role'];
            if (! isset($best[$role]) || $match['score'] > $best[$role]['score']) {
                $best[$role] = ['index' => $index, 'score' => $match['score']];
            }
        }

        $map = [];
        foreach ($best as $role => $picked) {
            $map[$role] = $picked['index'];
        }

        return $map;
    }

    /** @return array{role:string,score:int}|null */
    private function bestRole(string $header): ?array
    {
        if ($header === '') {
            return null;
        }

        $winner = null;
        $columns = is_array($this->config()['columns'] ?? null) ? $this->config()['columns'] : [];
        foreach ($columns as $role => $spec) {
            if (! is_array($spec)) {
                continue;
            }
            foreach ($spec['exact'] ?? [] as $alias) {
                if ($header === $alias) {
                    $winner = $this->prefer($winner, (string) $role, 1000 + strlen((string) $alias));
                }
            }
            foreach ($spec['contains'] ?? [] as $alias) {
                $alias = (string) $alias;
                if ($alias !== '' && str_contains($header, $alias)) {
                    $winner = $this->prefer($winner, (string) $role, strlen($alias));
                }
            }
        }

        return $winner;
    }

    /**
     * @param  array{role:string,score:int}|null  $current
     * @return array{role:string,score:int}
     */
    private function prefer(?array $current, string $role, int $score): array
    {
        if ($current === null || $score > $current['score']) {
            return ['role' => $role, 'score' => $score];
        }

        return $current;
    }

    private function isHeaderRepeat(string $orderCode, string $tracking): bool
    {
        foreach ([$orderCode, $tracking] as $value) {
            if ($value !== '' && $this->bestRole($this->norm($value)) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $line */
    private function cell(array $line, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($line[$index] ?? ''));
    }

    /** @param  list<string>  $line */
    private function rawCell(array $line, ?int $index): mixed
    {
        if ($index === null) {
            return null;
        }

        return $line[$index] ?? null;
    }

    private function parseMoney(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        $normalized = preg_replace('/[^\d,.\-]/', '', str_replace(' ', '', (string) $raw));
        if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
            return null;
        }
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function norm(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $loaded = config('reconciliation_columns');

        return is_array($loaded) ? $loaded : [];
    }
}
