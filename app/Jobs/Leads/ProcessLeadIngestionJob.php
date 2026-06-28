<?php

namespace App\Jobs\Leads;

use App\Enums\IntegrationPlatform;
use App\Integrations\IntegrationDriverFactory;
use App\Models\MarketingSource;
use App\Services\Integrations\IntegrationConfigService;
use App\Services\Leads\LeadIngestionService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessLeadIngestionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $platform,
        public array $payload,
        public ?int $campaignId = null,
        public ?int $companyId = null,
    ) {}

    public function handle(
        LeadIngestionService $ingestionService,
        IntegrationConfigService $configService,
    ): void {
        app(TenantManager::class)->forCompany($this->companyId, function () use ($ingestionService, $configService) {
            $this->process($ingestionService, $configService);
        });
    }

    private function process(
        LeadIngestionService $ingestionService,
        IntegrationConfigService $configService,
    ): void {
        if ($this->campaignId) {
            $this->processCampaign($ingestionService);

            return;
        }

        $enum = IntegrationPlatform::tryFromWebhookPath($this->platform)
            ?? IntegrationPlatform::tryFrom($this->platform);

        if (! $enum) {
            Log::warning('[Lead] Bỏ qua job — nền tảng không hợp lệ', ['platform' => $this->platform]);

            return;
        }

        $driver = IntegrationDriverFactory::make($enum);

        if ($enum === IntegrationPlatform::Facebook) {
            foreach ($this->payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? '') === 'leadgen') {
                        $ingestionService->ingest($driver, [
                            'entry' => [['changes' => [$change]]],
                        ]);
                    }
                }
            }
        } else {
            $ingestionService->ingest($driver, $this->payload);
        }

        $configService->touchSynced($enum);
    }

    private function processCampaign(LeadIngestionService $ingestionService): void
    {
        $campaign = MarketingSource::query()->find($this->campaignId);

        if (! $campaign) {
            Log::warning('[Lead] Bỏ qua job — không tìm thấy chiến dịch', ['id' => $this->campaignId]);

            return;
        }

        $driver = IntegrationDriverFactory::make(IntegrationPlatform::Landing);
        $ingestionService->ingestForCampaign($driver, $campaign, $this->payload);
    }
}
