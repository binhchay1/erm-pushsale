<?php

namespace App\Services\Marketing;

use App\Models\MarketingSource;
use Illuminate\Support\Str;

class CampaignLandingService
{
    public function webhookUrl(MarketingSource $campaign): string
    {
        return url('/api/v1/landing/'.$campaign->webhook_token.'/receive');
    }

    public function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (MarketingSource::query()->where('webhook_token', $token)->exists());

        return $token;
    }

    /**
     * Token webhook ổn định theo "seed" (thường là utm_campaign slug).
     *
     * Dùng cho seeder/demo: token KHÔNG đổi mỗi lần seed lại DB, tránh việc
     * Ladipage trỏ token cũ rồi webhook trả 404 sau mỗi lần seed.
     */
    public function stableToken(string $seed): string
    {
        return substr(hash('sha256', 'saleops-landing|'.Str::lower(trim($seed))), 0, 32);
    }

    public function utmCampaignFromName(string $name): string
    {
        $slug = Str::slug($name, '-', 'vi');

        return $slug !== '' ? $slug : 'campaign-'.Str::lower(Str::random(6));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForCreate(array $data, int $creatorId): array
    {
        $data['created_by_user_id'] = $creatorId;
        $data['webhook_token'] = $this->generateToken();
        $data['utm_campaign'] = $this->utmCampaignFromName($data['name']);
        $data['utm_source'] = $data['utm_source'] ?? 'ladipage';
        $data['ad_channel'] = $data['ad_channel'] ?? 'landing';
        $data['is_active'] = true;
        $data['is_approved'] = false;
        $data['lead_allocation'] = $data['lead_allocation'] ?? 'inherit';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForUpdate(MarketingSource $campaign, array $data): array
    {
        $data['utm_campaign'] = $this->utmCampaignFromName($data['name']);

        return $data;
    }
}
