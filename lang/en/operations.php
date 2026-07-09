<?php

return [
    'all' => 'All',
    'customer_interactions' => [
        'phone_required' => 'The customer does not have a valid phone number.',
        'pancake_read_only' => 'You have read-only access. Only Admin, Sales, or users with Full Pancake customer chat permission can message customers.',
        'read_only' => 'You have read-only access. Full Customer permission is required to send messages.',
        'message_required' => 'Please enter a message.',
        'pancake_missing_conversation' => 'The Pancake webhook does not contain a valid conversation_id.',
        'system_actor' => 'System',
        'history_before_tracking' => 'This current-state snapshot predates detailed operation-history tracking.',
        'history_actions' => [
            'initial_snapshot' => 'Current state',
            'call' => 'Call logged',
            'status_updated' => 'Operation updated',
            'order_updated' => 'Order information updated',
            'order_closed' => 'Order closed',
        ],
    ],
    'warehouse_tabs' => [
        'waiting' => 'Awaiting waybill',
        'pickup' => 'Pickup',
        'delivering' => 'Delivering',
        'delivered' => 'Delivered',
        'paid' => 'Paid',
        'returns' => 'Returns',
        'cancelled' => 'Cancelled',
    ],
];
