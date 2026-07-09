<?php

namespace App\Services\Pancake;

use App\Enums\OrgLevel;
use App\Enums\PancakeAssignmentMode;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Models\IntegrationConnection;
use App\Models\Order;
use App\Models\PancakeSyncRecord;
use App\Models\PancakeUserMapping;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PancakeAssignmentResolver
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $normalized
     * @return array{sale: ?User, mode: string, reason: string, requested_sale_id: ?int, pancake_user_key: ?string, source: string}
     */
    public function resolve(
        array $payload,
        array $normalized,
        IntegrationConnection $connection,
        ?User $actor = null,
    ): array {
        $requestedSaleId = $this->requestedSaleId($payload);
        $requestedMode = $this->requestedMode($payload);

        if ($actor && $requestedSaleId && $this->canAssignSelectedSale($actor)) {
            $sale = $this->resolveSelectedSale($requestedSaleId, $actor);

            return $this->decision(
                $sale,
                PancakeAssignmentMode::SelectedSale,
                __('integrations.pancake_assignment.selected_sale'),
                $requestedSaleId,
                null,
                'extension'
            );
        }

        if ($actor && $requestedSaleId && $requestedMode === PancakeAssignmentMode::SelectedSale->value) {
            throw ValidationException::withMessages([
                'selected_sale_user_id' => __('integrations.pancake_assignment.not_allowed_to_select_sale'),
            ]);
        }

        if ($actor?->isSales() && ! in_array($requestedMode, [PancakeAssignmentMode::AutoRouting->value, PancakeAssignmentMode::PendingPool->value], true)) {
            return $this->decision(
                $actor,
                PancakeAssignmentMode::Self,
                __('integrations.pancake_assignment.extension_self'),
                null,
                null,
                'extension'
            );
        }

        $pancakeUserKey = $this->pancakeUserKey($payload, $normalized);
        if ($pancakeUserKey) {
            $mappedSale = $this->mappedSale($connection, $payload, $normalized, $pancakeUserKey);
            if ($mappedSale) {
                return $this->decision(
                    $mappedSale,
                    PancakeAssignmentMode::PancakeUserMapping,
                    __('integrations.pancake_assignment.mapped_pancake_user'),
                    null,
                    $pancakeUserKey,
                    $actor ? 'extension' : 'webhook'
                );
            }
        }

        $existingOwner = $this->existingConversationOwner($connection, $payload, $normalized);
        if ($existingOwner) {
            return $this->decision(
                $existingOwner,
                PancakeAssignmentMode::ExistingConversationOwner,
                __('integrations.pancake_assignment.existing_owner'),
                null,
                $pancakeUserKey,
                $actor ? 'extension' : 'webhook'
            );
        }

        $autoMode = $requestedMode === PancakeAssignmentMode::PendingPool->value
            ? PancakeAssignmentMode::PendingPool
            : PancakeAssignmentMode::AutoRouting;

        return $this->decision(
            null,
            $autoMode,
            $autoMode === PancakeAssignmentMode::PendingPool
                ? __('integrations.pancake_assignment.pending_pool')
                : __('integrations.pancake_assignment.auto_routing'),
            null,
            $pancakeUserKey,
            $actor ? 'extension' : 'webhook'
        );
    }

    public function canAssignSelectedSale(User $actor): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->allows(PermissionArea::Pancake, PermissionLevel::Full)
            || $actor->allows(PermissionArea::Leads, PermissionLevel::Full)) {
            return true;
        }

        return $actor->isSales()
            && in_array($actor->org_level, [OrgLevel::Supervisor, OrgLevel::Head], true);
    }

    /** @param array<string, mixed> $payload */
    public function requestedSaleId(array $payload): ?int
    {
        $value = Arr::get($payload, 'saleops.selected_sale_user_id')
            ?? Arr::get($payload, 'selected_sale_user_id')
            ?? Arr::get($payload, 'assigned_sale_user_id')
            ?? Arr::get($payload, 'sale_user_id');

        return filled($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $payload */
    public function requestedMode(array $payload): ?string
    {
        $mode = Arr::get($payload, 'saleops.assignment_mode') ?? Arr::get($payload, 'assignment_mode');
        $mode = is_string($mode) ? Str::of($mode)->lower()->replace('-', '_')->value() : null;

        return in_array($mode, array_map(fn (PancakeAssignmentMode $case) => $case->value, PancakeAssignmentMode::cases()), true)
            ? $mode
            : null;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    public function pancakeUserKey(array $payload, array $normalized): ?string
    {
        $pancake = is_array($normalized['pancake'] ?? null) ? $normalized['pancake'] : [];
        $candidates = [
            Arr::get($payload, 'pancake_user_id'),
            Arr::get($payload, 'pancake_user.id'),
            Arr::get($payload, 'user.id'),
            Arr::get($payload, 'assignee.id'),
            Arr::get($payload, 'creator.id'),
            Arr::get($payload, 'last_editor.id'),
            Arr::get($pancake, 'pancake_user_id'),
            Arr::get($pancake, 'sale_email'),
            Arr::get($payload, 'pancake_user_email'),
            Arr::get($payload, 'assignee.email'),
            Arr::get($payload, 'creator.email'),
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return $this->normalizeUserKey((string) $candidate);
            }
        }

        return null;
    }

    public function normalizeUserKey(string $value): string
    {
        return Str::of($value)->trim()->lower()->replaceMatches('/\s+/', ' ')->value();
    }

    protected function resolveSelectedSale(int $saleId, User $actor): User
    {
        if (! $this->canAssignSelectedSale($actor)) {
            throw ValidationException::withMessages([
                'selected_sale_user_id' => __('integrations.pancake_assignment.not_allowed_to_select_sale'),
            ]);
        }

        $sale = User::query()
            ->whereKey($saleId)
            ->where('company_id', $actor->company_id)
            ->first();

        if (! $sale?->isSales()) {
            throw ValidationException::withMessages([
                'selected_sale_user_id' => __('integrations.pancake_assignment.invalid_selected_sale'),
            ]);
        }

        if ($actor->isSales() && ! $actor->isAdmin() && ! $this->canSeeSale($actor, $sale)) {
            throw ValidationException::withMessages([
                'selected_sale_user_id' => __('integrations.pancake_assignment.sale_out_of_scope'),
            ]);
        }

        return $sale;
    }

    protected function canSeeSale(User $actor, User $sale): bool
    {
        if ((int) $actor->id === (int) $sale->id) {
            return true;
        }

        if (! $actor->isSales()) {
            return true;
        }

        if ($actor->allows(PermissionArea::Pancake, PermissionLevel::Full)
            || $actor->allows(PermissionArea::Leads, PermissionLevel::Full)) {
            return true;
        }

        if ($actor->org_level === OrgLevel::Head) {
            return true;
        }

        if ($actor->org_level === OrgLevel::Supervisor) {
            return (int) $sale->team_id === (int) $actor->team_id;
        }

        return false;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    protected function mappedSale(
        IntegrationConnection $connection,
        array $payload,
        array $normalized,
        string $pancakeUserKey,
    ): ?User {
        $pancake = is_array($normalized['pancake'] ?? null) ? $normalized['pancake'] : [];
        $shopId = (string) (Arr::get($pancake, 'shop_id') ?? Arr::get($payload, 'shop_id') ?? '');
        $pageId = (string) (Arr::get($pancake, 'page_id') ?? Arr::get($payload, 'page_id') ?? '');

        $query = PancakeUserMapping::query()
            ->with('internalUser')
            ->where('company_id', $connection->company_id)
            ->where('integration_connection_id', $connection->id)
            ->where('pancake_user_key', $pancakeUserKey)
            ->where('is_active', true);

        $query->where(function ($q) use ($shopId) {
            $q->whereNull('shop_id');
            if ($shopId !== '') {
                $q->orWhere('shop_id', $shopId);
            }
        });

        $query->where(function ($q) use ($pageId) {
            $q->whereNull('page_id');
            if ($pageId !== '') {
                $q->orWhere('page_id', $pageId);
            }
        });

        $mapping = $query
            ->orderByRaw('CASE WHEN shop_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN page_id IS NULL THEN 1 ELSE 0 END')
            ->latest('id')
            ->first();

        $sale = $mapping?->internalUser;
        if (! $sale?->isSales()) {
            return null;
        }

        $mapping->forceFill(['last_seen_at' => now()])->save();

        return $sale;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $normalized */
    protected function existingConversationOwner(
        IntegrationConnection $connection,
        array $payload,
        array $normalized,
    ): ?User {
        $pancake = is_array($normalized['pancake'] ?? null) ? $normalized['pancake'] : [];
        $conversationId = Arr::get($pancake, 'conversation_id') ?? Arr::get($payload, 'conversation_id');
        $phone = preg_replace('/\D+/', '', (string) ($normalized['customer_phone'] ?? '')) ?: null;

        if (filled($conversationId)) {
            $record = PancakeSyncRecord::query()
                ->with('order.saleUser')
                ->where('company_id', $connection->company_id)
                ->where('integration_connection_id', $connection->id)
                ->whereNotNull('order_id')
                ->where('metadata->conversation_id', (string) $conversationId)
                ->latest('id')
                ->first();

            $sale = $record?->order?->saleUser;
            if ($sale?->isSales()) {
                return $sale;
            }
        }

        if ($phone) {
            $order = Order::query()
                ->with('saleUser')
                ->where('customer_phone', $phone)
                ->whereNotNull('sale_user_id')
                ->latest('id')
                ->first();

            if ($order?->saleUser?->isSales()) {
                return $order->saleUser;
            }
        }

        return null;
    }

    /** @return array{sale: ?User, mode: string, reason: string, requested_sale_id: ?int, pancake_user_key: ?string, source: string} */
    protected function decision(
        ?User $sale,
        PancakeAssignmentMode $mode,
        string $reason,
        ?int $requestedSaleId,
        ?string $pancakeUserKey,
        string $source,
    ): array {
        return [
            'sale' => $sale,
            'mode' => $mode->value,
            'reason' => $reason,
            'requested_sale_id' => $requestedSaleId,
            'pancake_user_key' => $pancakeUserKey,
            'source' => $source,
        ];
    }
}
