<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthSnapshotService
{
    private const CPU_CACHE_KEY = 'system-monitor:cpu-sample';

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $processes = $this->processSnapshot();
        $queues = $this->queueSnapshot($processes);
        $checks = $this->healthChecks();

        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'host' => $this->hostSnapshot(),
            'cpu' => $this->cpuSnapshot(),
            'memory' => $this->memorySnapshot(),
            'disks' => $this->diskSnapshot(),
            'runtime' => $this->runtimeSnapshot(),
            'services' => $this->serviceSnapshot($processes),
            'queues' => $queues,
            'checks' => $checks,
            'summary' => $this->summary($checks, $queues),
        ];
    }

    /** @return array<string, mixed> */
    private function hostSnapshot(): array
    {
        $uptime = $this->readUptimeSeconds();

        return [
            'hostname' => gethostname() ?: php_uname('n'),
            'os' => trim(php_uname('s').' '.php_uname('r')),
            'machine' => php_uname('m'),
            'uptime_seconds' => $uptime,
            'uptime_human' => $this->duration($uptime),
            'server_time' => now()->format('d/m/Y H:i:s'),
            'timezone' => config('app.timezone'),
        ];
    }

    /** @return array<string, mixed> */
    private function cpuSnapshot(): array
    {
        $sample = $this->readCpuSample();
        $previous = Cache::get(self::CPU_CACHE_KEY);
        Cache::put(self::CPU_CACHE_KEY, $sample, now()->addMinutes(10));

        $usage = null;
        if (is_array($previous) && isset($previous['total'], $previous['idle'])) {
            $totalDelta = max(0, $sample['total'] - (int) $previous['total']);
            $idleDelta = max(0, $sample['idle'] - (int) $previous['idle']);
            if ($totalDelta > 0) {
                $usage = round((1 - ($idleDelta / $totalDelta)) * 100, 1);
            }
        }

        $load = sys_getloadavg() ?: [0, 0, 0];
        $cores = $this->cpuCores();

        return [
            'model' => $this->cpuModel(),
            'cores' => $cores,
            'usage_percent' => $usage,
            'load_1' => round((float) ($load[0] ?? 0), 2),
            'load_5' => round((float) ($load[1] ?? 0), 2),
            'load_15' => round((float) ($load[2] ?? 0), 2),
            'load_percent' => $cores > 0 ? round(((float) ($load[0] ?? 0) / $cores) * 100, 1) : null,
            'status' => $this->thresholdStatus($usage ?? ($cores > 0 ? (((float) ($load[0] ?? 0) / $cores) * 100) : 0), 75, 90),
        ];
    }

    /** @return array{idle:int,total:int} */
    private function readCpuSample(): array
    {
        $line = $this->readFirstLine('/proc/stat');
        if (! str_starts_with($line, 'cpu ')) {
            return ['idle' => 0, 'total' => 0];
        }

        $parts = array_values(array_filter(explode(' ', trim(substr($line, 4))), fn ($v) => $v !== ''));
        $values = array_map('intval', $parts);
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);

        return [
            'idle' => $idle,
            'total' => array_sum($values),
        ];
    }

    private function cpuCores(): int
    {
        $cpuinfo = $this->readFile('/proc/cpuinfo');
        if ($cpuinfo !== '') {
            preg_match_all('/^processor\s*:/m', $cpuinfo, $matches);
            if (count($matches[0]) > 0) {
                return count($matches[0]);
            }
        }

        return 1;
    }

    private function cpuModel(): string
    {
        $cpuinfo = $this->readFile('/proc/cpuinfo');
        if (preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/^Hardware\s*:\s*(.+)$/m', $cpuinfo, $m)) {
            return trim($m[1]);
        }

        return 'Không xác định';
    }

    /** @return array<string, mixed> */
    private function memorySnapshot(): array
    {
        $info = $this->readMemInfo();
        $total = ($info['MemTotal'] ?? 0) * 1024;
        $available = ($info['MemAvailable'] ?? $info['MemFree'] ?? 0) * 1024;
        $used = max(0, $total - $available);
        $swapTotal = ($info['SwapTotal'] ?? 0) * 1024;
        $swapFree = ($info['SwapFree'] ?? 0) * 1024;
        $swapUsed = max(0, $swapTotal - $swapFree);
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : null;
        $swapPercent = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0;

        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'percent' => $percent,
            'total_human' => $this->bytes($total),
            'used_human' => $this->bytes($used),
            'available_human' => $this->bytes($available),
            'swap_total' => $swapTotal,
            'swap_used' => $swapUsed,
            'swap_percent' => $swapPercent,
            'swap_total_human' => $this->bytes($swapTotal),
            'swap_used_human' => $this->bytes($swapUsed),
            'status' => $this->thresholdStatus($percent ?? 0, 75, 90),
        ];
    }

    /** @return array<string, int> */
    private function readMemInfo(): array
    {
        $rows = [];
        foreach (explode("\n", $this->readFile('/proc/meminfo')) as $line) {
            if (preg_match('/^([A-Za-z_()]+):\s+(\d+)/', $line, $m)) {
                $rows[$m[1]] = (int) $m[2];
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function diskSnapshot(): array
    {
        $paths = [
            'Ứng dụng' => base_path(),
            'Storage' => storage_path(),
            'Public' => public_path(),
        ];

        $rows = [];
        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                continue;
            }
            $total = @disk_total_space($path) ?: 0;
            $free = @disk_free_space($path) ?: 0;
            $used = max(0, $total - $free);
            $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

            $rows[] = [
                'label' => $label,
                'path' => $path,
                'total' => $total,
                'used' => $used,
                'free' => $free,
                'percent' => $percent,
                'total_human' => $this->bytes($total),
                'used_human' => $this->bytes($used),
                'free_human' => $this->bytes($free),
                'status' => $this->thresholdStatus($percent, 80, 92),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function runtimeSnapshot(): array
    {
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $logPath = $this->laravelLogPath();

        return [
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => (bool) ($opcache['opcache_enabled'] ?? false),
            'queue_connection' => config('queue.default'),
            'cache_store' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'log_channel' => config('logging.default'),
            'log_file' => $logPath,
            'log_size_human' => $logPath && File::exists($logPath) ? $this->bytes((int) File::size($logPath)) : '—',
        ];
    }

    /** @param array<string, array<string, mixed>> $processes */
    private function serviceSnapshot(array $processes): array
    {
        $services = [
            ['key' => 'web', 'label' => 'Nginx/Apache', 'patterns' => ['nginx', 'apache2', 'httpd']],
            ['key' => 'php_fpm', 'label' => 'PHP-FPM', 'patterns' => ['php-fpm']],
            ['key' => 'mysql', 'label' => 'MySQL/MariaDB', 'patterns' => ['mysqld', 'mariadbd']],
            ['key' => 'redis', 'label' => 'Redis', 'patterns' => ['redis-server']],
            ['key' => 'supervisor', 'label' => 'Supervisor', 'patterns' => ['supervisord']],
            ['key' => 'horizon', 'label' => 'Laravel Horizon', 'patterns' => ['artisan horizon', 'horizon:supervisor', 'horizon:work']],
            ['key' => 'queue', 'label' => 'Legacy Queue Worker', 'patterns' => ['queue:work', 'queue:listen']],
            ['key' => 'reverb', 'label' => 'Laravel Reverb', 'patterns' => ['reverb:start']],
            ['key' => 'node', 'label' => 'Node/PM2/Vite', 'patterns' => ['node', 'pm2', 'vite']],
        ];

        return array_map(function (array $service) use ($processes) {
            $matches = [];
            foreach ($processes as $pid => $process) {
                foreach ($service['patterns'] as $pattern) {
                    if (str_contains(strtolower($process['cmd']), strtolower($pattern))) {
                        $matches[] = [
                            'pid' => (int) $pid,
                            'cmd' => $process['cmd'],
                        ];
                        break;
                    }
                }
            }

            return [
                'key' => $service['key'],
                'label' => $service['label'],
                'running' => count($matches) > 0,
                'count' => count($matches),
                'examples' => array_slice($matches, 0, 3),
                'status' => count($matches) > 0 ? 'ok' : 'warning',
            ];
        }, $services);
    }

    /** @return array<string, mixed> */
    private function queueSnapshot(array $processes): array
    {
        $expectedQueues = array_values(config('saleops.queues', [
            'webhooks', 'shipping-webhooks', 'messages', 'notifications', 'exports', 'reports', 'default',
        ]));

        $byQueue = [];
        $failed = null;
        $databasePending = 0;

        try {
            if (config('queue.default') === 'redis') {
                $byQueue = $this->redisQueueRows($expectedQueues);
            } elseif (Schema::hasTable('jobs')) {
                $byQueue = DB::table('jobs')
                    ->selectRaw('queue, COUNT(*) as count, MIN(created_at) as oldest')
                    ->groupBy('queue')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($row) => [
                        'queue' => (string) $row->queue,
                        'ready' => (int) $row->count,
                        'delayed' => 0,
                        'reserved' => 0,
                        'count' => (int) $row->count,
                        'oldest_seconds' => $row->oldest ? max(0, now()->timestamp - (int) $row->oldest) : null,
                        'oldest_human' => $row->oldest ? $this->duration(max(0, now()->timestamp - (int) $row->oldest)) : '—',
                    ])
                    ->values()
                    ->all();
            }

            // A deployment may switch QUEUE_CONNECTION from database to Redis while
            // old database-backed jobs are still waiting. Keep surfacing that count so
            // operators can drain them instead of silently abandoning work.
            if (Schema::hasTable('jobs')) {
                $databasePending = (int) DB::table('jobs')->count();
            }

            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            $byQueue = [];
        }

        $horizon = $this->horizonSnapshot();
        $legacyWorkers = array_filter($processes, fn ($p) => str_contains($p['cmd'], 'queue:work') || str_contains($p['cmd'], 'queue:listen'));
        $totalPending = array_sum(array_map(fn ($row) => (int) $row['count'], $byQueue));
        $workers = (int) ($horizon['processes'] ?? 0) + count($legacyWorkers);

        return [
            'connection' => config('queue.default'),
            'redis_connection' => config('queue.connections.redis.connection'),
            'pending_total' => $totalPending,
            'failed_total' => $failed,
            'workers' => $workers,
            'legacy_workers' => count($legacyWorkers),
            'database_pending' => $databasePending,
            'queues' => $byQueue,
            'expected_queues' => $expectedQueues,
            'horizon' => $horizon,
            'status' => $this->queueStatus($totalPending, $failed, $workers, $horizon, $databasePending),
        ];
    }

    /** @param list<string> $queues @return list<array<string, mixed>> */
    private function redisQueueRows(array $queues): array
    {
        $connection = (string) config('queue.connections.redis.connection', 'queue');
        $redis = Redis::connection($connection);

        return collect($queues)->map(function (string $queue) use ($redis): array {
            $key = 'queues:'.$queue;
            $ready = (int) $redis->llen($key);
            $delayed = (int) $redis->zcard($key.':delayed');
            $reserved = (int) $redis->zcard($key.':reserved');
            $oldestTimestamp = $this->queuePayloadTimestamp($redis->lindex($key, 0));

            return [
                'queue' => $queue,
                'ready' => $ready,
                'delayed' => $delayed,
                'reserved' => $reserved,
                'count' => $ready + $delayed,
                'oldest_seconds' => $oldestTimestamp ? max(0, now()->timestamp - $oldestTimestamp) : null,
                'oldest_human' => $oldestTimestamp ? $this->duration(max(0, now()->timestamp - $oldestTimestamp)) : '—',
            ];
        })->values()->all();
    }

    private function queuePayloadTimestamp(mixed $payload): ?int
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        foreach (['pushedAt', 'createdAt', 'created_at'] as $key) {
            if (isset($decoded[$key]) && is_numeric($decoded[$key])) {
                return (int) $decoded[$key];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function horizonSnapshot(): array
    {
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return [
                'installed' => false,
                'running' => false,
                'status' => 'not_installed',
                'masters' => [],
                'supervisors' => [],
                'processes' => 0,
                'dashboard_url' => null,
            ];
        }

        try {
            $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
            $supervisors = app(\Laravel\Horizon\Contracts\SupervisorRepository::class)->all();

            $supervisorRows = collect($supervisors)->map(function (object $supervisor): array {
                $processes = is_array($supervisor->processes ?? null) ? $supervisor->processes : [];
                $options = is_array($supervisor->options ?? null) ? $supervisor->options : [];

                return [
                    'name' => (string) ($supervisor->name ?? ''),
                    'master' => (string) ($supervisor->master ?? ''),
                    'pid' => (int) ($supervisor->pid ?? 0),
                    'status' => (string) ($supervisor->status ?? 'unknown'),
                    'processes' => array_sum(array_map('intval', $processes)),
                    'processes_by_queue' => $processes,
                    'queues' => array_values((array) ($options['queue'] ?? [])),
                    'balance' => $options['balance'] ?? null,
                    'min_processes' => (int) ($options['minProcesses'] ?? 0),
                    'max_processes' => (int) ($options['maxProcesses'] ?? 0),
                    'timeout' => (int) ($options['timeout'] ?? 0),
                ];
            })->values()->all();

            $masterRows = collect($masters)->map(fn (object $master) => [
                'name' => (string) ($master->name ?? ''),
                'environment' => (string) ($master->environment ?? ''),
                'pid' => (int) ($master->pid ?? 0),
                'status' => (string) ($master->status ?? 'unknown'),
                'supervisors' => array_values((array) ($master->supervisors ?? [])),
            ])->values()->all();

            $running = collect($masterRows)->contains(fn (array $master) => $master['status'] === 'running');

            return [
                'installed' => true,
                'running' => $running,
                'status' => $running ? 'running' : 'inactive',
                'masters' => $masterRows,
                'supervisors' => $supervisorRows,
                'processes' => collect($supervisorRows)->sum('processes'),
                'dashboard_url' => url('/'.trim((string) config('horizon.path', 'horizon'), '/')),
            ];
        } catch (Throwable $e) {
            return [
                'installed' => true,
                'running' => false,
                'status' => 'unavailable',
                'masters' => [],
                'supervisors' => [],
                'processes' => 0,
                'dashboard_url' => url('/'.trim((string) config('horizon.path', 'horizon'), '/')),
                'error' => app()->isProduction() ? null : $e->getMessage(),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function healthChecks(): array
    {
        return [
            $this->check('database', 'Database', function () {
                DB::select('select 1');
            }),
            $this->check('cache', 'Cache', function () {
                Cache::put('system-monitor:cache-check', 'ok', 30);
                if (Cache::get('system-monitor:cache-check') !== 'ok') {
                    throw new \RuntimeException('Cache write/read mismatch');
                }
            }),
            $this->check('redis', 'Redis', function () {
                if (! class_exists(Redis::class)) {
                    return;
                }
                Redis::connection()->ping();
            }, optional: true),
            $this->check('redis_queue', 'Redis queue connection', function () {
                if (config('queue.default') !== 'redis') {
                    throw new \RuntimeException('QUEUE_CONNECTION phải là redis khi sử dụng Horizon.');
                }

                Redis::connection((string) config('queue.connections.redis.connection', 'queue'))->ping();
            }),
            [
                'key' => 'horizon_package',
                'label' => 'Laravel Horizon package',
                'ok' => class_exists(\Laravel\Horizon\Horizon::class),
                'status' => class_exists(\Laravel\Horizon\Horizon::class) ? 'ok' : 'critical',
                'message' => class_exists(\Laravel\Horizon\Horizon::class)
                    ? 'Đã cài đặt'
                    : 'Chưa có laravel/horizon trong vendor.',
            ],
            $this->check('storage', 'Storage writable', function () {
                $path = storage_path('framework/cache/system-monitor-'.uniqid('', true).'.tmp');
                File::ensureDirectoryExists(dirname($path));
                File::put($path, 'ok');
                File::delete($path);
            }),
            [
                'key' => 'app_debug',
                'label' => 'APP_DEBUG production',
                'ok' => ! (bool) config('app.debug') || config('app.env') !== 'production',
                'status' => (! (bool) config('app.debug') || config('app.env') !== 'production') ? 'ok' : 'critical',
                'message' => config('app.env') === 'production' && config('app.debug') ? 'APP_DEBUG=true trong production' : 'OK',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function check(string $key, string $label, callable $callback, bool $optional = false): array
    {
        try {
            $callback();

            return ['key' => $key, 'label' => $label, 'ok' => true, 'status' => 'ok', 'message' => 'OK'];
        } catch (Throwable $e) {
            return [
                'key' => $key,
                'label' => $label,
                'ok' => $optional,
                'status' => $optional ? 'warning' : 'critical',
                'message' => $e->getMessage(),
            ];
        }
    }

    /** @param array<int, array<string, mixed>> $checks @param array<string, mixed> $queues */
    private function summary(array $checks, array $queues): array
    {
        $critical = collect($checks)->where('status', 'critical')->count();
        $warning = collect($checks)->where('status', 'warning')->count();
        if (($queues['status'] ?? 'ok') === 'critical') {
            $critical++;
        } elseif (($queues['status'] ?? 'ok') === 'warning') {
            $warning++;
        }

        return [
            'status' => $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'ok'),
            'critical' => $critical,
            'warning' => $warning,
        ];
    }

    /** @return array<string, array{cmd: string}> */
    private function processSnapshot(): array
    {
        $rows = [];
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $pid = basename(dirname($file));
            $cmd = @file_get_contents($file);
            if (! is_string($cmd) || $cmd === '') {
                continue;
            }
            $cmd = trim(str_replace("\0", ' ', $cmd));
            if ($cmd === '') {
                continue;
            }
            $rows[$pid] = ['cmd' => $cmd];
        }

        return $rows;
    }

    /** @param array<string, mixed> $horizon */
    private function queueStatus(
        int $pending,
        ?int $failed,
        int $workers,
        array $horizon = [],
        int $databasePending = 0,
    ): string
    {
        if (($horizon['installed'] ?? false) && ! ($horizon['running'] ?? false)) {
            return 'critical';
        }

        if ($pending > 0 && $workers === 0) {
            return 'critical';
        }

        if ($databasePending > 0 || ($failed ?? 0) > 0 || $pending > 500) {
            return 'warning';
        }

        return 'ok';
    }

    private function thresholdStatus(float|int $value, float $warning, float $critical): string
    {
        if ($value >= $critical) {
            return 'critical';
        }
        if ($value >= $warning) {
            return 'warning';
        }

        return 'ok';
    }

    private function readUptimeSeconds(): int
    {
        $content = $this->readFile('/proc/uptime');
        if ($content === '') {
            return 0;
        }

        return (int) floor((float) explode(' ', trim($content))[0]);
    }

    private function readFirstLine(string $path): string
    {
        $content = $this->readFile($path);
        $pos = strpos($content, "\n");

        return $pos === false ? trim($content) : trim(substr($content, 0, $pos));
    }

    private function readFile(string $path): string
    {
        return is_readable($path) ? (string) @file_get_contents($path) : '';
    }

    private function laravelLogPath(): ?string
    {
        $daily = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        if (File::exists($daily)) {
            return $daily;
        }
        $single = storage_path('logs/laravel.log');

        return File::exists($single) ? $single : null;
    }

    private function bytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $value : number_format($value, 1)).' '.$units[$i];
    }

    private function duration(?int $seconds): string
    {
        if (! $seconds) {
            return '—';
        }
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);

        $parts = [];
        if ($days) {
            $parts[] = $days.' ngày';
        }
        if ($hours) {
            $parts[] = $hours.' giờ';
        }
        if ($minutes || $parts === []) {
            $parts[] = $minutes.' phút';
        }

        return implode(' ', array_slice($parts, 0, 3));
    }
}
