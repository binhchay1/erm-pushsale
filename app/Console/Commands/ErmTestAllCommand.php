<?php

namespace App\Console\Commands;

use App\Services\Testing\StagingTestService;
use Database\Seeders\FullBusinessDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class ErmTestAllCommand extends Command
{
    protected $signature = 'erm:test-all
                            {--fresh : DESTRUCTIVE - run migrate:fresh before seeding}
                            {--seed : Run migrate + full ERM demo seed}
                            {--phpunit : Run Laravel/PHPUnit feature and unit tests}
                            {--filter= : Pass a PHPUnit filter when --phpunit is enabled}
                            {--build : Run pnpm build}
                            {--audit : Run business/report audits}
                            {--landing-flow : Run real Landing Connection main+upsell flow}
                            {--flow : Run legacy campaign/order/shipping E2E flow}
                            {--pages : Scan configured/staging pages for 500 errors}
                            {--all-pages : With --pages, scan all GET/navigation pages}
                            {--route-smoke : Scan all safe GET/view routes, including dynamic routes with sampled IDs}
                            {--no-route-query-noise : With --route-smoke, do not append safe query params}
                            {--smoke-limit=20 : Number of failed page/route rows printed in compact smoke summaries}
                            {--full-json : With --json, include full verbose result payloads instead of compact QA summary}
                            {--base-url= : Override APP_URL/staging base URL for HTTP page/flow scans}
                            {--phone= : Phone number used by flow checks}
                            {--quick : Seed + critical backend audits only}
                            {--json : Print machine-readable JSON summary}';

    protected $description = 'One command for ERM Pushsale QA: full demo seed, backend tests, flow tests, page smoke, and build check.';

    /** @var list<array{name:string, ok:bool, ms:int, output?:string, error?:string, data?:mixed}> */
    private array $results = [];

    public function handle(StagingTestService $staging): int
    {
        $this->configureBaseUrl();

        $explicit = collect([
            'fresh', 'seed', 'phpunit', 'build', 'audit', 'landing-flow', 'flow', 'pages', 'all-pages', 'route-smoke', 'quick',
        ])->contains(fn (string $option): bool => (bool) $this->option($option))
            || trim((string) $this->option('filter')) !== '';

        // Không truyền option nào = bộ kiểm tra chuẩn dùng sau mỗi lần sửa luồng.
        $standard = ! $explicit;
        $quick = (bool) $this->option('quick');

        $runSeed = $standard || $quick || (bool) $this->option('seed') || (bool) $this->option('fresh');
        $runPhpunit = $standard || (bool) $this->option('phpunit') || trim((string) $this->option('filter')) !== '';
        $runAudit = $standard || $quick || (bool) $this->option('audit');
        $runLandingFlow = $standard || (bool) $this->option('landing-flow');
        $runFlow = $standard || (bool) $this->option('flow');
        $runPages = (bool) $this->option('pages') || (bool) $this->option('all-pages');
        $runRouteSmoke = $standard || (bool) $this->option('route-smoke');
        $runBuild = (bool) $this->option('build');

        if ($quick) {
            $runPhpunit = false;
            $runLandingFlow = false;
            $runFlow = false;
            $runPages = false;
            $runRouteSmoke = false;
            $runBuild = false;
        }

        $this->line('<fg=cyan>=== ERM PUSHSALE FULL QA ===</>');
        $this->line('Mode: '.($standard ? 'standard' : ($quick ? 'quick' : 'custom')));
        $this->newLine();

        $this->record('schema:contract-repair', fn () => $this->artisan('erm:repair-schema-contract'));
        $this->record('health', fn () => $this->healthPayload($staging));

        if ((bool) $this->option('fresh')) {
            $this->record('migrate:fresh', fn () => $this->artisan('migrate:fresh', ['--force' => true]));
        } elseif ($runSeed) {
            $this->record('migrate', fn () => $this->artisan('migrate', ['--force' => true]));
        }

        if ($runSeed) {
            $this->record('seed:full-business-demo', fn () => $this->artisan('db:seed', [
                '--class' => FullBusinessDemoSeeder::class,
                '--force' => true,
            ]));
            $this->record('reports:aggregate-today', fn () => $this->artisan('reports:aggregate-daily', ['date' => 'today']));
            $this->record('reports:verify-facts', fn () => $this->artisan('reports:verify-facts', ['--days' => 14, '--repair' => true]));
        }

        if ($runBuild) {
            $this->record('frontend:pnpm-build', fn () => $this->process('pnpm build', 300));
        }

        if ($runPhpunit) {
            $this->record('phpunit', fn () => $this->runPhpunit());
        }

        if ($runAudit) {
            $this->record('audit:business-flow', fn () => $this->artisan('audit:business-flow', ['--json' => true]));
            $this->record('audit:business-data', fn () => $this->artisan('data:audit-business'));
            $this->record('audit:reports', fn () => $this->artisan('audit:reports', ['--run-flow' => true]));
        }

        $phone = trim((string) $this->option('phone')) ?: null;

        if ($runLandingFlow) {
            $this->record('flow:landing-connection', fn () => $this->jsonCheck($staging->landingConnectionFlow($phone)));
        }

        if ($runFlow) {
            $this->record('flow:e2e-order-shipping', fn () => $this->jsonCheck($staging->fullFlow($phone)));
        }

        if ($runPages) {
            $this->record('pages:scan', fn () => $this->jsonCheck($staging->scanPages(null, (bool) $this->option('all-pages'))));
        }

        if ($runRouteSmoke) {
            $queryNoise = ! (bool) $this->option('no-route-query-noise');
            $smokeLimit = max(1, min(100, (int) $this->option('smoke-limit')));
            $this->record('routes:view-smoke', fn () => $this->jsonCheck($staging->scanRoutableViewRoutes($queryNoise, 320, $smokeLimit)));
        }

        $failed = collect($this->results)->filter(fn (array $row): bool => ! $row['ok'])->values();
        $ok = $failed->isEmpty();

        $this->newLine();
        $this->table(
            ['Step', 'OK', 'ms'],
            array_map(fn (array $row): array => [$row['name'], $row['ok'] ? 'OK' : 'FAIL', $row['ms']], $this->results),
        );

        if ((bool) $this->option('json')) {
            $payload = [
                'ok' => $ok,
                'generated_at' => now()->toISOString(),
                'base_url' => config('staging_test.base_url') ?: config('app.url'),
                'failed' => $failed->pluck('name')->all(),
                'results' => (bool) $this->option('full-json')
                    ? $this->results
                    : array_map(fn (array $row): array => $this->compactResultForJson($row), $this->results),
            ];

            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        if (! $ok) {
            $this->error('ERM QA có bước lỗi: '.$failed->pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $this->info('ERM QA hoàn tất OK.');

        return self::SUCCESS;
    }

    private function configureBaseUrl(): void
    {
        $baseUrl = trim((string) $this->option('base-url'));
        if ($baseUrl === '') {
            $baseUrl = trim((string) config('staging_test.base_url')) ?: trim((string) config('app.url'));
        }

        if ($baseUrl !== '') {
            config(['staging_test.base_url' => rtrim($baseUrl, '/')]);
        }
    }

    /** @param callable(): array{ok:bool, ms?:int, output?:string, error?:string, data?:mixed} $callback */
    private function record(string $name, callable $callback): void
    {
        $this->line("<fg=blue>→ {$name}</>");
        $started = microtime(true);

        try {
            $result = $callback();
            $result['ms'] = $result['ms'] ?? (int) round((microtime(true) - $started) * 1000);
        } catch (\Throwable $e) {
            $result = [
                'ok' => false,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'error' => $e->getMessage(),
            ];
        }

        $ok = (bool) ($result['ok'] ?? false);
        if ($ok) {
            $this->line("<fg=green>✓ {$name}</> ({$result['ms']}ms)");
        } else {
            $this->line("<fg=red>✗ {$name}</> ({$result['ms']}ms)");
            if (! empty($result['error'])) {
                $this->warn((string) $result['error']);
            }
            if (! empty($result['output'])) {
                $this->line(mb_substr((string) $result['output'], -4000));
            }
        }

        $this->results[] = [
            'name' => $name,
            'ok' => $ok,
            'ms' => (int) $result['ms'],
            'output' => isset($result['output']) ? mb_substr((string) $result['output'], -8000) : null,
            'error' => $result['error'] ?? null,
            'data' => $result['data'] ?? null,
        ];
    }

    /**
     * @param array{name:string, ok:bool, ms:int, output?:?string, error?:?string, data?:mixed} $row
     * @return array<string, mixed>
     */
    private function compactResultForJson(array $row): array
    {
        $compact = [
            'name' => $row['name'],
            'ok' => (bool) $row['ok'],
            'ms' => (int) $row['ms'],
        ];

        if (! empty($row['error'])) {
            $compact['error'] = $row['error'];
        }

        $data = $row['data'] ?? null;
        if (is_array($data)) {
            foreach (['total', 'failed', 'passed', 'base_url', 'internal_kernel', 'route_smoke', 'query_noise'] as $key) {
                if (array_key_exists($key, $data)) {
                    $compact[$key] = $data[$key];
                }
            }

            if (isset($data['summary']) && is_array($data['summary'])) {
                $compact['summary'] = $data['summary'];
            } elseif (isset($data['counts']) && is_array($data['counts'])) {
                $compact['counts'] = $data['counts'];
            } elseif (isset($data['checks']) && is_array($data['checks'])) {
                $compact['checks'] = array_map(static function ($check) {
                    if (! is_array($check)) {
                        return $check;
                    }

                    return array_intersect_key($check, array_flip(['ok', 'version', 'env', 'connection', 'store', 'default', 'count', 'error']));
                }, $data['checks']);
            }
        }

        if (! (bool) $row['ok'] && ! isset($compact['summary']) && ! empty($row['output'])) {
            $compact['output_tail'] = mb_substr((string) $row['output'], -2000);
        }

        return $compact;
    }

    /** @return array{ok:bool, ms:int, output:string} */
    private function artisan(string $command, array $parameters = []): array
    {
        $started = microtime(true);
        $exitCode = Artisan::call($command, $parameters);

        return [
            'ok' => $exitCode === 0,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'output' => trim(Artisan::output()),
        ];
    }

    /** @return array{ok:bool, ms:int, output:string, error?:string} */
    private function runPhpunit(): array
    {
        $filter = trim((string) $this->option('filter'));
        $params = [];
        if ($filter !== '') {
            $params['--filter'] = $filter;
        }

        if (array_key_exists('test', Artisan::all())) {
            return $this->artisan('test', $params);
        }

        $phpunit = base_path('vendor/bin/phpunit');
        if (is_file($phpunit)) {
            $command = 'php '.escapeshellarg($phpunit);
            if ($filter !== '') {
                $command .= ' --filter '.escapeshellarg($filter);
            }

            return $this->process($command, 300);
        }

        return [
            'ok' => true,
            'ms' => 0,
            'output' => 'SKIP: php artisan test/vendor/bin/phpunit không có trên server này. Production deploy đang composer install --no-dev nên PHPUnit không được cài. Backend smoke/flow/audit vẫn chạy bằng erm:test-all.',
        ];
    }

    /** @return array{ok:bool, ms:int, output:string, error?:string} */
    private function process(string $command, int $timeout = 120): array
    {
        $started = microtime(true);
        $process = Process::fromShellCommandline($command, base_path(), timeout: $timeout);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return [
            'ok' => $process->isSuccessful(),
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
            'error' => $process->isSuccessful() ? null : 'Process exit code '.$process->getExitCode(),
        ];
    }

    /** @return array{ok:bool, data:array<string, mixed>, output:string} */
    private function jsonCheck(array $payload): array
    {
        return [
            'ok' => (bool) ($payload['ok'] ?? false),
            'data' => $payload,
            'output' => $this->compactPayloadForConsole($payload),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function compactPayloadForConsole(array $payload): string
    {
        if (! empty($payload['summary_text'])) {
            return (string) $payload['summary_text'];
        }

        if (isset($payload['summary']) && is_array($payload['summary'])) {
            return json_encode([
                'ok' => (bool) ($payload['ok'] ?? false),
                'total' => $payload['total'] ?? null,
                'passed' => $payload['passed'] ?? null,
                'failed' => $payload['failed'] ?? null,
                'summary' => $payload['summary'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    /** @return array{ok:bool, data:array<string, mixed>, output:string} */
    private function healthPayload(StagingTestService $staging): array
    {
        return $this->jsonCheck($staging->health());
    }
}
