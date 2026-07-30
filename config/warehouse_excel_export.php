<?php

/**
 * FAB warehouse/accounting Excel export profiles.
 * Columns come from SettingExcelAccounting (feature settings) — not hardcoded per tenant.
 */
return [
    'max_rows' => 5000,
    'throttle_per_minute' => 3,
    'columns_setting' => 'SettingExcelAccounting',
    'permission_setting' => 'SettingExcelPermission',

    /** Column keys that force one Excel row per order line. */
    'line_item_columns' => [
        'DonHangTenSanPham',
        'DonHangTenSanPham_SoLuong',
        'DonHangMaSanPham',
        'DonHangMaSanPham_SoLuong',
        'DonHangDonGia',
        'DonHangSoLuong',
        'DonHangCanNang',
        'DonHangThanhTien',
        'DonHangChietKhauSanPham',
        'DonHangPTCKSP',
        'DonHangPTCKSP2',
        'DonHangSoTienCKSP',
        'DonHangSoTienCKSP2',
        'DonHangTenCombo',
        'DonHangMaCombo',
    ],

    'profiles' => [
        'standard' => [
            'key' => 'standard',
            'title' => 'Xuất Excel kiểu 1',
            'tone' => 'primary',
            // filename_prefix omitted → WarehouseOrderExcelExportService uses config('app.name') slug
            'extension' => 'xls',
        ],
        'shipping' => [
            'key' => 'shipping',
            'title' => 'Xuất Excel kiểu 2',
            'tone' => 'success',
            'extension' => 'xls',
        ],
        'accounting' => [
            'key' => 'accounting',
            'title' => 'Xuất Excel kiểu 3',
            'tone' => 'warning',
            'extension' => 'xlsx',
        ],
    ],
];
