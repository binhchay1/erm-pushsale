<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Services\Reports\ReportDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportFilterDataTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_report_range_uses_last_seven_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

        $filter = ReportFilterData::fromRequest(Request::create('/reports'));

        $this->assertSame(ReportDateRange::PRESET_LAST_7_DAYS, $filter->preset);
        $this->assertSame('2026-05-26 00:00:00', $filter->dateFrom?->toDateTimeString());
        $this->assertSame('2026-06-01 23:59:59', $filter->dateTo?->toDateTimeString());
    }

    public function test_report_range_supports_quick_presets(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $today = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'today']));
        $lastThirtyDays = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'last_30_days']));
        $thisMonth = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['preset' => 'this_month']));

        $this->assertSame('2026-06-15 00:00:00', $today->dateFrom?->toDateTimeString());
        $this->assertSame('2026-05-17 00:00:00', $lastThirtyDays->dateFrom?->toDateTimeString());
        $this->assertSame('2026-06-01 00:00:00', $thisMonth->dateFrom?->toDateTimeString());
    }

    public function test_explicit_date_range_becomes_custom_preset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'last_7_days',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $this->assertSame(ReportDateRange::PRESET_CUSTOM, $filter->preset);
        $this->assertSame('2026-05-01 00:00:00', $filter->dateFrom?->toDateTimeString());
        $this->assertSame('2026-05-31 23:59:59', $filter->dateTo?->toDateTimeString());
        $this->assertSame('custom', $filter->toInertia()['preset']);
    }
}
