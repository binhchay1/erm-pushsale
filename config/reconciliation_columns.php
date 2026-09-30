<?php

/**
 * Cột file đối soát tay (kho / kế toán). Thêm tên cột mới ở đây khi hãng đổi mẫu,
 * không cần sửa parser. Khớp theo chữ thường, bỏ dấu.
 *
 * exact: ô tiêu đề trùng hẳn.
 * contains: ô tiêu đề chứa cụm này (cụm dài hơn được ưu tiên).
 */
return [
    'header_scan_rows' => 40,
    // Một ô tình cờ trùng tên cột (vd. ghi chú "note") chưa đủ để nhận là dòng tiêu đề.
    'min_header_hits' => 2,

    'columns' => [
        'order_code' => [
            'exact' => ['ma don', 'ma don hang', 'order code', 'order_code', 'madon', 'ma pushsale', 'ma don noi bo'],
        ],
        'tracking_number' => [
            'exact' => ['ma giao van', 'ma van don', 'tracking', 'tracking number', 'tracking_number', 'waybill', 'billcode', 'ma bill'],
            'contains' => ['ma giao van', 'ma van don'],
        ],
        'reconciliation_status' => [
            'exact' => ['trang thai doi soat', 'reconciliation status', 'reconciliation_status', 'trang thai cap nhat', 'dsnb'],
            'contains' => ['trang thai doi soat'],
        ],
        'delivery_outcome' => [
            'exact' => ['trang thai', 'trang thai giao', 'trang thai giao hang', 'status'],
        ],
        'excel_total' => [
            'exact' => ['tong tien', 'tong tien don', 'order total', 'order_total', 'total'],
            'contains' => ['tong tien don', 'tong tien'],
        ],
        'excel_cod' => [
            'exact' => ['tien thu ho', 'thu ho', 'cod goc', 'amount to collect', 'amount_to_collect', 'cod', 'pick money'],
            'contains' => ['tien thu ho', 'cod goc'],
        ],
        'note' => [
            'exact' => ['ghi chu', 'note', 'logs ly do', 'ly do'],
            'contains' => ['ghi chu', 'ly do'],
        ],
    ],

    /**
     * Giá trị ô trạng thái (không phải tên cột) → reconciliation_status.
     * Cụm giao/hoàn thành công là dòng đã có trên bảng kê quyết toán.
     * Trạng thái đang giao để trống, không ghi đè đối soát.
     */
    'status_aliases' => [
        'da doi soat' => 'reconciled',
        'doi soat' => 'reconciled',
        'settled' => 'reconciled',
        'reconciled' => 'reconciled',
        'chua doi soat' => 'pending',
        'cho doi soat' => 'pending',
        'pending' => 'pending',
        'giao hang thanh cong' => 'reconciled',
        'hoan hang thanh cong' => 'reconciled',
        'da giao hang' => 'reconciled',
        'da giao' => 'reconciled',
        'da hoan' => 'reconciled',
        'delivered' => 'reconciled',
        'returned' => 'reconciled',
    ],
];
