<?php

return [

    'brand' => [
        'name' => 'ERM SaleOps',
        'tagline' => 'Hệ thống điều hành bán hàng & vận hành',
        'short' => 'SaleOps',
    ],

    'themes' => [
        'brand' => [
            'label' => 'SaleOps Blue',
            'description' => 'Xanh chủ đạo — mặc định',
            'primary' => 'oklch(0.546 0.215 262.881)',
            'primary_foreground' => 'oklch(0.985 0 0)',
            'chart' => ['#3b82f6', '#60a5fa', '#93c5fd'],
        ],
        'ocean' => [
            'label' => 'Ocean Teal',
            'description' => 'Xanh ngọc hiện đại',
            'primary' => 'oklch(0.55 0.12 195)',
            'primary_foreground' => 'oklch(0.99 0 0)',
            'chart' => ['#0d9488', '#2dd4bf', '#5eead4'],
        ],
        'sunset' => [
            'label' => 'Sunset',
            'description' => 'Cam ấm — nhấn conversion',
            'primary' => 'oklch(0.62 0.19 45)',
            'primary_foreground' => 'oklch(0.99 0 0)',
            'chart' => ['#ea580c', '#fb923c', '#fdba74'],
        ],
        'violet' => [
            'label' => 'Violet Pro',
            'description' => 'Tím sang — báo cáo CEO',
            'primary' => 'oklch(0.52 0.22 295)',
            'primary_foreground' => 'oklch(0.99 0 0)',
            'chart' => ['#7c3aed', '#a78bfa', '#c4b5fd'],
        ],
    ],

    'lead_routing' => [
        'strategy' => env('LEAD_ROUTING_STRATEGY', 'round_robin'),
        'duplicate_window_days' => (int) env('LEAD_DUPLICATE_WINDOW_DAYS', 30),
    ],

];
