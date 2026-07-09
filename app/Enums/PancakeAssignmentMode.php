<?php

namespace App\Enums;

enum PancakeAssignmentMode: string
{
    case Self = 'self';
    case SelectedSale = 'selected_sale';
    case PancakeUserMapping = 'pancake_user_mapping';
    case ExistingConversationOwner = 'existing_conversation_owner';
    case AutoRouting = 'auto_routing';
    case PendingPool = 'pending_pool';

    public function label(): string
    {
        return __('integrations.pancake_assignment_modes.'.$this->value);
    }
}
