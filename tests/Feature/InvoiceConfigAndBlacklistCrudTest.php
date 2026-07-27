<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Pushsale\ElectronicInvoiceConfig;
use App\Models\Pushsale\PhoneBlacklist;
use App\Models\User;
use App\Services\Pushsale\PageResourceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceConfigAndBlacklistCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_electronic_invoice_config_via_resource_manager(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'is_platform_admin' => true]);
        $manager = app(PageResourceManager::class);

        $record = $manager->create('1.14.1', [
            'account' => 'demo-account',
            'password' => 'Secret123!',
            'invoice_type_code' => '1',
            'tax_code' => '0312345678',
            'invoice_template_code' => '1C22T',
            'invoice_series' => 'C22TAA',
            'business_name' => 'Cong ty Demo',
            'address' => 'Ha Noi',
            'phone' => '0901234567',
            'email' => 'demo@example.com',
            'bank_name' => 'Vietcombank',
            'bank_account' => '0123456789',
            'is_active' => true,
        ], $user);

        $this->assertInstanceOf(ElectronicInvoiceConfig::class, $record);
        $this->assertDatabaseHas('electronic_invoice_configs', [
            'account' => 'demo-account',
            'tax_code' => '0312345678',
            'business_name' => 'Cong ty Demo',
        ]);
    }

    public function test_invoice_config_requires_core_fields(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PageResourceManager::class)->create('1.14.1', [
            'account' => '',
            'tax_code' => '',
        ], User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_can_create_phone_blacklist_via_resource_manager(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'is_platform_admin' => true]);
        $manager = app(PageResourceManager::class);

        $record = $manager->create('1.13.1', [
            'phone' => '0909888777',
            'reason' => 'Spam',
            'order_id' => null,
            'creation_type' => 'manual',
        ], $user);

        $this->assertInstanceOf(PhoneBlacklist::class, $record);
        $this->assertDatabaseHas('phone_blacklists', [
            'phone' => '0909888777',
            'creation_type' => 'manual',
        ]);
    }

    public function test_phone_blacklist_rejects_invalid_phone(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PageResourceManager::class)->create('1.13.1', [
            'phone' => 'abc',
            'creation_type' => 'manual',
        ], User::factory()->create(['role' => UserRole::Admin]));

        $this->assertSame(0, PhoneBlacklist::query()->count());
    }
}
