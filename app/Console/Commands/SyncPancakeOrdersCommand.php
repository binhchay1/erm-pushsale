<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Pancake\PancakeConnectionResolver;
use App\Services\Pancake\PancakeOrderImportService;
use App\Support\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SyncPancakeOrdersCommand extends Command
{
    protected $signature = 'pancake:sync-orders
        {--company-id= : ID doanh nghiệp cần đồng bộ}
        {--search= : Tìm theo mã đơn, SĐT, tên khách, ghi chú hoặc mã vận đơn}
        {--limit=50 : Số đơn tối đa import trong một lần}
        {--page=1 : Trang Pancake POS cần quét}';

    protected $description = 'Đồng bộ/poll đơn Pancake POS về SaleOps qua Open API.';

    public function handle(
        TenantManager $tenant,
        PancakeConnectionResolver $connections,
        PancakeOrderImportService $importer,
    ): int {
        $companyId = $this->option('company-id')
            ?: Company::query()->orderBy('id')->value('id');

        if (! $companyId) {
            $this->error('Không tìm thấy company để đồng bộ.');

            return self::FAILURE;
        }

        return $tenant->forCompany((int) $companyId, function () use ($connections, $importer) {
            $params = [
                'page' => (int) $this->option('page'),
                'search' => $this->option('search'),
            ];

            $response = $connections->client()->orders($params);
            $orders = Arr::get($response, 'data')
                ?? Arr::get($response, 'orders')
                ?? Arr::get($response, 'items')
                ?? [];

            if (! is_array($orders)) {
                $this->warn('Pancake API không trả danh sách đơn theo shape quen thuộc. Đã in raw response để kiểm tra.');
                $this->line(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $limit = max(1, (int) $this->option('limit'));
            $count = 0;

            foreach (array_slice($orders, 0, $limit) as $order) {
                if (! is_array($order)) {
                    continue;
                }

                $result = $importer->import($order);
                $count++;

                $code = $result['order']?->order_code ?? ('lead#'.$result['lead']->id);
                $this->line("✓ Pancake → {$code}");
            }

            $this->info("Đã đồng bộ {$count} đơn/lead từ Pancake.");

            return self::SUCCESS;
        });
    }
}
