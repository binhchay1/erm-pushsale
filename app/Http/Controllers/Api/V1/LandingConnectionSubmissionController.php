<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InboundEventSource;
use App\Enums\LeadIngestionStatus;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationDriverFactory;
use App\Models\LandingConnection;
use App\Models\LandingConnectionSource;
use App\Models\LandingSession;
use App\Models\Order;
use App\Services\Inbound\InboundEventRecorder;
use App\Services\Leads\LeadIngestionService;
use App\Services\Marketing\LandingConnectionPayloadMapper;
use App\Support\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LandingConnectionSubmissionController extends Controller
{
    public function __construct(
        private readonly LandingConnectionPayloadMapper $mapper,
        private readonly LeadIngestionService $ingestion,
    ) {}

    public function __invoke(Request $request, string $connectionToken, string $sourceToken): JsonResponse|RedirectResponse|Response
    {
        $connection = LandingConnection::query()
            ->withoutTenant()
            ->with([
                'marketingSource' => fn ($query) => $query->withoutTenant(),
                'products' => fn ($query) => $query->withoutTenant()->with([
                    'product' => fn ($product) => $product->withoutTenant(),
                ]),
            ])
            ->where('public_token', $connectionToken)
            ->first();

        $source = LandingConnectionSource::query()
            ->withoutTenant()
            ->where('public_token', $sourceToken)
            ->where('landing_connection_id', $connection?->id)
            ->first();

        if (! $connection || ! $source) {
            return $this->failure($request, 'Không tìm thấy kết nối landing.', 404);
        }

        if (! $source->acceptsSubmissions()) {
            return $this->failure($request, 'Nguồn này chỉ dùng làm trang đích, không nhận dữ liệu form.', 405);
        }

        if (! $connection->is_active || ! $source->is_active || ! $connection->marketingSource?->is_active) {
            return $this->failure($request, 'Kết nối landing đang tạm dừng.', 403);
        }

        if (! $connection->is_approved || ! $connection->marketingSource?->is_approved) {
            return $this->failure($request, 'Kết nối landing chưa được duyệt.', 403);
        }

        $event = app(InboundEventRecorder::class)->record(
            $request,
            InboundEventSource::LandingWebhook,
            'landing-connection:'.$connection->id.':source:'.$source->id,
            (int) $connection->company_id,
            $request->all(),
        );

        try {
            $result = app(TenantManager::class)->forCompany($connection->company_id, function () use ($request, $connection, $source): array {
                $driver = IntegrationDriverFactory::make('landing');
                $rawNormalized = $driver->normalize($request->all());
                $phone = $this->normalizePhone($rawNormalized['customer_phone'] ?? null);
                $flowToken = $this->resolveFlowToken($request, $connection, $source, $phone);
                $payload = $this->mapper->map($connection, $source, $request->all(), $flowToken);
                $normalized = $driver->normalize($payload);

                if ($this->normalizePhone($normalized['customer_phone'] ?? null) === null) {
                    throw new HttpException(422, 'Vui lòng gửi số điện thoại hợp lệ của khách hàng.');
                }

                if (empty($normalized['items']) || ! is_array($normalized['items'])) {
                    throw new HttpException(422, 'Không xác định được sản phẩm từ cấu hình kết nối và dữ liệu form.');
                }

                $lead = $source->isSupplemental()
                    ? $this->ingestion->ingestUpsellForCampaign($driver, $connection->marketingSource, $payload)
                    : $this->ingestion->ingestForCampaign($driver, $connection->marketingSource, $payload);

                $lead->forceFill([
                    'landing_connection_id' => $connection->id,
                    'landing_connection_source_id' => $source->id,
                ])->save();

                $session = LandingSession::query()
                    ->where('session_key', $flowToken)
                    ->where('marketing_source_id', $connection->marketing_source_id)
                    ->first();
                if ($session) {
                    $sessionUpdates = [
                        'landing_connection_id' => $connection->id,
                        'customer_phone' => $session->customer_phone ?: $phone,
                        'last_activity_at' => now(),
                    ];

                    // Keep the primary landing source on the session. Upsell packets are
                    // traceable on lead_ingestions and must not overwrite the entry source.
                    if (! $source->isSupplemental() || ! $session->landing_connection_source_id) {
                        $sessionUpdates['landing_connection_source_id'] = $source->id;
                    }

                    $session->forceFill($sessionUpdates)->save();
                }

                $orderId = $lead->order_id ?: $lead->related_order_id ?: $session?->order_id;
                if ($orderId) {
                    $order = Order::query()->whereKey($orderId)->first();
                    if ($order) {
                        $orderUpdates = ['landing_connection_id' => $connection->id];
                        if (! $source->isSupplemental() || ! $order->landing_connection_source_id) {
                            $orderUpdates['landing_connection_source_id'] = $source->id;
                        }
                        $order->forceFill($orderUpdates)->save();
                    }
                }

                return [
                    'flow_token' => $flowToken,
                    'lead_id' => $lead->id,
                    'order_id' => $orderId,
                    'status' => $lead->status instanceof LeadIngestionStatus ? $lead->status->value : (string) $lead->status,
                    'requires_review' => (bool) $lead->requires_review,
                ];
            });

            $event->markProcessed();

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'ok' => true,
                    ...Arr::only($result, ['flow_token', 'status', 'requires_review']),
                    'redirect_url' => $this->redirectUrl($source, $connection, $result),
                ], 201);
            }

            if ($redirect = $this->redirectUrl($source, $connection, $result)) {
                return redirect()->away($redirect, 303);
            }

            return response(
                '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đã ghi nhận</title><body style="font-family:Arial,sans-serif;padding:40px;text-align:center"><h2>Đã ghi nhận thông tin</h2><p>Nhân viên sẽ liên hệ xác nhận đơn hàng.</p></body></html>',
                201,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        } catch (HttpExceptionInterface $exception) {
            $event->markRejected($exception->getStatusCode(), $exception->getMessage());

            return $this->failure($request, $exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);
            $event->markFailed($exception->getMessage());

            return $this->failure($request, 'Không thể ghi nhận dữ liệu. Vui lòng thử lại.', 500);
        }
    }

    private function resolveFlowToken(
        Request $request,
        LandingConnection $connection,
        LandingConnectionSource $source,
        ?string $phone,
    ): string {
        foreach (['flow_token', 'ps_flow', 'session_id', 'session_key', 'saleops_session'] as $key) {
            $token = $this->cleanToken($request->input($key) ?? $request->query($key));
            if (! $token) {
                continue;
            }

            $existing = LandingSession::query()->where('session_key', $token)->first();
            if ($existing && (
                ($existing->landing_connection_id && (int) $existing->landing_connection_id !== (int) $connection->id)
                || ($existing->marketing_source_id && (int) $existing->marketing_source_id !== (int) $connection->marketing_source_id)
            )) {
                throw new HttpException(409, 'Mã luồng không thuộc kết nối landing này.');
            }

            return $token;
        }

        if ($phone) {
            $recent = LandingSession::query()
                ->where('landing_connection_id', $connection->id)
                ->where('customer_phone', $phone)
                ->where('created_at', '>=', now()->subSeconds((int) config('saleops.landing.hold_seconds', 90)))
                ->latest('id')
                ->value('session_key');

            if ($recent) {
                return (string) $recent;
            }
        }

        if ($source->isSupplemental() && ! $phone) {
            throw new HttpException(422, 'Trang upsell phải gửi ps_flow hoặc số điện thoại của khách.');
        }

        return Str::lower(Str::random(48));
    }

    private function cleanToken(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value) ?? '';

        return $value !== '' ? substr($value, 0, 64) : null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) >= 9 ? substr($digits, 0, 20) : null;
    }

    /** @param array<string, mixed> $result */
    private function redirectUrl(LandingConnectionSource $source, LandingConnection $connection, array $result): ?string
    {
        $url = $source->redirect_url ?: $connection->success_url;
        if (! $url) {
            return null;
        }

        $query = http_build_query(array_filter([
            'ps_flow' => $result['flow_token'] ?? null,
            'saleops_session' => $result['flow_token'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $separator = str_contains($base, '?')
            ? (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&')
            : '?';

        return $base.$separator.$query.($fragment !== null ? '#'.$fragment : '');
    }

    private function failure(Request $request, string $message, int $status): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return response(
            '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lỗi</title><body style="font-family:Arial,sans-serif;padding:40px;text-align:center"><h2>Không thể ghi nhận</h2><p>'.e($message).'</p></body></html>',
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
