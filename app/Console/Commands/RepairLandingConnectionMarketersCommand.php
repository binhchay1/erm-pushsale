<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use Illuminate\Console\Command;

final class RepairLandingConnectionMarketersCommand extends Command
{
    protected $signature = 'landing-connections:repair-marketers {--dry-run : Chỉ liệt kê, không ghi DB}';

    protected $description = 'Sửa marketer_user_id sai (gán về người tạo nếu creator là Marketing và khác marketer hiện tại)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;

        LandingConnection::query()
            ->with(['createdBy:id,role'])
            ->whereNotNull('created_by_user_id')
            ->orderBy('id')
            ->chunkById(100, function ($connections) use ($dryRun, &$fixed): void {
                foreach ($connections as $connection) {
                    $creator = $connection->createdBy;
                    if (! $creator || $creator->role !== UserRole::Marketing) {
                        continue;
                    }

                    if ((int) $connection->marketer_user_id === (int) $creator->id) {
                        continue;
                    }

                    $this->line(sprintf(
                        '#%d "%s": marketer %d → %d (%s)',
                        $connection->id,
                        $connection->name,
                        $connection->marketer_user_id,
                        $creator->id,
                        $creator->name,
                    ));

                    if (! $dryRun) {
                        $connection->forceFill([
                            'marketer_user_id' => $creator->id,
                            'updated_by_user_id' => $creator->id,
                        ])->save();
                    }

                    $fixed++;
                }
            });

        $this->info($dryRun
            ? "Sẽ sửa {$fixed} kết nối landing (chạy lại không --dry-run để áp dụng)."
            : "Đã sửa {$fixed} kết nối landing.");

        return self::SUCCESS;
    }
}
