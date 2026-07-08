<?php

namespace App\Services\CustomerInteractions;

use App\Enums\UserRole;
use App\Models\CustomerInternalMessage;
use App\Models\User;
use Illuminate\Support\Collection;

final class CustomerInternalMessagePresenter
{
    /** @return array<string, mixed> */
    public static function toArray(CustomerInternalMessage $message, ?User $viewer = null): array
    {
        $role = $message->author_role;

        return [
            'id' => (string) $message->id,
            'message' => $message->message,
            'authorId' => $message->author_user_id !== null ? (string) $message->author_user_id : null,
            'authorName' => $message->author_name ?? $message->author?->name ?? __('operations.customer_interactions.system_actor'),
            'authorRole' => filled($role) ? (UserRole::tryFrom($role)?->label() ?? $role) : null,
            'authorOrgLevel' => $message->author?->orgLevelLabel(),
            'orderCode' => $message->order?->order_code,
            'createdAt' => $message->created_at?->toIso8601String(),
            'isMine' => $viewer !== null && $message->author_user_id === $viewer->id,
        ];
    }

    /**
     * @param  Collection<int, CustomerInternalMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $messages, ?User $viewer = null): array
    {
        return $messages->map(fn (CustomerInternalMessage $message) => self::toArray($message, $viewer))->values()->all();
    }
}
