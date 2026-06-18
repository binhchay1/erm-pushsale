<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIntegrationRequest;
use App\Integrations\IntegrationDriverFactory;
use App\Repositories\LeadIngestionRepository;
use App\Services\Integrations\IntegrationConfigService;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class IntegrationsController extends Controller
{
    public function index(IntegrationConfigService $config, LeadIngestionRepository $leads): Response
    {
        return Inertia::render('Admin/Integrations/Index', [
            'hub' => $config->hubOverview(),
            'categories' => $config->categories(),
            'platforms' => $config->listForAdmin(),
            'leadRouting' => config('saleops.lead_routing'),
            'stats' => [
                'leads_today' => $leads->countToday(),
                'leads_pending' => $leads->countPending(),
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

        return back()->with('success', __('messages.integrations_saved', ['platform' => $enum->label()]));
    }

    public function testWebhook(
        Request $request,
        string $platform,
        LeadIngestionService $ingestionService,
        IntegrationConfigService $config,
    ): JsonResponse {
        $enum = IntegrationPlatform::tryFrom($platform);

        if (! $enum) {
            return response()->json([
                'success' => false,
                'message' => __('integrations.test.unsupported'),
            ], 404);
        }

        try {
            $phone = '09'.random_int(10000000, 99999999);
            $sampleName = __('integrations.test.sample_name');
            $sampleProduct = __('integrations.test.sample_product');

            $payload = match ($enum) {
                IntegrationPlatform::Facebook => [
                    'entry' => [[
                        'changes' => [[
                            'field' => 'leadgen',
                            'value' => [
                                'leadgen_id' => 'test_'.uniqid(),
                                'field_data' => [
                                    ['name' => 'full_name', 'values' => [$sampleName]],
                                    ['name' => 'phone_number', 'values' => [$phone]],
                                ],
                            ],
                        ]],
                    ]],
                ],
                default => [
                    'id' => 'test_'.uniqid(),
                    'name' => $sampleName,
                    'phone' => $phone,
                    'product' => $sampleProduct,
                    'utm_source' => $enum->value,
                    'utm_campaign' => 'admin-test',
                ],
            };

            $driver = IntegrationDriverFactory::make($enum);
            $ingestion = $ingestionService->ingest($driver, $payload);
            $config->touchSynced($enum);

            return response()->json([
                'success' => true,
                'message' => __('integrations.test.success'),
                'display' => [
                    'success' => true,
                    'message' => __('integrations.test.sample_recorded'),
                    'lines' => [
                        ['label' => __('integrations.test.platform'), 'value' => $enum->label(), 'highlight' => true],
                        ['label' => __('integrations.test.lead_id'), 'value' => (string) $ingestion->id],
                        ['label' => __('integrations.test.status'), 'value' => $ingestion->status->label()],
                        ['label' => __('integrations.test.sample_phone'), 'value' => $phone],
                    ],
                    'items' => [],
                    'options' => [],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('integrations.test.failed', ['error' => $e->getMessage()]),
                'display' => [
                    'success' => false,
                    'message' => __('integrations.test.payload_failed'),
                    'lines' => [],
                    'items' => [],
                    'options' => [],
                ],
            ], 422);
        }
    }
}
