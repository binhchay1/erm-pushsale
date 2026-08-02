<?php

namespace App\Services\Operations;

use App\Events\OrderInteractionLocked;
use App\Events\OrderInteractionReleased;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderInteractionLockService
{
    public const TTL_SECONDS = 90;

    public const HEARTBEAT_SECONDS = 25;

    /**
     * @return array{token: string, order_id: int, holder: array<string, mixed>, expires_at: string, acquired: bool}
     */
    public function acquire(Order $order, User $actor, string $action = 'dialog'): array
    {
        $key = $this->cacheKey($order);
        $now = now();
        $existing = Cache::get($key);

        if (is_array($existing) && ! $this->isExpired($existing)) {
            if ((int) ($existing['user_id'] ?? 0) === (int) $actor->id) {
                $payload = $this->buildPayload($order, $actor, $action, (string) $existing['token'], $now);
                Cache::put($key, $payload, self::TTL_SECONDS);
                $this->broadcastLocked($order, $payload);

                return $this->response($payload, acquired: true);
            }

            throw $this->lockedException($existing);
        }

        $payload = $this->buildPayload($order, $actor, $action, (string) Str::uuid(), $now);
        if (! Cache::add($key, $payload, self::TTL_SECONDS)) {
            $race = Cache::get($key);
            if (is_array($race) && (int) ($race['user_id'] ?? 0) === (int) $actor->id) {
                Cache::put($key, $this->buildPayload($order, $actor, $action, (string) $race['token'], $now), self::TTL_SECONDS);

                return $this->response(Cache::get($key), acquired: true);
            }
            throw $this->lockedException(is_array($race) ? $race : $payload);
        }

        $this->broadcastLocked($order, $payload);

        return $this->response($payload, acquired: true);
    }

    /**
     * @return array{token: string, order_id: int, holder: array<string, mixed>, expires_at: string, acquired: bool}
     */
    public function heartbeat(Order $order, User $actor, string $token): array
    {
        $key = $this->cacheKey($order);
        $existing = Cache::get($key);

        if (! is_array($existing) || $this->isExpired($existing) || (string) ($existing['token'] ?? '') !== $token) {
            throw $this->lockedException(is_array($existing) ? $existing : null, 'Phiên thao tác đã hết hạn. Mở lại dialog để tiếp tục.');
        }

        if ((int) ($existing['user_id'] ?? 0) !== (int) $actor->id) {
            throw $this->lockedException($existing);
        }

        $payload = $this->buildPayload(
            $order,
            $actor,
            (string) ($existing['action'] ?? 'dialog'),
            $token,
            now(),
            $existing['acquired_at'] ?? now()->toIso8601String(),
        );
        Cache::put($key, $payload, self::TTL_SECONDS);

        return $this->response($payload, acquired: true);
    }

    public function release(Order $order, User $actor, ?string $token = null): void
    {
        $key = $this->cacheKey($order);
        $existing = Cache::get($key);
        if (! is_array($existing)) {
            return;
        }

        if ((int) ($existing['user_id'] ?? 0) !== (int) $actor->id) {
            return;
        }

        if ($token !== null && (string) ($existing['token'] ?? '') !== $token) {
            return;
        }

        Cache::forget($key);
        try {
            event(new OrderInteractionReleased(
                companyId: (int) ($order->company_id ?? $actor->company_id),
                orderId: (int) $order->id,
                userId: (int) $actor->id,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Ensure the actor holds the lock (valid token) or acquire if free / same user.
     */
    public function assertHeldOrAcquire(Order $order, User $actor, ?string $token, string $action = 'mutate'): string
    {
        if (filled($token)) {
            $this->assertHeld($order, $actor, $token);

            return (string) $token;
        }

        $result = $this->acquire($order, $actor, $action);

        return (string) $result['token'];
    }

    public function assertHeld(Order $order, User $actor, ?string $token): void
    {
        $existing = Cache::get($this->cacheKey($order));
        if (! is_array($existing) || $this->isExpired($existing)) {
            throw $this->lockedException(null, 'Đơn chưa được khóa thao tác. Mở lại dialog rồi thử lại.');
        }

        if ((string) ($existing['token'] ?? '') !== (string) $token
            || (int) ($existing['user_id'] ?? 0) !== (int) $actor->id) {
            throw $this->lockedException($existing);
        }
    }

    /** @return array<string, mixed>|null */
    public function getHolder(Order $order): ?array
    {
        $existing = Cache::get($this->cacheKey($order));
        if (! is_array($existing) || $this->isExpired($existing)) {
            return null;
        }

        return $this->holderFromPayload($existing);
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<string, array<string, mixed>>
     */
    public function holdersForOrders(array $orderIds): array
    {
        $map = [];
        foreach ($orderIds as $orderId) {
            $existing = Cache::get($this->keyForId((int) $orderId));
            if (is_array($existing) && ! $this->isExpired($existing)) {
                $map[(string) $orderId] = $this->holderFromPayload($existing);
            }
        }

        return $map;
    }

    private function cacheKey(Order $order): string
    {
        return $this->keyForId((int) $order->id);
    }

    private function keyForId(int $orderId): string
    {
        return 'order_interaction_lock:'.$orderId;
    }

    /** @param  array<string, mixed>  $payload */
    private function isExpired(array $payload): bool
    {
        $expires = $payload['expires_at'] ?? null;
        if (! is_string($expires) || $expires === '') {
            return true;
        }

        return now()->greaterThan(\Carbon\Carbon::parse($expires));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        Order $order,
        User $actor,
        string $action,
        string $token,
        mixed $now,
        ?string $acquiredAt = null,
    ): array {
        $expires = $now->copy()->addSeconds(self::TTL_SECONDS);

        return [
            'token' => $token,
            'order_id' => (int) $order->id,
            'company_id' => (int) ($order->company_id ?? $actor->company_id),
            'user_id' => (int) $actor->id,
            'user_name' => (string) $actor->name,
            'role' => $actor->role?->value ?? (string) $actor->role,
            'role_label' => $actor->role?->label() ?? (string) $actor->role,
            'action' => $action,
            'acquired_at' => $acquiredAt ?? $now->toIso8601String(),
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, order_id: int, holder: array<string, mixed>, expires_at: string, acquired: bool}
     */
    private function response(array $payload, bool $acquired): array
    {
        return [
            'token' => (string) $payload['token'],
            'order_id' => (int) $payload['order_id'],
            'holder' => $this->holderFromPayload($payload),
            'expires_at' => (string) $payload['expires_at'],
            'acquired' => $acquired,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function holderFromPayload(array $payload): array
    {
        return [
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'user_name' => (string) ($payload['user_name'] ?? ''),
            'role' => (string) ($payload['role'] ?? ''),
            'role_label' => (string) ($payload['role_label'] ?? $payload['role'] ?? ''),
            'action' => (string) ($payload['action'] ?? ''),
            'acquired_at' => (string) ($payload['acquired_at'] ?? ''),
            'expires_at' => (string) ($payload['expires_at'] ?? ''),
            'token' => (string) ($payload['token'] ?? ''),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function broadcastLocked(Order $order, array $payload): void
    {
        try {
            event(new OrderInteractionLocked(
                companyId: (int) ($payload['company_id'] ?? $order->company_id),
                orderId: (int) $order->id,
                holder: $this->holderFromPayload($payload),
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @param  array<string, mixed>|null  $holderPayload */
    private function lockedException(?array $holderPayload, ?string $message = null): HttpException
    {
        $holder = is_array($holderPayload) ? $this->holderFromPayload($holderPayload) : null;
        $name = $holder['user_name'] ?? 'người khác';
        $role = $holder['role_label'] ?? $holder['role'] ?? '';
        $default = $role !== ''
            ? "Đơn đang được thao tác bởi {$name} ({$role}). Vui lòng đợi."
            : "Đơn đang được thao tác bởi {$name}. Vui lòng đợi.";

        return new HttpException(423, $message ?: $default, null, [
            'X-Order-Lock-Holder' => $holder ? json_encode($holder, JSON_UNESCAPED_UNICODE) : '',
        ]);
    }
}
