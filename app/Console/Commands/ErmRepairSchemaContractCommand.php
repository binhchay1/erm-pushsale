<?php

namespace App\Console\Commands;

use App\Support\RuntimeSchemaContract;
use Illuminate\Console\Command;

class ErmRepairSchemaContractCommand extends Command
{
    protected $signature = 'erm:repair-schema-contract {--json : Print machine-readable output}';

    protected $description = 'Repair non-destructive schema columns required by ERM Pushsale seeders, audits, and pages.';

    public function handle(RuntimeSchemaContract $contract): int
    {
        $changes = $contract->ensure();

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => true,
                'changed' => $changes,
                'changed_count' => count($changes),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($changes === []) {
            $this->info('Schema contract OK, không cần sửa cột nào.');
        } else {
            $this->info('Đã repair schema contract: '.implode(', ', $changes));
        }

        return self::SUCCESS;
    }
}
