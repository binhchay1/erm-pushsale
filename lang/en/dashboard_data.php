<?php

return [
    'other' => 'Other',
    'funnel' => [
        'lead' => 'Leads',
        'lead_ingest' => 'Lead ingest',
        'order' => 'Orders',
        'closed' => 'Closed',
        'delivered' => 'Delivered',
        'paid' => 'Paid',
        'allocated' => 'Allocated',
        'contacted' => 'Contacted',
        'delivered_paid' => 'Delivered/Paid',
        'in_progress' => 'In progress',
        'processed' => 'Processed',
        'failed_leads' => 'Failed leads',
    ],
    'delivery' => [
        'waiting_waybill' => 'Awaiting waybill',
        'pending_pickup' => 'Awaiting pickup',
        'delivering' => 'Out for delivery',
    ],
    'routing' => [
        'pending' => 'Pending routing',
        'failed' => 'Failed',
        'duplicate' => 'Duplicate',
    ],
    'alerts' => [
        'failed_orders' => 'Failed / returned orders',
        'failed_orders_desc' => 'Review delivery status.',
        'cod_mismatch' => 'COD mismatch',
        'cod_mismatch_desc' => 'Carrier webhook reported mismatched amount.',
        'failed_leads' => 'Failed leads',
        'failed_leads_desc' => 'Lead ingestion failed — retry needed.',
        'waiting_waybill' => 'Awaiting waybill',
        'waiting_waybill_desc' => 'Orders waiting for shipment creation.',
    ],
];
