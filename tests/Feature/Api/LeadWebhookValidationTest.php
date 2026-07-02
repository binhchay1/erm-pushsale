<?php

namespace Tests\Feature\Api;

use App\Enums\InboundEventStatus;
use App\Enums\IntegrationPlatform;
use App\Enums\UserRole;
use App\Models\InboundEvent;
use App\Models\IntegrationConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadWebhookValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_webhook_rejects_invalid_phone_before_queue(): void
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        $campaign = MarketingSource::query()->create([
            'name' => 'Serum',
            'utm_campaign' => 'serum',
            'webhook_token' => 'testtoken123456789012345678901234',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $response = $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'name' => 'Nguyễn Thanh Bình',
            'phone' => '03582402952',
            'products' => 'SP-SRM-01',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.phone', __('messages.lead_intake.invalid_phone'));

        $this->assertSame(0, LeadIngestion::query()->count());
        $this->assertDatabaseHas('inbound_events', [
            'status' => InboundEventStatus::Rejected->value,
            'http_status' => 422,
        ]);
    }

    public function test_campaign_webhook_accepts_nine_digit_phone(): void
    {
        User::factory()->create(['role' => UserRole::Sales]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);

        $campaign = MarketingSource::query()->create([
            'name' => 'Serum',
            'utm_campaign' => 'serum',
            'webhook_token' => 'ninephone123456789012345678901',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', [
            'submission_id' => 'lp-nine-digit',
            'name' => 'Khách 9 số',
            'phone' => '358240295',
            'products' => 'SP-SRM-01',
        ])->assertAccepted();

        $this->assertDatabaseHas('lead_ingestions', [
            'external_id' => 'lp-nine-digit',
            'customer_phone' => '0358240295',
        ]);
    }

    public function test_ladipage_webhook_rejects_missing_phone(): void
    {
        $this->enableLandingIntegration();

        $this->postJson('/api/v1/webhooks/ladipage?api_key=secret-key', [
            'submission_id' => 'lp-no-phone',
            'name' => 'Không có SĐT',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone', __('messages.lead_intake.phone_required'));

        $this->assertSame(0, LeadIngestion::query()->count());
    }

    public function test_ladipage_webhook_rejects_invalid_phone(): void
    {
        $this->enableLandingIntegration();

        $this->postJson('/api/v1/webhooks/ladipage?api_key=secret-key', [
            'submission_id' => 'lp-bad-phone',
            'name' => 'Sai SĐT',
            'phone' => '03582402952',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone', __('messages.lead_intake.invalid_phone'));

        $event = InboundEvent::query()->latest('id')->first();
        $this->assertSame(InboundEventStatus::Rejected, $event->status);
        $this->assertSame(422, $event->http_status);
    }

    private function enableLandingIntegration(): void
    {
        IntegrationConnection::query()->create([
            'platform' => IntegrationPlatform::Landing->value,
            'is_enabled' => true,
            'credentials' => ['api_key' => 'secret-key'],
        ]);
    }
}
