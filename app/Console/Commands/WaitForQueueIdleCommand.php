<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class WaitForQueueIdleCommand extends Command
{
    protected $signature = 'queue:wait-empty
        {--queue=* : Queue names to watch; defaults to every SaleOps lane}
        {--timeout=60 : Maximum seconds to wait}
        {--poll=250 : Poll interval in milliseconds}
        {--include-delayed : Treat delayed jobs as pending}';

    protected $description = 'Wait until the configured Redis queue lanes have no ready or reserved jobs';

    public function handle(): int
    {
        if (config('queue.default') !== 'redis') {
            $this->components->error('QUEUE_CONNECTION must be redis for queue:wait-empty.');

            return self::FAILURE;
        }

        $queues = collect((array) $this->option('queue'))
            ->flatMap(fn (string $value): array => array_filter(array_map('trim', explode(',', $value))))
            ->filter()
            ->unique()
            ->values();

        if ($queues->isEmpty()) {
            $queues = collect(config('saleops.queues', []))->values()->unique()->values();
        }

        $timeout = max(1, (int) $this->option('timeout'));
        $pollMilliseconds = max(50, (int) $this->option('poll'));
        $includeDelayed = (bool) $this->option('include-delayed');
        $deadline = microtime(true) + $timeout;
        $connection = (string) config('queue.connections.redis.connection', 'queue');

        try {
            $redis = Redis::connection($connection);

            do {
                $pending = $queues->mapWithKeys(function (string $queue) use ($redis, $includeDelayed): array {
                    $key = 'queues:'.$queue;
                    $ready = (int) $redis->llen($key);
                    $reserved = (int) $redis->zcard($key.':reserved');
                    $delayed = $includeDelayed ? (int) $redis->zcard($key.':delayed') : 0;

                    return [$queue => $ready + $reserved + $delayed];
                })->filter(fn (int $count): bool => $count > 0);

                if ($pending->isEmpty()) {
                    $this->components->info('Redis queues are idle: '.$queues->implode(', '));

                    return self::SUCCESS;
                }

                usleep($pollMilliseconds * 1000);
            } while (microtime(true) < $deadline);

            $this->components->error(
                'Timed out waiting for Redis queues: '.
                $pending->map(fn (int $count, string $queue): string => "{$queue}={$count}")->implode(', ')
            );

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Unable to inspect Redis queues: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
