<?php

namespace Tests\Feature\Pushsale;

use App\Models\Company;
use App\Models\User;
use App\Services\Pushsale\PushsalePageService;
use App\Support\TenantManager;
use Database\Seeders\FullBusinessDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PushsaleMenuDemoCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_demo_seed_feeds_registered_pushsale_menu_pages(): void
    {
        $this->seed(FullBusinessDemoSeeder::class);

        $company = Company::query()->where('slug', 'internal')->first()
            ?? Company::query()->firstOrFail();
        $admin = User::query()->where('email', 'admin@saleops.local')->first()
            ?? User::query()->where('role', 'admin')->firstOrFail();

        $this->actingAs($admin);

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $service = app(PushsalePageService::class);
            $pages = config('pushsale_pages');

            $criticalPages = [
                '1.3.1', '1.3.2', '1.7.1', '1.7.2', '1.11', '1.13.1', '1.14.1',
                '2.4.1', '2.4.2', '2.6.2', '4.3', '5.2.1', '5.2.2', '5.3.2', '5.3.3', '5.4',
                '7.1.1', '7.1.2', '7.1.3', '7.1.4', '8.5.4', '8.5.5', '8.5.9', '8.5.10', '8.5.11',
            ];

            foreach (array_keys($pages) as $code) {
                $result = $service->rows($code, Request::create('/admin/demo-coverage', 'GET', ['per_page' => 20]));
                $this->assertArrayHasKey('data', $result, "Page {$code} must return data key");
                $this->assertArrayHasKey('meta', $result, "Page {$code} must return pagination meta");
                $this->assertArrayHasKey('total', $result['meta'], "Page {$code} must return total meta");

                if (in_array($code, $criticalPages, true)) {
                    $this->assertNotEmpty($result['data'], "Demo seed must show real rows for menu {$code}");
                }
            }
        });
    }
}
