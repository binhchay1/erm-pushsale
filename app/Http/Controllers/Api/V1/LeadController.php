<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLeadRequest;
use App\Http\Resources\V1\LeadIngestionResource;
use App\Http\Traits\ApiResponds;
use App\Integrations\IntegrationDriverFactory;
use App\Models\LeadIngestion;
use App\Repositories\LeadIngestionRepository;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected LeadIngestionService $ingestionService,
        protected LeadIngestionRepository $leads,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $leads = $this->leads->paginatedApiList(
            $request->query('platform'),
            $request->query('status'),
            min((int) $request->query('per_page', 20), 100),
        );

        return $this->success(LeadIngestionResource::collection($leads));
    }

    public function show(LeadIngestion $lead): JsonResponse
    {
        $lead->load('order');

        return $this->success(new LeadIngestionResource($lead));
    }

    /** POST /api/v1/leads — nguồn landing / tool nội bộ (Bearer token). */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $driver = IntegrationDriverFactory::make('landing');
        $ingestion = $this->ingestionService->ingest($driver, $request->validated());

        return $this->created(
            new LeadIngestionResource($ingestion->load('order')),
            'Lead đã được ghi nhận'
        );
    }
}
