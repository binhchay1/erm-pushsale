<?php

namespace App\Console\Commands;

use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use App\Models\PancakeSyncRecord;
use App\Models\PancakeUserMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PancakeDoctorCommand extends Command
{
    protected $signature = 'pancake:doctor {--json : Print compact JSON for copy/paste}';

    protected $description = 'Kiểm tra nhanh cấu hình Pancake: connection, webhook URL, mapping agent, sync records và queue.';

    public function handle(): int
    {
        $report = [
            'ok' => true,
            'connection' => null,
            'mappings' => 0,
            'sync_records' => 0,
            'queues' => [
                'orders' => config('saleops.queues.pancake_orders', 'pancake-orders'),
                'chat' => config('saleops.queues.pancake_chat_sync', 'pancake-chat'),
                'broadcasts' => config('saleops.queues.pancake_chat_broadcasts', 'pancake-chat-broadcasts'),
            ],
            'warnings' => [],
        ];

        if (! Schema::hasTable('integration_connections')) {
            $report['ok'] = false;
            $report['warnings'][] = 'Missing table: integration_connections';
            return $this->printReport($report);
        }

        $connection = IntegrationConnection::query()
            ->where('platform', IntegrationPlatform::Pancake->value)
            ->first();

        if (! $connection) {
            $report['ok'] = false;
            $report['warnings'][] = 'Chưa có kết nối Pancake. Tạo/cấu hình ở menu kết nối tích hợp trước khi nhận webhook.';
        } else {
            $credentials = $connection->credentials ?? [];
            $report['connection'] = [
                'id' => $connection->id,
                'enabled' => (bool) $connection->is_enabled,
                'webhook_url' => $connection->webhookUrl(),
                'has_token' => filled($connection->webhook_token),
                'has_page_token' => filled($credentials['page_access_token'] ?? null),
                'has_shop_id' => filled($credentials['shop_id'] ?? null),
            ];

            if (! $connection->is_enabled) {
                $report['warnings'][] = 'Kết nối Pancake đang tắt.';
            }
            if (! filled($credentials['page_access_token'] ?? null)) {
                $report['warnings'][] = 'Thiếu page_access_token nên tab Pancake chỉ hiển thị cache/webhook, không gửi trực tiếp được.';
            }
        }

        if (Schema::hasTable('pancake_user_mappings')) {
            $report['mappings'] = PancakeUserMapping::query()->where('is_active', true)->count();
            if ($report['mappings'] === 0) {
                $report['warnings'][] = 'Chưa có mapping agent Pancake -> user nội bộ. Dùng php artisan pancake:map-user để map.';
            }
        } else {
            $report['warnings'][] = 'Missing table: pancake_user_mappings';
        }

        if (Schema::hasTable('pancake_sync_records')) {
            $report['sync_records'] = PancakeSyncRecord::query()->count();
        }

        if ($report['warnings']) {
            $report['ok'] = false;
        }

        return $this->printReport($report);
    }

    protected function printReport(array $report): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            return $report['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('PANCAKE DOCTOR');
        $this->line('ok='.($report['ok'] ? 'true' : 'false'));
        $this->line('connection='.json_encode($report['connection'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line('mappings='.$report['mappings'].' sync_records='.$report['sync_records']);
        $this->line('queues='.json_encode($report['queues'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($report['warnings'] as $warning) {
            $this->warn('- '.$warning);
        }

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
