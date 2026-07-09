<?php

namespace App\Enums;

enum InboundEventSource: string
{
    case LeadWebhook = 'lead_webhook';
    case LandingWebhook = 'landing_webhook';
    case ShippingWebhook = 'shipping_webhook';
    case PancakeChatWebhook = 'pancake_chat_webhook';

    public function label(): string
    {
        return match ($this) {
            self::LeadWebhook => __('system_monitor.source_lead'),
            self::LandingWebhook => __('system_monitor.source_landing'),
            self::ShippingWebhook => __('system_monitor.source_shipping'),
            self::PancakeChatWebhook => __('system_monitor.source_pancake_chat'),
        };
    }
}
