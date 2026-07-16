<?php

namespace App\Console\Commands;

use App\Services\BusinessFlow\BusinessFlowContractService;
use Illuminate\Console\Command;

class AuditBusinessFlowContractCommand extends Command
{
    protected $signature = 'audit:business-flow {--company= : Chỉ audit một company_id} {--json : Xuất JSON để CI đọc}';

    protected $description = 'Audit cấu hình end-to-end của luồng ERM Pushsale: nhân sự, sản phẩm, landing, chia số, kho/giao vận.';

    public function handle(BusinessFlowContractService $service): int
    {
        $company = $this->option('company');
        $result = $service->audit($company !== null && $company !== '' ? (int) $company : null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Business flow audit');
        $this->line('Companies: '.$result['totals']['companies'].' | Issues: '.$result['totals']['issues'].' | Warnings: '.$result['totals']['warnings']);

        foreach ($result['companies'] as $companyResult) {
            $this->newLine();
            $this->line(($companyResult['ok'] ? '✅' : '❌').' '.$companyResult['company_name'].' (#'.$companyResult['company_id'].')');

            foreach ($companyResult['issues'] as $issue) {
                $this->error('  - '.$issue);
            }
            foreach ($companyResult['warnings'] as $warning) {
                $this->warn('  - '.$warning);
            }
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
