<?php

namespace Database\Seeders;

use App\Models\MarketingSource;
use App\Models\Product;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class MarketingSourceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@saleops.local')->first();
        $marketingUsers = User::query()->where('role', UserRole::Marketing)->get();
        $mkt = $marketingUsers->values();

        $camera = Product::query()->where('sku', 'CAM-MINI')->first();
        $product = Product::query()->where('sku', 'SP292627')->first();

        $sourceParent = MarketingSource::query()->firstOrCreate([
            'name' => 'Hải - camera mini nhật bản',
        ], [
            'product_id' => $camera?->id,
            'marketer_user_id' => $mkt->get(0)?->id ?? $admin?->id,
            'ad_channel' => 'facebook',
            'utm_source' => 'facebook',
            'utm_campaign' => 'cam-mini-fb',
            'budget' => 5_000_000,
            'interactions' => 1200,
            'contacts' => 180,
            'is_active' => true,
        ]);

        MarketingSource::query()->firstOrCreate([
            'parent_id' => $sourceParent->id,
            'name' => $sourceParent->name,
        ], [
            'product_id' => $camera?->id,
            'marketer_user_id' => $mkt->get(1)?->id ?? $admin?->id,
            'ad_channel' => 'google',
            'utm_source' => 'youtube',
            'utm_campaign' => 'cam-mini-q2',
            'budget' => 2_000_000,
            'interactions' => 400,
            'contacts' => 60,
            'is_active' => true,
        ]);

        MarketingSource::query()->firstOrCreate([
            'name' => 'Ngọc Huyền - GG - Bột diệt cỏ',
        ], [
            'product_id' => $product?->id,
            'marketer_user_id' => $mkt->get(2)?->id ?? $admin?->id,
            'ad_channel' => 'google',
            'utm_source' => 'google',
            'utm_campaign' => 'bot-diet-co-gg',
            'budget' => 3_500_000,
            'interactions' => 800,
            'contacts' => 95,
            'is_active' => true,
        ]);
    }
}
