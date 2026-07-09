<?php

namespace Tests\Feature\Queue;

use Tests\TestCase;

class HorizonConfigurationTest extends TestCase
{
    public function test_every_business_queue_has_one_dedicated_production_supervisor(): void
    {
        $expectedQueues = array_values(config('saleops.queues'));
        $supervisors = config('horizon.environments.production', []);

        $supervisedQueues = collect($supervisors)
            ->flatMap(fn (array $options): array => array_values((array) ($options['queue'] ?? [])))
            ->values();

        $this->assertEqualsCanonicalizing($expectedQueues, $supervisedQueues->all());
        $this->assertSame(
            $supervisedQueues->count(),
            $supervisedQueues->unique()->count(),
            'A queue lane must not be consumed by multiple Horizon supervisors.',
        );
    }

    public function test_horizon_uses_redis_and_worker_timeouts_are_below_retry_after(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');

        $this->assertSame('queue', config('queue.connections.redis.connection'));
        $this->assertSame('horizon_meta', config('horizon.use'));
        $this->assertGreaterThan(0, $retryAfter);

        foreach (config('horizon.environments.production', []) as $name => $options) {
            $this->assertSame('redis', $options['connection'], "{$name} must use Redis.");
            $this->assertLessThan(
                $retryAfter,
                (int) $options['timeout'],
                "{$name} timeout must remain lower than Redis retry_after to prevent duplicate processing.",
            );
        }
    }

    public function test_horizon_metadata_is_isolated_from_queue_payloads_and_cache(): void
    {
        $queueDatabase = (string) config('database.redis.queue.database');
        $horizonDatabase = (string) config('database.redis.horizon_meta.database');
        $cacheDatabase = (string) config('database.redis.cache.database');

        $this->assertNotSame($queueDatabase, $horizonDatabase);
        $this->assertNotSame($queueDatabase, $cacheDatabase);
        $this->assertNotSame($horizonDatabase, $cacheDatabase);
    }
}
