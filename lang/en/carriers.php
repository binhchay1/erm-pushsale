<?php

return [
    'not_configured' => ':provider is not enabled or is missing configuration.',
    'create_rejected' => ':provider rejected the order.',
    'status_failed' => 'Could not fetch :provider status.',
    'cancel_failed' => 'Could not cancel the :provider order.',
    'no_waybill' => 'No :provider waybill code yet.',
    'created_status' => 'Created on :provider',
    'cancelled_status' => 'Cancelled on :provider',
    'action_unsupported' => ':provider action [:action] is not supported.',
    'print_token' => ':provider returned a label token — open the link from the response.',
    'jnt' => [
        'sync_via_webhook' => 'J&T: status syncs via webhook or tracking API — currently using the SaleOps webhook.',
        'fee_via_portal' => 'J&T: calculate fees via the portal or add the fee API later.',
        'cancel_via_api' => 'J&T: cancel orders via a dedicated API — contact operations to enable the endpoint.',
        'label_via_portal' => 'J&T: print labels via the portal.',
    ],
];
