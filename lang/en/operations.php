<?php

return [
    'all' => 'All',
    'customer_interactions' => [
        'phone_required' => 'The customer does not have a valid phone number.',
        'read_only' => 'You have read-only access. Full Customer permission is required to send messages.',
        'message_required' => 'Please enter a message.',
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
