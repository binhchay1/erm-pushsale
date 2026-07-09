<?php

namespace App\Jobs\Leads;

use App\Enums\IntegrationPlatform;
use App\Enums\LeadIngestionStatus;
use App\Integrations\IntegrationDriverFactory;
use App\Models\InboundEvent;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Services\Integrations\IntegrationConfigService;
use App\Services\Leads\LeadIngestionService;
use App\Services\Pancake\PancakeOrderImportService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        public ?int $inboundEventId = null,
        public bool $isUpsell = false,
    ) {
        $this->onQueue(config('saleops.queues.webhooks', 'webhooks'));
    }

    public function handle(
        LeadIngestionService $ingestionService,
        IntegrationConfigService $configService,
        PancakeOrderImportService $pancakeImporter,
    ): void {
        $this->lastIngestion = null;

        app(TenantManager::class)->forCompany($this->companyId, function () use ($ingestionService, $configService, $pancakeImporter) {
            $this->process($ingestionService, $configService, $pancakeImporter);
        });

        if ($this->inboundEventId) {
            $event = InboundEvent::query()->find($this->inboundEventId);

            if ($this->lastIngestion?->status === LeadIngestionStatus::Failed) {
                $event?->markFailed($this->lastIngestion->error_message ?? __('messages.webhook.validation_failed'));
            } elseif ($this->lastIngestion === null && $this->campaignId !== null) {
                $event?->markFailed(__('messages.webhook.landing_not_found'));
            } else {
                $event?->markProcessed();
            }
        }
    }

    private ?LeadIngestion $lastIngestion = null;

    public function failed(?Throwable $e): void
    {
        if ($this->inboundEventId) {
            InboundEvent::query()->find($this->inboundEventId)?->markFailed($e?->getMessage() ?? 'Job failed');
        }
    }

    private function process(
        LeadIngestionService $ingestionService,
        IntegrationConfigService $configService,
        PancakeOrderImportService $pancakeImporter,
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

        if ($enum === IntegrationPlatform::Pancake) {
            $result = $pancakeImporter->import($this->payload);
            $this->lastIngestion = $result['lead'];
            $configService->touchSynced($enum);

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
            $this->lastIngestion = $ingestionService->ingest($driver, $this->payload);
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

        $this->lastIngestion = $this->isUpsell
            ? $ingestionService->ingestUpsellForCampaign($driver, $campaign, $this->payload)
            : $ingestionService->ingestForCampaign($driver, $campaign, $this->payload);
    }
}
