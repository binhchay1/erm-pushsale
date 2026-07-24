<?php

namespace Database\Seeders;

use App\Models\Pushsale\ElectronicInvoiceConfig;
use App\Models\User;
use Illuminate\Database\Seeder;

class ElectronicInvoiceConfigSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->first() ?? User::query()->first();
        $rows = [
            [
                'account' => 'ttgroup2.admin',
                'password' => 'demo-secret',
                'invoice_type_code' => '1',
                'tax_code' => '0109990001',
                'invoice_template_code' => '1/001',
                'invoice_series' => 'C26TTA',
                'business_name' => 'CÔNG TY TNHH TT GROUP 2',
                'address' => 'Tầng 8, Tòa nhà Demo, Hà Nội',
                'phone' => '02439990001',
                'fax' => '02439990002',
                'email' => 'ketoan@ttgroup2.vn',
                'bank_name' => 'VCB Hà Nội',
                'bank_account' => '102000000001',
                'is_active' => true,
            ],
            [
                'account' => 'ttgroup2.invoice.backup',
                'password' => 'demo-secret',
                'invoice_type_code' => '1',
                'tax_code' => '0109990002',
                'invoice_template_code' => '1/002',
                'invoice_series' => 'C26TTB',
                'business_name' => 'CÔNG TY TNHH TT GROUP 2 - CHI NHÁNH HCM',
                'address' => 'Quận 1, TP. Hồ Chí Minh',
                'phone' => '02839990001',
                'fax' => '02839990002',
                'email' => 'invoice-hcm@ttgroup2.vn',
                'bank_name' => 'ACB Hồ Chí Minh',
                'bank_account' => '888000000002',
                'is_active' => false,
            ],
        ];

        foreach ($rows as $row) {
            ElectronicInvoiceConfig::query()->updateOrCreate(
                ['account' => $row['account'], 'tax_code' => $row['tax_code']],
                $row + ['created_by_user_id' => $admin?->id, 'updated_by_user_id' => $admin?->id],
            );
        }
    }
}
