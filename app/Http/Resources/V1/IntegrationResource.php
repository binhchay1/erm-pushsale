<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->resource['platform'],
            'label' => $this->resource['label'],
            'is_enabled' => $this->resource['is_enabled'],
            'is_configured' => $this->resource['is_configured'],
            'webhook_url' => $this->resource['webhook_url'],
            'last_synced_at' => $this->resource['last_synced_at'],
            'required_env' => $this->resource['required_env'],
            'docs_url' => $this->resource['docs_url'],
        ];
    }
}
