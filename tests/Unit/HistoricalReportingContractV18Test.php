<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HistoricalReportingContractV18Test extends TestCase
{
    public function test_reporting_contract_contains_live_dirty_close_archive_and_integrity_jobs(): void
    {
        $root = dirname(__DIR__, 2);
        $schedule = file_get_contents($root.'/routes/console.php');
        $migration = file_get_contents($root.'/database/migrations/2026_07_15_000000_create_historical_reporting_tables.php');
        $archive = file_get_contents($root.'/app/Services/Reporting/MonthlyArchiveService.php');

        $this->assertStringContainsString('reports:aggregate-daily --queue', $schedule);
        $this->assertStringContainsString('reports:process-dirty --queue', $schedule);
        $this->assertStringContainsString('reports:aggregate-daily yesterday --close --queue', $schedule);
        $this->assertStringContainsString('reports:archive-month --queue', $schedule);
        $this->assertStringContainsString('yearlyOn(1, 3, \'04:30\')', $schedule);
        $this->assertStringContainsString("'driver' => env('REPORTING_ARCHIVE_DRIVER', 'yearly_tables')", file_get_contents($root.'/config/reporting.php'));
        $this->assertStringContainsString('reports:refresh-stale-archives --queue', $schedule);
        $this->assertStringContainsString('reports:prune-snapshots', $schedule);
        $this->assertStringContainsString('reports:verify-facts --days=14', $schedule);

        foreach ([
            'report_daily_closures', 'report_dirty_dates', 'report_daily_lead_facts',
            'report_daily_order_facts', 'report_daily_product_facts',
            'report_daily_cashflow_facts', 'report_daily_inventory_facts',
            'report_query_snapshots', 'analytics_archive_manifests', 'analytics_cold_records',
        ] as $table) {
            $this->assertStringContainsString("Schema::create('{$table}'", $migration);
        }

        $this->assertStringContainsString('copy_chunk_size', file_get_contents($root.'/config/reporting.php'));
        $this->assertStringContainsString('hash_equals($currentSourceChecksum, $archiveChecksum)', $archive);
        $this->assertStringContainsString('whereBetween(\'id\', [$minId, $maxId])', $archive);
    }
}
