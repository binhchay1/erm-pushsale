<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_all_sales_leader_report_children(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $tree = app(NavigationService::class)->forUser($user);

        $leader = $this->findByTitle($tree, '4.6 Báo cáo Leader');
        $this->assertNotNull($leader);
        $this->assertCount(5, $leader['children'] ?? []);

        $codes = collect($leader['children'])->pluck('code')->all();
        $this->assertSame(['4.6.1', '4.6.2', '4.6.3', '4.6.4', '4.6.5'], $codes);

        $urls = collect($leader['children'])->pluck('url')->all();
        $this->assertContains('/admin/sales/reports/operation-conversion', $urls);
        $this->assertContains('/admin/sales/reports/work', $urls);
        $this->assertContains('/admin/sales/reports/teams', $urls);
        $this->assertContains('/admin/sales/reports/data', $urls);
        $this->assertContains('/admin/sales/reports/optimization', $urls);
    }

    /** @param list<array<string,mixed>> $items */
    private function findByTitle(array $items, string $title): ?array
    {
        foreach ($items as $item) {
            if (($item['title'] ?? null) === $title) {
                return $item;
            }
            if (! empty($item['children']) && is_array($item['children'])) {
                $found = $this->findByTitle($item['children'], $title);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
