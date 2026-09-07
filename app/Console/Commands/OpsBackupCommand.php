<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Local disaster-recovery backup: MySQL dump + storage/app.
 * Prefer mysqldump when available; otherwise stream via PDO (no extra packages).
 */
class OpsBackupCommand extends Command
{
    protected $signature = 'ops:backup
        {--path= : Backup root (default OPS_BACKUP_PATH or /home/deploy/backups/erm-pushsale)}
        {--skip-db : Skip database dump}
        {--skip-files : Skip storage/app archive}
        {--keep-daily=7 : Daily snapshots to keep}
        {--keep-weekly=4 : Weekly (Sunday) snapshots to keep}
        {--keep-monthly=3 : Monthly (1st) snapshots to keep}';

    protected $description = 'Backup database + storage/app with retention (local DR)';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('path') ?: env('OPS_BACKUP_PATH', '/home/deploy/backups/erm-pushsale')), '/');
        $stamp = now()->format('Ymd_His');
        $dir = "{$root}/{$stamp}";

        if (! File::isDirectory($root) && ! File::makeDirectory($root, 0750, true)) {
            $this->error("Cannot create backup root: {$root}");

            return self::FAILURE;
        }

        File::makeDirectory($dir, 0750, true, true);
        $this->info("Backup → {$dir}");

        $meta = [
            'started_at' => now()->toIso8601String(),
            'host' => gethostname() ?: null,
            'app_url' => config('app.url'),
            'db_host' => config('database.connections.mysql.host'),
            'db_database' => config('database.connections.mysql.database'),
            'git' => trim((string) @shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse --short HEAD 2>/dev/null')),
            'files' => [],
        ];

        try {
            if (! $this->option('skip-db')) {
                $dbFile = "{$dir}/database.sql.gz";
                $this->backupDatabase($dbFile);
                $meta['files']['database'] = $this->fileMeta($dbFile);
            }

            if (! $this->option('skip-files')) {
                $filesArchive = "{$dir}/storage-app.tar.gz";
                $this->backupStorageApp($filesArchive);
                $meta['files']['storage_app'] = $this->fileMeta($filesArchive);
            }

            // Capture non-secret env flags for restore checklist (never dump secrets).
            $meta['env_flags'] = [
                'APP_ENV' => config('app.env'),
                'APP_DEBUG' => config('app.debug'),
                'SESSION_SECURE_COOKIE' => config('session.secure'),
            ];

            $meta['finished_at'] = now()->toIso8601String();
            $metaPath = "{$dir}/meta.json";
            File::put($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            @chmod($metaPath, 0640);

            $this->prune($root, (int) $this->option('keep-daily'), (int) $this->option('keep-weekly'), (int) $this->option('keep-monthly'));

            $this->info('Backup OK.');
            foreach ($meta['files'] as $name => $info) {
                $this->line(sprintf('  %s: %s (%s)', $name, $info['path'], $info['size_human']));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());
            File::put("{$dir}/FAILED.txt", $e->getMessage()."\n".$e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function backupDatabase(string $gzPath): void
    {
        $mysqldump = $this->findMysqldump();
        if ($mysqldump) {
            $this->line("DB: using {$mysqldump}");
            $this->dumpWithMysqldump($mysqldump, $gzPath);

            return;
        }

        $this->warn('DB: mysqldump not found — streaming via PDO (slower).');
        $this->dumpWithPdo($gzPath);
    }

    private function findMysqldump(): ?string
    {
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', trim((string) @shell_exec('command -v mysqldump 2>/dev/null'))] as $bin) {
            if ($bin !== '' && is_executable($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function dumpWithMysqldump(string $bin, string $gzPath): void
    {
        $cfg = config('database.connections.mysql');
        $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
        $cnf = "[client]\n"
            ."host=".($cfg['host'] ?? '127.0.0.1')."\n"
            .'port='.($cfg['port'] ?? 3306)."\n"
            .'user='.($cfg['username'] ?? '')."\n"
            .'password='.($cfg['password'] ?? '')."\n";
        if (! empty($cfg['unix_socket'])) {
            $cnf .= 'socket='.$cfg['unix_socket']."\n";
        }
        file_put_contents($tmpCnf, $cnf);
        chmod($tmpCnf, 0600);

        $db = $cfg['database'] ?? '';
        $cmd = sprintf(
            '%s --defaults-extra-file=%s --single-transaction --quick --routines --triggers --events --set-gtid-purged=OFF %s | gzip -c > %s',
            escapeshellarg($bin),
            escapeshellarg($tmpCnf),
            escapeshellarg($db),
            escapeshellarg($gzPath),
        );

        $exit = 0;
        system($cmd, $exit);
        @unlink($tmpCnf);

        if ($exit !== 0 || ! is_file($gzPath) || filesize($gzPath) < 64) {
            throw new \RuntimeException("mysqldump failed (exit {$exit})");
        }
        @chmod($gzPath, 0640);
    }

    private function dumpWithPdo(string $gzPath): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        $gz = gzopen($gzPath, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException("Cannot open {$gzPath}");
        }

        $write = function (string $sql) use ($gz): void {
            if (gzwrite($gz, $sql) === false) {
                throw new \RuntimeException('gzwrite failed');
            }
        };

        $write("-- ERM Pushsale PDO backup\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = DB::select('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
        foreach ($tables as $row) {
            $arr = (array) $row;
            $table = (string) reset($arr);
            if ($table === '') {
                continue;
            }

            $safe = str_replace('`', '``', $table);
            $this->line("  dump {$table}");
            $create = DB::select("SHOW CREATE TABLE `{$safe}`");
            $createArr = (array) ($create[0] ?? []);
            $createSql = $createArr['Create Table'] ?? (array_values($createArr)[1] ?? null);
            if (! is_string($createSql) || $createSql === '') {
                throw new \RuntimeException("SHOW CREATE TABLE failed for {$table}");
            }

            $write("DROP TABLE IF EXISTS `{$safe}`;\n");
            $write($createSql.";\n\n");

            $offset = 0;
            $chunk = 500;
            while (true) {
                $rows = DB::table($table)->offset($offset)->limit($chunk)->get();
                if ($rows->isEmpty()) {
                    break;
                }
                foreach ($rows as $r) {
                    $cols = [];
                    $vals = [];
                    foreach ((array) $r as $c => $v) {
                        $cols[] = '`'.str_replace('`', '``', (string) $c).'`';
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote((string) $v);
                        }
                    }
                    $write('INSERT INTO `'.$safe.'` ('.implode(',', $cols).') VALUES ('.implode(',', $vals).");\n");
                }
                $offset += $chunk;
                if ($rows->count() < $chunk) {
                    break;
                }
            }
            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        @chmod($gzPath, 0640);

        if (filesize($gzPath) < 64) {
            throw new \RuntimeException('PDO dump produced empty file');
        }
    }

    private function backupStorageApp(string $archivePath): void
    {
        $src = storage_path('app');
        if (! is_dir($src)) {
            $this->warn('storage/app missing — skip files');

            return;
        }

        $cmd = sprintf(
            'tar -czf %s -C %s --exclude=./public/tmp --exclude=./backups .',
            escapeshellarg($archivePath),
            escapeshellarg($src),
        );
        $exit = 0;
        system($cmd, $exit);
        if ($exit !== 0 || ! is_file($archivePath)) {
            throw new \RuntimeException("tar storage/app failed (exit {$exit})");
        }
        @chmod($archivePath, 0640);
    }

    /**
     * @return array{path:string,size:int,size_human:string,sha256:string}
     */
    private function fileMeta(string $path): array
    {
        $size = (int) filesize($path);

        return [
            'path' => basename($path),
            'size' => $size,
            'size_human' => $this->humanBytes($size),
            'sha256' => hash_file('sha256', $path) ?: '',
        ];
    }

    private function humanBytes(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($u) - 1) {
            $n /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $n, $u[$i]);
    }

    private function prune(string $root, int $keepDaily, int $keepWeekly, int $keepMonthly): void
    {
        $dirs = collect(File::directories($root))
            ->filter(fn ($d) => (bool) preg_match('/\/\d{8}_\d{6}$/', $d))
            ->sort()
            ->values();

        $keep = [];

        // Daily: newest N
        foreach ($dirs->reverse()->take(max(0, $keepDaily)) as $d) {
            $keep[$d] = true;
        }

        // Weekly: one per ISO week, newest first
        $weeks = [];
        foreach ($dirs->reverse() as $d) {
            $base = basename($d);
            $dt = \DateTimeImmutable::createFromFormat('Ymd_His', $base);
            if (! $dt) {
                continue;
            }
            $week = $dt->format('o-W');
            if (isset($weeks[$week])) {
                continue;
            }
            $weeks[$week] = $d;
            $keep[$d] = true;
            if (count($weeks) >= max(0, $keepWeekly)) {
                break;
            }
        }

        // Monthly: one per YYYY-MM
        $months = [];
        foreach ($dirs->reverse() as $d) {
            $base = basename($d);
            $dt = \DateTimeImmutable::createFromFormat('Ymd_His', $base);
            if (! $dt) {
                continue;
            }
            $month = $dt->format('Y-m');
            if (isset($months[$month])) {
                continue;
            }
            $months[$month] = $d;
            $keep[$d] = true;
            if (count($months) >= max(0, $keepMonthly)) {
                break;
            }
        }

        foreach ($dirs as $d) {
            if (isset($keep[$d])) {
                continue;
            }
            $this->line('Prune '.$d);
            File::deleteDirectory($d);
        }
    }
}
