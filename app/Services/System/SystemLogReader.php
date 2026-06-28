<?php

namespace App\Services\System;

use Illuminate\Support\Facades\File;

class SystemLogReader
{
    private const MAX_BYTES = 512_000;

    /**
     * Đọc các dòng log gần nhất từ laravel.log (và file daily nếu có).
     *
     * @return list<array{at: string, level: string, message: string, raw: string}>
     */
    public function tail(int $lines = 200, ?string $level = null): array
    {
        $path = $this->resolveLogPath();
        if (! $path || ! File::exists($path)) {
            return [];
        }

        $content = $this->readTailBytes($path);
        $parsed = $this->parseLines($content);

        if ($level) {
            $parsed = array_values(array_filter(
                $parsed,
                fn (array $row) => strtoupper($row['level']) === strtoupper($level),
            ));
        }

        return array_slice($parsed, -$lines);
    }

    private function resolveLogPath(): ?string
    {
        $daily = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        if (File::exists($daily)) {
            return $daily;
        }

        $single = storage_path('logs/laravel.log');
        if (File::exists($single)) {
            return $single;
        }

        return null;
    }

    private function readTailBytes(string $path): string
    {
        $size = filesize($path);
        if ($size === false || $size <= self::MAX_BYTES) {
            return (string) File::get($path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        fseek($handle, -self::MAX_BYTES, SEEK_END);
        $chunk = fread($handle, self::MAX_BYTES);
        fclose($handle);

        return is_string($chunk) ? $chunk : '';
    }

    /**
     * @return list<array{at: string, level: string, message: string, raw: string}>
     */
    private function parseLines(string $content): array
    {
        $rows = [];
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.*)$/';

        foreach (preg_split("/\r\n|\n|\r/", $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match($pattern, $line, $m)) {
                $rows[] = [
                    'at' => $m[1],
                    'level' => strtoupper($m[2]),
                    'message' => $m[3],
                    'raw' => $line,
                ];
            } elseif ($rows !== []) {
                $last = array_key_last($rows);
                $rows[$last]['message'] .= "\n".$line;
                $rows[$last]['raw'] .= "\n".$line;
            }
        }

        return $rows;
    }
}
