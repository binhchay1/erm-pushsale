<?php

namespace Tests\Unit\Support;

use App\Models\Company;
use App\Support\TenantEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_company_uses_flat_domain(): void
    {
        $company = Company::query()->create([
            'name' => 'Internal',
            'slug' => TenantEmail::internalSlug(),
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'internal',
        ]);

        $this->assertSame('@saleops.local', TenantEmail::suffixFor($company));
        $this->assertSame('nguyen.van.a@saleops.local', TenantEmail::build('nguyen.van.a', $company));
        $this->assertTrue(TenantEmail::acceptsForCompany('sales@saleops.local', $company));
        $this->assertFalse(TenantEmail::acceptsForCompany('sales@client.saleops.local', $company));
    }

    public function test_subsidiary_company_uses_slug_subdomain(): void
    {
        $company = Company::query()->create([
            'name' => 'Acme Co',
            'slug' => 'acme',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'trial',
        ]);

        $this->assertSame('@acme.saleops.local', TenantEmail::suffixFor($company));
        $this->assertSame('sales@acme.saleops.local', TenantEmail::build('sales', $company));
        $this->assertSame('sales', TenantEmail::localPartFromEmail('sales@acme.saleops.local', $company));
        $this->assertFalse(TenantEmail::acceptsForCompany('sales@saleops.local', $company));
    }

    public function test_company_email_login_host_override(): void
    {
        $company = Company::query()->create([
            'name' => 'Custom Host Co',
            'slug' => 'custom-host',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'trial',
            'email_login_host' => 'mail.custom-host.vn',
        ]);

        $this->assertSame('@mail.custom-host.vn', TenantEmail::suffixFor($company));
        $this->assertSame('admin@mail.custom-host.vn', TenantEmail::build('admin', $company));
    }
}
