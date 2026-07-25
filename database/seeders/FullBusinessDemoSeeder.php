<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LandingConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Scopes\TenantScope;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\RuntimeSchemaContract;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Bộ seed đầy đủ cho QA giao diện + QA backend.
 *
 * Seed này cố tình chạy qua các seeder nghiệp vụ thật thay vì nhét rows mẫu rời rạc,
 * để các trang menu dùng cùng một nguồn dữ liệu với backend flow:
 * account -> sản phẩm/kho -> marketing/landing -> lead -> sale -> kho -> giao vận -> đối soát -> báo cáo.
 */
class FullBusinessDemoSeeder extends Seeder
{
    /** @var list<class-string<Seeder>> */
    private const TENANT_SEEDERS = [
        AccountSeeder::class,
        SecurityAuditDemoSeeder::class,
        CatalogSeeder::class,
        ProductTaxonomyDemoSeeder::class,
        FacebookPageMappingSeeder::class,
        DiscountCodRuleSeeder::class,
        InventorySeeder::class,
        MarketingCampaignSeeder::class,
        ManualMarketingContactSeeder::class,
        SalesPipelineSeeder::class,
        PhoneBlacklistSeeder::class,
        KpiCatalogSeeder::class,
        RevenueBonusRuleSeeder::class,
        ElectronicInvoiceConfigSeeder::class,
        BusinessKpiPlanSeeder::class,
        AnnualBusinessPlanSeeder::class,
        DataFilterHistorySeeder::class,
        LandingFlowSeeder::class,
        ShippingEventSeeder::class,
        WarehouseDeliveryHandoverSeeder::class,
        EcommerceDemoSeeder::class,
        PowerDashboardSeeder::class,
        HourlyStatsSeeder::class,
        CustomerInteractionDemoSeeder::class,
        DemoNotificationSeeder::class,
    ];

    public function run(): void
    {
        app(RuntimeSchemaContract::class)->ensure();

        $this->call(DemoResetSeeder::class);

        $company = CompanyProvisioningService::internalCompany();

        app(TenantManager::class)->forCompany($company->id, function () use ($company): void {
            $this->call(self::TENANT_SEEDERS);
            $this->finalizeOwner($company);
            $this->printSummary();
        });
    }

    private function finalizeOwner(Company $company): void
    {
        $owner = User::query()->withoutGlobalScope(TenantScope::class)
            ->where('email', 'superadmin@saleops.local')
            ->first();

        if (! $owner) {
            return;
        }

        $owner->forceFill([
            'is_owner' => true,
            'is_platform_admin' => true,
            'company_id' => $company->id,
        ])->save();

        $company->forceFill([
            'owner_user_id' => $owner->id,
            'contact_email' => 'superadmin@saleops.local',
        ])->save();
    }

    private function printSummary(): void
    {
        if (! $this->command) {
            return;
        }

        $counts = [
            'companies' => [Company::class, 'companies'],
            'users' => [User::class, 'users'],
            'products' => [Product::class, 'products'],
            'warehouses' => [Warehouse::class, 'warehouses'],
            'inventories' => [WarehouseInventory::class, 'warehouse_inventories'],
            'inventory_movements' => [WarehouseInventoryMovement::class, 'warehouse_inventory_movements'],
            'warehouse_vouchers' => [WarehouseVoucher::class, 'warehouse_vouchers'],
            'marketing_sources' => [MarketingSource::class, 'marketing_sources'],
            'landing_connections' => [LandingConnection::class, 'landing_connections'],
            'leads' => [LeadIngestion::class, 'lead_ingestions'],
            'orders' => [Order::class, 'orders'],
            'shipments' => [Shipment::class, 'shipments'],
        ];

        $rows = [];
        foreach ($counts as $label => [$model, $table]) {
            $rows[] = [
                $label,
                Schema::hasTable($table) ? $this->countModel($model) : 'n/a',
            ];
        }

        $this->command->newLine();
        $this->command->info('Tổng hợp dữ liệu demo ERM Pushsale:');
        $this->command->table(['Nhóm dữ liệu', 'Số bản ghi'], $rows);
    }

    /** @param class-string $model */
    private function countModel(string $model): int
    {
        if (method_exists($model, 'scopeWithoutTenant')) {
            return $model::withoutTenant()->count();
        }

        return $model::query()->withoutGlobalScope(TenantScope::class)->count();
    }
}
