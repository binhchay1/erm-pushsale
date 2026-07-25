<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\Marketing\LandingConnectionManager;
use Illuminate\Database\Seeder;

/**
 * Seed dữ liệu thật cho menu 2.4.1 Kết nối landing/nguồn dữ liệu.
 *
 * Các bản ghi tạo bằng LandingConnectionManager để đồng bộ đầy đủ:
 * marketing_sources -> landing_connections -> sources -> products -> sale priority.
 */
class LandingConnectionDemoSeeder extends Seeder
{
    public function __construct(private readonly LandingConnectionManager $manager) {}

    public function run(): void
    {
        if (LandingConnection::query()->count() >= 8) {
            $this->command?->info('Bỏ qua LandingConnectionDemoSeeder: đã có dữ liệu kết nối landing.');

            return;
        }

        $actor = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderByDesc('is_platform_admin')
            ->orderBy('id')
            ->first();

        $marketers = User::query()
            ->where('role', UserRole::Marketing->value)
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->values();

        $sales = User::query()
            ->where('role', UserRole::Sales->value)
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->values();

        $products = Product::query()
            ->where('is_active', true)
            ->where('available_marketing', true)
            ->orderByRaw("case when type = 'combo' then 0 else 1 end")
            ->orderBy('id')
            ->limit(18)
            ->get()
            ->values();

        if (! $actor || $marketers->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('Bỏ qua LandingConnectionDemoSeeder: thiếu admin/marketing/product để tạo nguồn dữ liệu.');

            return;
        }

        $saleTeam = Team::query()->where('type', 'sale')->with('users:id,name,email,team_id')->orderBy('id')->first();
        $channels = ['facebook_ads', 'youtube', 'google_ads', 'tiktok_ads', 'zalo_ads', 'hotline'];
        $types = ['landing', 'facebook', 'landing', 'website'];

        foreach ($products->take(12) as $index => $product) {
            $marketer = $marketers[$index % $marketers->count()];
            $channel = $channels[$index % count($channels)];
            $type = $types[$index % count($types)];
            $slug = str($product->sku ?: $product->name)->lower()->slug('-')->toString();
            $name = sprintf('%s - %s', $marketer->name, $product->name);

            if (LandingConnection::query()->where('name', $name)->exists()) {
                continue;
            }

            $prioritySales = $sales
                ->slice($index % max(1, $sales->count()), 3)
                ->when(fn ($collection) => $collection->count() < 3, fn ($collection) => $collection->merge($sales->take(3 - $collection->count())))
                ->pluck('id')
                ->values()
                ->all();

            $this->manager->create([
                'name' => $name,
                'marketer_user_id' => $marketer->id,
                'connection_type' => $type,
                'ad_channel' => $channel,
                'allocation_method' => $prioritySales === [] ? 'inherit' : 'priority',
                'budget_type' => 'total',
                'budget_amount' => 2_000_000 + ($index * 450_000),
                'budget_start_date' => now()->startOfMonth()->toDateString(),
                'budget_end_date' => now()->endOfMonth()->toDateString(),
                'success_url' => "https://demo.salesloop.vn/thank-you/{$slug}",
                'manual_import' => $index % 5 === 0,
                'is_approved' => $index % 7 !== 6,
                'is_active' => true,
                'notes' => $saleTeam ? 'Seed từ nhóm '.$saleTeam->name : 'Seed landing connection demo',
                'sources' => [
                    [
                        'client_key' => 'main_'.$index,
                        'name' => $name,
                        'source_type' => 'main',
                        'source_url' => "https://landing.salesloop.vn/{$slug}",
                        'redirect_url' => "https://demo.salesloop.vn/thank-you/{$slug}",
                        'sort_order' => 0,
                        'is_active' => true,
                    ],
                    [
                        'client_key' => 'upsell_'.$index,
                        'name' => 'Trang upsale '.($index + 1),
                        'source_type' => 'upsell',
                        'source_url' => "https://landing.salesloop.vn/{$slug}/upsale",
                        'redirect_url' => null,
                        'sort_order' => 1,
                        'is_active' => true,
                    ],
                ],
                'products' => $index % 7 === 6 ? [] : [[
                    'product_id' => $product->id,
                    'source_key' => 'main_'.$index,
                    'item_type' => $product->type === 'combo' ? 'combo' : 'product',
                    'external_field' => null,
                    'external_value' => null,
                    'quantity' => 1,
                    'unit_price_override' => null,
                    'is_default' => true,
                ]],
                'sale_user_ids' => $prioritySales,
            ], $actor);
        }

        $this->command?->info('Đã tạo dữ liệu thật cho menu 2.4.1 Kết nối landing/nguồn dữ liệu.');
    }
}
