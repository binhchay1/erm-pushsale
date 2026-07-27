<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\User;
use Illuminate\Console\Command;

final class RepairLandingConnectionMarketersCommand extends Command
{
    protected $signature = 'landing-connections:repair-marketers {--dry-run : Chỉ liệt kê, không ghi DB}';

    protected $description = 'Đồng bộ người tạo/marketing của landing cũ theo dữ liệu cập nhật đầu tiên';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;

        LandingConnection::query()
            ->with(['createdBy:id,name,role', 'updatedBy:id,name,role'])
            ->orderBy('id')
            ->chunkById(100, function ($connections) use ($dryRun, &$fixed): void {
                foreach ($connections as $connection) {
                    $creator = $connection->createdBy;
                    $firstUpdater = $connection->updatedBy;

                    // Dữ liệu cũ có thể thiếu created_by_user_id.
                    // Theo business hiện tại: lấy người cập nhật đầu tiên (updated_by) làm người tạo.
                    $resolvedCreator = $creator ?: $firstUpdater;
                    if (! $resolvedCreator instanceof User) {
                        continue;
                    }

                    // Ưu tiên giữ creator role marketing/admin cho cột Marketing.
                    if (! in_array($resolvedCreator->role, [UserRole::Marketing, UserRole::Admin], true)) {
                        continue;
                    }

                    $needsCreatorFix = (int) ($connection->created_by_user_id ?? 0) !== (int) $resolvedCreator->id;
                    $needsMarketerFix = (int) ($connection->marketer_user_id ?? 0) !== (int) $resolvedCreator->id;
                    if (! $needsCreatorFix && ! $needsMarketerFix) {
                        continue;
                    }

                    $this->line(sprintf(
                        '#%d "%s": creator %s -> %d, marketer %d -> %d (%s)',
                        $connection->id,
                        $connection->name,
                        (string) ($connection->created_by_user_id ?? 'null'),
                        $resolvedCreator->id,
                        (int) ($connection->marketer_user_id ?? 0),
                        $resolvedCreator->id,
                        $resolvedCreator->name,
                    ));

                    if (! $dryRun) {
                        $connection->forceFill([
                            'created_by_user_id' => $resolvedCreator->id,
                            'marketer_user_id' => $resolvedCreator->id,
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
