<?php

namespace Tests\Feature\Warehouse;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Pushsale\PushsalePageService;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WarehouseVoucherVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_staff_only_sees_own_vouchers_on_list(): void
    {
        [$warehouse, $product] = $this->seedWarehouseContext();

        $staffA = $this->user('wh-a@test.local', UserRole::Warehouse);
        $staffB = $this->user('wh-b@test.local', UserRole::Warehouse);
        $admin = $this->user('wh-admin@test.local', UserRole::Admin);

        $this->createDraft($staffA, $warehouse, $product, 'PNK-A-001');
        $this->createDraft($staffB, $warehouse, $product, 'PNK-B-001');

        $this->actingAs($staffA);
        $rowsA = collect(app(PushsalePageService::class)->rows('5.3.2', Request::create('/admin/warehouse/vouchers', 'GET'))['data'])
            ->pluck('voucher_code')
            ->all();
        $this->assertSame(['PNK-A-001'], $rowsA);

        $this->actingAs($admin);
        $rowsAdmin = collect(app(PushsalePageService::class)->rows('5.3.2', Request::create('/admin/warehouse/vouchers', 'GET'))['data'])
            ->pluck('voucher_code')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['PNK-A-001', 'PNK-B-001'], $rowsAdmin);
    }

    public function test_warehouse_staff_cannot_open_other_users_voucher(): void
    {
        [$warehouse, $product] = $this->seedWarehouseContext();
        $staffA = $this->user('wh-a2@test.local', UserRole::Warehouse);
        $staffB = $this->user('wh-b2@test.local', UserRole::Warehouse);
        $voucherId = $this->createDraft($staffA, $warehouse, $product, 'PNK-LOCK-001');

        $this->actingAs($staffB)
            ->get('/admin/warehouse/vouchers/entry?id='.$voucherId)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Warehouse/VoucherEntry')
                ->where('voucher', null)
                ->where('pageRuntimeError', fn ($msg) => filled($msg)));
    }

    public function test_team_leader_sees_team_member_vouchers(): void
    {
        [$warehouse, $product] = $this->seedWarehouseContext();
        $team = Team::query()->create([
            'name' => 'Team Kho A',
            'type' => 'warehouse',
        ]);
        $leader = $this->user('wh-lead@test.local', UserRole::Warehouse, [
            'is_team_leader' => true,
            'team_id' => $team->id,
        ]);
        $member = $this->user('wh-member@test.local', UserRole::Warehouse, [
            'team_id' => $team->id,
        ]);
        $outsider = $this->user('wh-out@test.local', UserRole::Warehouse);

        $this->createDraft($member, $warehouse, $product, 'PNK-TEAM-001');
        $this->createDraft($outsider, $warehouse, $product, 'PNK-OUT-001');

        $this->actingAs($leader);
        $codes = collect(app(PushsalePageService::class)->rows('5.3.2', Request::create('/admin/warehouse/vouchers', 'GET'))['data'])
            ->pluck('voucher_code')
            ->all();

        $this->assertContains('PNK-TEAM-001', $codes);
        $this->assertNotContains('PNK-OUT-001', $codes);
    }

    public function test_draft_then_complete_inbound_increases_stock(): void
    {
        [$warehouse, $product] = $this->seedWarehouseContext();
        $staff = $this->user('wh-stock@test.local', UserRole::Warehouse);

        $create = $this->actingAs($staff)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => 'PNK-STOCK-001',
                'type' => 'inbound',
                'document_date' => now()->toDateString(),
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity' => 8,
                    'unit_cost' => 50_000,
                ]],
            ],
        ])->assertCreated();

        $voucherId = (int) $create->json('voucher.id');
        $this->assertSame('draft', $create->json('voucher.status'));
        $this->assertSame(0, (int) WarehouseInventory::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('stock_quantity'));

        $this->actingAs($staff)
            ->postJson("/admin/warehouse/vouchers/entry/records/{$voucherId}/complete")
            ->assertOk()
            ->assertJsonPath('voucher.status', 'confirmed');

        $this->assertSame(8, (int) WarehouseInventory::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('stock_quantity'));
        $this->assertSame('confirmed', WarehouseVoucher::query()->findOrFail($voucherId)->status);
    }

    /** @return array{0:Warehouse,1:Product} */
    private function seedWarehouseContext(): array
    {
        $warehouse = Warehouse::query()->create([
            'name' => 'Kho visibility',
            'code' => 'VIS',
        ]);
        $product = Product::query()->create([
            'name' => 'SP visibility',
            'sku' => 'VIS-001',
            'unit_price' => 100_000,
            'cost_price' => 40_000,
            'is_active' => true,
        ]);

        return [$warehouse, $product];
    }

    /** @param  array<string, mixed>  $extra */
    private function user(string $email, UserRole $role, array $extra = []): User
    {
        return User::query()->create(array_merge([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'company_id' => app(TenantManager::class)->id(),
            'is_active' => true,
        ], $extra));
    }

    private function createDraft(User $actor, Warehouse $warehouse, Product $product, string $code): int
    {
        $response = $this->actingAs($actor)->postJson('/admin/warehouse/vouchers/entry/records', [
            'payload' => [
                'warehouse_id' => $warehouse->id,
                'code' => $code,
                'type' => 'inbound',
                'document_date' => now()->toDateString(),
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_cost' => 40_000,
                ]],
            ],
        ])->assertCreated();

        return (int) $response->json('voucher.id');
    }
}
