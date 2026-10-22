<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIntegrationRequest;
use App\Integrations\IntegrationDriverFactory;
use App\Repositories\LeadIngestionRepository;
use App\Services\Integrations\IntegrationConfigService;
use App\Services\Leads\LeadIngestionService;
use App\Services\Pancake\PancakeConnectionResolver;
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
        PancakeConnectionResolver $pancakeConnections,
    ): JsonResponse {
        $enum = IntegrationPlatform::tryFrom($platform);

        if (! $enum) {
            return response()->json([
                'success' => false,
                'message' => __('integrations.test.unsupported'),
            ], 404);
        }

        try {
            if ($enum === IntegrationPlatform::Pancake) {
                $result = $pancakeConnections->client()->test();
                $config->touchSynced($enum);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'display' => [
                        'success' => true,
                        'message' => $result['message'],
                        'lines' => [
                            ['label' => __('integrations.test.platform'), 'value' => $enum->label(), 'highlight' => true],
                            ['label' => 'Shop kiểm tra', 'value' => json_encode($result['shops'] ?? [], JSON_UNESCAPED_UNICODE)],
                        ],
                        'items' => [],
                        'options' => [],
                    ],
                ]);
            }

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

    public function syncNow(
        Request $request,
        string $platform,
        IntegrationConfigService $config,
        PancakeConnectionResolver $pancakeConnections,
        \App\Services\Pancake\PancakeOrderImportService $importer,
    ): JsonResponse {
        $enum = IntegrationPlatform::tryFrom($platform);

        if ($enum !== IntegrationPlatform::Pancake) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ hỗ trợ đồng bộ thủ công cho Pancake POS.',
            ], 422);
        }

        try {
            $client = $pancakeConnections->client();
            $response = $client->orders(['page' => 1]);
            $orders = \Illuminate\Support\Arr::get($response, 'data')
                ?? \Illuminate\Support\Arr::get($response, 'orders')
                ?? \Illuminate\Support\Arr::get($response, 'items')
                ?? [];

            if (! is_array($orders) || empty($orders)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Không tìm thấy đơn hàng mới nào trên Pancake POS.',
                    'count' => 0,
                    'orders' => [],
                ]);
            }

            $synced = [];
            $limit = min(20, count($orders));
            foreach (array_slice($orders, 0, $limit) as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $res = $importer->import($order, $request->user());
                $synced[] = [
                    'order_code' => $res['order']?->order_code ?? ('lead#'.$res['lead']->id),
                    'customer_name' => $res['lead']->customer_name,
                    'customer_phone' => $res['lead']->customer_phone,
                ];
            }

            $config->touchSynced($enum);

            return response()->json([
                'success' => true,
                'message' => sprintf('Đã đồng bộ thành công %d đơn hàng từ Pancake POS!', count($synced)),
                'count' => count($synced),
                'orders' => $synced,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi đồng bộ từ Pancake POS: '.$e->getMessage(),
            ], 422);
        }
    }
}
