<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponds
{
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $body = ['success' => true];

        if ($message) {
            $body['message'] = $message;
        }

        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            $resolved = $data->response()->getData(true);
            $body['data'] = $resolved['data'] ?? $resolved;
            if (! empty($resolved['meta'])) {
                $body['meta'] = array_merge($meta, (array) $resolved['meta']);
            }
            if (! empty($resolved['links'])) {
                $body['links'] = $resolved['links'];
            }
        } elseif ($data !== null) {
            $body['data'] = $data;
        }

        if ($meta && ! isset($body['meta'])) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    protected function created(mixed $data = null, ?string $message = 'Đã tạo thành công.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
