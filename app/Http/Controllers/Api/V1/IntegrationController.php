<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateIntegrationRequest;
use App\Http\Resources\V1\IntegrationResource;
use App\Http\Traits\ApiResponds;
use App\Models\IntegrationConnection;
use App\Services\Integrations\IntegrationConfigService;
use Illuminate\Http\JsonResponse;

class IntegrationController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected IntegrationConfigService $configService,
    ) {}

    public function index(): JsonResponse
    {
        $items = collect($this->configService->listForApi())
            ->map(fn ($row) => new IntegrationResource($row));

        return $this->success(IntegrationResource::collection($items));
    }

    public function show(string $platform): JsonResponse
    {
        $enum = IntegrationPlatform::tryFrom($platform);

        if (! $enum) {
            return $this->error('Nền tảng không tồn tại', 404);
        }

        $row = collect($this->configService->listForApi())
            ->firstWhere('platform', $enum->value);

        return $this->success(new IntegrationResource($row));
    }

    public function update(UpdateIntegrationRequest $request, string $platform): JsonResponse
    {
        $enum = IntegrationPlatform::tryFrom($platform);

        if (! $enum) {
            return $this->error('Nền tảng không tồn tại', 404);
        }

        $connection = IntegrationConnection::forPlatform($enum);

        $connection->update(array_filter([
            'is_enabled' => $request->has('is_enabled') ? $request->boolean('is_enabled') : null,
            'credentials' => $request->has('credentials')
                ? array_merge($connection->credentials ?? [], $request->input('credentials', []))
                : null,
            'webhook_secret' => $request->input('webhook_secret'),
            'verify_token' => $request->input('verify_token'),
        ], fn ($v) => $v !== null));

        $row = collect($this->configService->listForApi())
            ->firstWhere('platform', $enum->value);

        return $this->success(new IntegrationResource($row), 'Đã cập nhật cấu hình tích hợp');
    }
}
