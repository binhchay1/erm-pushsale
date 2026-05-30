<?php

namespace Database\Seeders;

use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use Illuminate\Database\Seeder;

class LeadIngestionSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['Facebook', 'FB-DEMO-001', LeadIngestionStatus::Processed, 'Nguyễn Văn A', '0912000111', 0],
            ['Facebook', 'FB-DEMO-002', LeadIngestionStatus::Pending, 'Trần Thị B', '0923000222', 0],
            ['TikTok', 'TT-DEMO-002', LeadIngestionStatus::Processed, 'Lê Văn C', '0934000333', 1],
            ['Landing', 'LD-DEMO-001', LeadIngestionStatus::Duplicate, 'Phạm D', '0945000444', 2],
            ['Zalo', 'ZL-DEMO-001', LeadIngestionStatus::Failed, 'Hoàng E', '0956000555', 0],
            ['Google', 'GG-DEMO-001', LeadIngestionStatus::Pending, 'Võ F', '0967000666', 0],
            ['Facebook', 'FB-DEMO-003', LeadIngestionStatus::Processed, 'Trần Minh Anh', '0912345678', 0],
        ];

        foreach ($samples as [$platform, $externalId, $status, $name, $phone, $daysAgo]) {
            LeadIngestion::query()->firstOrCreate(
                ['platform' => $platform, 'external_id' => $externalId],
                [
                    'status' => $status,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'product_interest' => 'Gối mây đan',
                    'utm_source' => strtolower($platform),
                    'utm_campaign' => 'demo-seed',
                    'payload' => ['seed' => true],
                    'created_at' => now()->subDays($daysAgo),
                    'updated_at' => now()->subDays($daysAgo),
                ],
            );
        }
    }
}
