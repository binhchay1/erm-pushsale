<?php

namespace App\Console\Commands;

use App\Services\Testing\StagingTestService;
use Illuminate\Console\Command;

class StagingSmokeTestCommand extends Command
{
    protected $signature = 'staging:smoke
                            {--bootstrap : Seed demo data before testing pages}
                            {--reset : Reset demo flow data when bootstrapping}
                            {--flow : Run one legacy campaign full flow}
                            {--landing-flow : Run one real Landing Connection main+upsell flow}
                            {--pages : Scan configured web pages for 500 errors}
                            {--campaigns=2 : Demo campaigns when bootstrapping}
                            {--per-campaign=8 : Demo leads per campaign when bootstrapping}';

    protected $description = 'Run staging QA setup and smoke checks for the ERM Pushsale deployment.';

    public function handle(StagingTestService $service): int
    {
        $failed = false;

        $this->info('=== STAGING HEALTH ===');
        $health = $service->health();
        $this->line(json_encode($health['checks'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $failed = $failed || ! (bool) $health['ok'];

        if ($this->option('bootstrap')) {
            $this->info('=== BOOTSTRAP DEMO DATA ===');
            $bootstrap = $service->bootstrapDemo(
                reset: (bool) $this->option('reset'),
                campaigns: (int) $this->option('campaigns'),
                perCampaign: (int) $this->option('per-campaign'),
            );
            $this->line(json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $failed = $failed || ! (bool) $bootstrap['ok'];
        }

        if ($this->option('landing-flow')) {
            $this->info('=== LANDING CONNECTION FLOW ===');
            $landingFlow = $service->landingConnectionFlow();
            $this->line(json_encode($landingFlow, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $failed = $failed || ! (bool) $landingFlow['ok'];
        }

        if ($this->option('flow')) {
            $this->info('=== FULL FLOW ===');
            $flow = $service->fullFlow();
            $this->line(json_encode($flow, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $failed = $failed || ! (bool) $flow['ok'];
        }

        if ($this->option('pages') || (! $this->option('bootstrap') && ! $this->option('flow') && ! $this->option('landing-flow'))) {
            $this->info('=== PAGE SCAN ===');
            $scan = $service->scanPages();
            $this->table(['OK', 'HTTP', 'ms', 'URL', 'Title/Error'], array_map(static fn ($row) => [
                $row['ok'] ? 'OK' : 'FAIL',
                $row['status'],
                $row['ms'],
                $row['url'],
                $row['title'] ?: $row['error_hint'],
            ], $scan['results']));
            $failed = $failed || ! (bool) $scan['ok'];
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
