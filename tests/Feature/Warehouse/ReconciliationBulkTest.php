<?php

namespace Tests\Feature\Warehouse;

use App\Enums\DeliveryStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Shops\ShopProvisioningService;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReconciliationBulkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0:User,1:Shop,2:Company}
     */
    private function actingShopUser(string $role = User::ROLE_ADMIN): array
    {
        $company = Company::query()->firstOrCreate(
            ['slug' => 'test-co'],
            ['name' => 'Test Co', 'status' => Company::STATUS_ACTIVE, 'plan' => 'pro'],
        );
        app(TenantManager::class)->set($company->id);

        $shop = app(ShopProvisioningService::class)->ensureDefaultShop($company);
        app(TenantManager::class)->setShop($shop->id);

        $user = User::factory()->create([
            'role' => $role,
            'company_id' => $company->id,
            'default_shop_id' => $shop->id,
        ]);
        $shop->users()->syncWithoutDetaching([$user->id]);

        return [$user, $shop, $company];
    }

    private function makeOrder(Shop $shop, Company $company, string $code, string $phone): Order
    {
        return Order::query()->create([
            'company_id' => $company->id,
            'shop_id' => $shop->id,
            'order_code' => $code,
            'customer_name' => 'KH',
            'customer_phone' => $phone,
            'delivery_status' => DeliveryStatus::Delivered->value,
            'reconciliation_status' => 'pending',
            'closed_at' => now(),
            'data_arrived_at' => now(),
            'total' => 100000,
            'amount_to_collect' => 100000,
        ]);
    }

    public function test_inspect_and_update_reconciliation_by_codes(): void
    {
        [$admin, $shop, $company] = $this->actingShopUser();
        $order = $this->makeOrder($shop, $company, 'PS00184600899PS', '0901234599');

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/reconciliation-bulk/inspect', [
                'codes' => $order->order_code,
                'code_type' => 'MHT',
            ])
            ->assertOk()
            ->assertJsonPath('found', 1);

        $this->actingAs($admin)
            ->postJson('/admin/warehouse/orders/reconciliation-bulk/update', [
                'codes' => $order->order_code,
                'code_type' => 'MHT',
                'reconciliation_status' => 'reconciled',
                'note' => 'Đã đối soát',
            ])
            ->assertOk()
            ->assertJsonPath('success_count', 1);

        $this->assertSame('reconciled', $order->fresh()->reconciliation_status);
    }

    public function test_excel_upload_and_apply_reconciliation(): void
    {
        [$admin, $shop, $company] = $this->actingShopUser();
        $order = $this->makeOrder($shop, $company, 'PS00184176999PS', '0901234598');

        $csv = "Mã đơn,Mã giao vận,Trạng thái đối soát,Ghi chú\n{$order->order_code},,reconciled,OK\n";
        $file = UploadedFile::fake()->createWithContent('ttds.csv', $csv);

        $upload = $this->actingAs($admin)
            ->post('/admin/warehouse/orders/reconciliation-bulk/upload', [
                'file' => $file,
                'is_ghtk' => 0,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json();

        $batchId = $upload['batch']['id'];
        $this->assertSame(1, $upload['counts']['total']);

        $this->actingAs($admin)
            ->postJson("/admin/warehouse/orders/reconciliation-bulk/batches/{$batchId}/apply")
            ->assertOk()
            ->assertJsonPath('counts.success', 1);

        $this->assertSame('reconciled', $order->fresh()->reconciliation_status);
    }

    public function test_accounting_workspace_can_update_reconciliation(): void
    {
        [$accountant, $shop, $company] = $this->actingShopUser(User::ROLE_ACCOUNTING);
        $order = $this->makeOrder($shop, $company, 'PS00184177000PS', '0901234597');

        $this->actingAs($accountant)
            ->postJson('/accounting/orders/reconciliation-bulk/update', [
                'codes' => $order->order_code,
                'code_type' => 'MHT',
                'reconciliation_status' => 'reconciled',
            ])
            ->assertOk()
            ->assertJsonPath('success_count', 1);

        $this->assertSame('reconciled', $order->fresh()->reconciliation_status);
    }
}
