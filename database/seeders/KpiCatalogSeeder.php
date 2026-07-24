<?php

namespace Database\Seeders;

use App\Models\Pushsale\KpiCatalogItem;
use Illuminate\Database\Seeder;

class KpiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'marketing' => [
                ['KPI Marketing chuẩn', 2200000, 700, 34, 16000000],
                ['KPI Marketing tăng trưởng', 3200000, 950, 48, 24000000],
                ['KPI Marketing tối ưu ngân sách', 1600000, 520, 26, 13000000],
                ['KPI Marketing test sản phẩm mới', 2500000, 780, 38, 18000000],
                ['KPI Marketing giữ nhịp lead', 1400000, 450, 22, 10500000],
            ],
            'sales' => [
                ['KPI Sale chuẩn', 12, 5, 6, 3, 12000000],
                ['KPI Sale tăng tốc', 16, 7, 8, 4, 18000000],
                ['KPI Sale chăm sóc lại', 8, 3, 14, 6, 15000000],
                ['KPI Sale hàng nóng', 20, 9, 5, 2, 22000000],
                ['KPI Sale giữ chân khách cũ', 6, 2, 18, 8, 17000000],
            ],
        ];

        foreach ($definitions['marketing'] as $sort => [$name, $budget, $clicks, $contacts, $revenue]) {
            KpiCatalogItem::query()->updateOrCreate(
                ['position_key' => 'marketing', 'kpi_name' => $name],
                [
                    'daily_budget' => $budget,
                    'daily_clicks' => $clicks,
                    'daily_contacts' => $contacts,
                    'daily_revenue' => $revenue,
                    'daily_new_contacts' => 0,
                    'daily_new_closed' => 0,
                    'daily_old_contacts' => 0,
                    'daily_old_closed' => 0,
                    'is_active' => true,
                    'sort_order' => $sort + 1,
                ]
            );
        }

        foreach ($definitions['sales'] as $sort => [$name, $newContacts, $newClosed, $oldContacts, $oldClosed, $revenue]) {
            KpiCatalogItem::query()->updateOrCreate(
                ['position_key' => 'sales', 'kpi_name' => $name],
                [
                    'daily_budget' => 0,
                    'daily_clicks' => 0,
                    'daily_contacts' => $newContacts + $oldContacts,
                    'daily_revenue' => $revenue,
                    'daily_new_contacts' => $newContacts,
                    'daily_new_closed' => $newClosed,
                    'daily_old_contacts' => $oldContacts,
                    'daily_old_closed' => $oldClosed,
                    'is_active' => true,
                    'sort_order' => $sort + 1,
                ]
            );
        }

        $this->command?->info('Đã tạo danh mục KPI demo cho menu 7.1.3.');
    }
}
