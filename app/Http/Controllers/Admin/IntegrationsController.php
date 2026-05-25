<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIntegrationRequest;
use App\Enums\IntegrationPlatform;
use App\Enums\LeadIngestionStatus;
use App\Integrations\IntegrationDriverFactory;
use App\Models\LeadIngestion;
use App\Services\Integrations\IntegrationConfigService;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function index(IntegrationConfigService $config): Response
    {
        $today = now()->startOfDay();

        return Inertia::render('Admin/Integrations/Index', [
            'hub' => $config->hubOverview(),
            'categories' => $config->categories(),
            'platforms' => $config->listForAdmin(),
            'leadRouting' => config('saleops.lead_routing'),
            'stats' => [
                'leads_today' => LeadIngestion::query()->where('created_at', '>=', $today)->count(),
                'leads_pending' => LeadIngestion::query()->where('status', LeadIngestionStatus::Pending)->count(),
                'platforms_enabled' => collect($config->listForAdmin())->where('is_enabled', true)->count(),
            ],
        ]);
    }

    public function update(
        UpdateIntegrationRequest $request,
        string $platform,
        IntegrationConfigService $config,
    ): RedirectResponse {
        $enum = IntegrationPlatform::tryFrom($platform);

        if (! $enum) {
            abort(404);
        }

        $config->updateConnection($enum, $request->validated());

        return back()->with('success', "Đã lưu cấu hình {$enum->label()}.");
    }

    public function testWebhook(
        Request $request,
        string $platform,
        LeadIngestionService $ingestionService,
        IntegrationConfigService $config,
    ): RedirectResponse {
        $enum = IntegrationPlatform::tryFrom($platform);

        if (! $enum) {
            abort(404);
        }

        $phone = '09'.random_int(10000000, 99999999);

        $payload = match ($enum) {
            IntegrationPlatform::Facebook => [
                'entry' => [[
                    'changes' => [[
                        'field' => 'leadgen',
                        'value' => [
                            'leadgen_id' => 'test_'.uniqid(),
                            'field_data' => [
                                ['name' => 'full_name', 'values' => ['Khách test webhook']],
                                ['name' => 'phone_number', 'values' => [$phone]],
                            ],
                        ],
                    ]],
                ]],
            ],
            default => [
                'id' => 'test_'.uniqid(),
                'name' => 'Khách test webhook',
                'phone' => $phone,
                'product' => 'Sản phẩm demo',
                'utm_source' => $enum->value,
                'utm_campaign' => 'admin-test',
            ],
        };

        $driver = IntegrationDriverFactory::make($enum);
        $ingestion = $ingestionService->ingest($driver, $payload);
        $config->touchSynced($enum);

        return back()->with(
            'success',
            "Test webhook OK — lead #{$ingestion->id}, trạng thái: {$ingestion->status->label()}"
        );
    }
}
