<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampaignLandingWebhookController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ShippingWebhookController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/token', [AuthController::class, 'token']);

    Route::post('landing/{token}/receive', [CampaignLandingWebhookController::class, 'receive'])
        ->where('token', '[a-z0-9]{16,64}');

    Route::match(['get', 'post'], 'webhooks/{platform}', [WebhookController::class, 'handle'])
        ->where('platform', 'facebook|tiktok|zalo|landing|ladipage|google|shopee|lazada');
    Route::post('shipping/webhooks/{provider}', [ShippingWebhookController::class, 'handle'])
        ->where('provider', 'viettel_post|ghn|ghtk|jnt|spx');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::delete('auth/token', [AuthController::class, 'logout']);

        Route::get('dashboard/summary', [DashboardController::class, 'summary']);

        Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
        Route::apiResource('leads', LeadController::class)->only(['index', 'show']);
        Route::post('leads', [LeadController::class, 'store']);

        Route::middleware('role:'.User::ROLE_ADMIN)->group(function () {
            Route::get('integrations', [IntegrationController::class, 'index']);
            Route::get('integrations/{platform}', [IntegrationController::class, 'show']);
            Route::put('integrations/{platform}', [IntegrationController::class, 'update']);
        });
    });
});
