<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Report fact coverage map
    |--------------------------------------------------------------------------
    | Historical report speed depends on daily fact tables that keep the same
    | dimensions as the filters users can choose in the UI. Row-level search and
    | EXISTS-style predicates still use live/detail queries, but all ordinary
    | analytical filters below are covered by daily facts.
    */

    'facts' => [
        'marketing_packets' => [
            'table' => 'report_daily_marketing_packet_facts',
            'date_column' => 'metric_date',
            'dimensions' => [
                'company_id', 'metric_date', 'marketing_source_id', 'parent_marketing_source_id',
                'landing_connection_id', 'landing_connection_source_id', 'marketer_user_id',
                'team_id', 'ad_channel', 'source_type', 'channel', 'utm_source', 'utm_campaign',
                'utm_medium', 'utm_term', 'utm_content', 'status',
            ],
            'metrics' => [
                'packet_count', 'primary_packet_count', 'upsale_packet_count', 'processed_count',
                'rejected_count', 'failed_count', 'no_phone_count', 'phone_count',
                'unique_phone_count', 'duplicate_packet_count',
            ],
        ],
        'leads' => [
            'table' => 'report_daily_lead_facts',
            'date_column' => 'metric_date',
            'dimensions' => [
                'company_id', 'metric_date', 'platform', 'status', 'packet_type',
                'marketing_source_id', 'landing_connection_id', 'landing_connection_source_id',
                'sale_user_id', 'marketer_user_id', 'team_id', 'warehouse_id',
                'delivery_status', 'reconciliation_status',
            ],
            'metrics' => ['packet_count', 'lead_count', 'processed_count', 'failed_count', 'duplicate_count', 'review_count'],
        ],
        'orders' => [
            'table' => 'report_daily_order_facts',
            'date_column' => 'metric_date',
            'date_basis_column' => 'date_basis',
            'dimensions' => [
                'company_id', 'metric_date', 'date_basis', 'sale_user_id', 'marketer_user_id',
                'team_id', 'marketing_source_id', 'landing_connection_id', 'warehouse_id',
                'shipping_provider', 'shipping_method', 'customer_type', 'duplicate_phone_status',
                'warehouse_care_status', 'printed_status', 'deposit_status', 'delivery_status',
                'reconciliation_status', 'operation_stage', 'operation_result', 'closing_status',
            ],
            'metrics' => [
                'order_count', 'closed_order_count', 'open_order_count', 'delivered_order_count',
                'partial_delivery_count', 'returned_order_count', 'cancelled_order_count',
                'upsell_order_count', 'contact_count', 'contacted_order_count', 'gross_sales',
                'discount_amount', 'vat_amount', 'shipping_collected', 'order_value',
                'recognized_revenue', 'deposit_amount', 'amount_to_collect', 'settled_cod_amount',
                'shipping_cost', 'closed_shipping_cost', 'return_fee', 'compensation_amount',
                'net_cashflow',
            ],
        ],
        'products' => [
            'table' => 'report_daily_product_facts',
            'date_column' => 'metric_date',
            'date_basis_column' => 'date_basis',
            'dimensions' => [
                'company_id', 'metric_date', 'date_basis', 'product_id', 'parent_product_id',
                'sale_user_id', 'marketer_user_id', 'team_id', 'marketing_source_id',
                'landing_connection_id', 'warehouse_id', 'item_origin', 'is_upsell',
                'delivery_status', 'reconciliation_status',
            ],
            'metrics' => [
                'order_count', 'line_count', 'quantity', 'gross_sales', 'discount_amount',
                'net_sales', 'cost_of_goods', 'recognized_revenue',
            ],
        ],
        'cashflow' => [
            'table' => 'report_daily_cashflow_facts',
            'date_column' => 'metric_date',
            'dimensions' => ['company_id', 'metric_date', 'event_basis', 'marketing_source_id', 'warehouse_id', 'shipping_provider'],
            'metrics' => [
                'shipment_count', 'cod_mismatch_count', 'cod_expected', 'cod_collected',
                'cod_remitted', 'shipping_fee', 'return_fee', 'cod_fee', 'insurance_fee',
                'other_fee', 'compensation_amount', 'net_cashflow',
            ],
        ],
        'inventory' => [
            'table' => 'report_daily_inventory_facts',
            'date_column' => 'metric_date',
            'dimensions' => ['company_id', 'metric_date', 'warehouse_id', 'product_id', 'movement_type'],
            'metrics' => ['movement_count', 'quantity_in', 'quantity_out', 'quantity_net', 'value_in', 'value_out'],
        ],
    ],

    'live_only_filters' => [
        'search',
        'order_id',
        'tracking_alert',
        'care_status',
        'operation_activity_status',
        'min_product_quantity',
        'max_product_quantity',
        'hide_no_phone',
        'no_closing_date_limit',
    ],

    'hybrid_policy' => [
        'closed_days' => 'facts',
        'open_today' => 'live',
        'missing_fact_days' => 'chunked_live_fallback',
        'detail_rows' => 'live_paginated',
    ],
];
