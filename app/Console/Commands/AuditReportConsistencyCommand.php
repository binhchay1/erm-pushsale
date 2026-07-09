<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\ReportConsistencyAuditService;
use Illuminate\Console\Command;

class AuditReportConsistencyCommand extends Command
{
    protected $signature = 'audit:report-consistency {--role= : Chỉ audit một role cụ thể}';

    protected $description = 'So khớp dashboard/report với dữ liệu bản ghi gốc Order/LeadIngestion';

    public function handle(ReportConsistencyAuditService $audit): int
    {
        $role = $this->option('role');
        $actor = null;

        if ($role) {
            $enum = UserRole::tryFrom((string) $role);
            if (! $enum) {
                $this->error('Role không hợp lệ.');

                return self::FAILURE;
            }
            $actor = User::query()->where('role', $enum)->orderBy('id')->first();
            if (! $actor) {
                $this->error('Không tìm thấy user đại diện cho role '.$role);

                return self::FAILURE;
            }
        }

        $result = $audit->snapshot($actor);

        $this->info('Report consistency audit: '.strtoupper($result['status'] ?? 'unknown'));
        $this->line('Khoảng ngày: '.(($result['date_range']['from'] ?? '?').' → '.($result['date_range']['to'] ?? '?')));

        $this->table(
            ['Role', 'Report', 'Expected', 'Actual', 'Diff', 'Status', 'Chi tiết'],
            collect($result['rows'] ?? [])->map(fn ($row) => [
                $row['role'],
                $row['report'],
                $row['expected'],
                $row['actual'],
                $row['diff'],
                strtoupper($row['status']),
                $row['detail'],
            ])->all(),
        );

        return (($result['failed'] ?? 0) > 0) ? self::FAILURE : self::SUCCESS;
    }
}
