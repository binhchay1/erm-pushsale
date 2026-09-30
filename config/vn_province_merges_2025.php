<?php

/**
 * Sáp nhập tỉnh 2025: 63 tỉnh cũ → 34 tỉnh mới.
 *
 * Địa chỉ trong hệ thống (kho, đơn) đã theo cấu trúc 2 cấp mới (tỉnh → phường/xã),
 * còn API địa chỉ của một số hãng vận chuyển vẫn trả cấu trúc 3 cấp cũ.
 * Map này cho phép tìm lại tỉnh cũ chứa một phường/xã khi tên tỉnh đã đổi.
 *
 * Key = tên tỉnh mới, value = danh sách tên tỉnh cũ đã gộp vào (gồm cả chính nó).
 * Tỉnh không sáp nhập thì không cần liệt kê.
 */
return [
    'Tuyên Quang' => ['Tuyên Quang', 'Hà Giang'],
    'Lào Cai' => ['Lào Cai', 'Yên Bái'],
    'Thái Nguyên' => ['Thái Nguyên', 'Bắc Kạn'],
    'Phú Thọ' => ['Phú Thọ', 'Vĩnh Phúc', 'Hòa Bình'],
    'Bắc Ninh' => ['Bắc Ninh', 'Bắc Giang'],
    'Hưng Yên' => ['Hưng Yên', 'Thái Bình'],
    'Hải Phòng' => ['Hải Phòng', 'Hải Dương'],
    'Ninh Bình' => ['Ninh Bình', 'Hà Nam', 'Nam Định'],
    'Quảng Trị' => ['Quảng Trị', 'Quảng Bình'],
    'Đà Nẵng' => ['Đà Nẵng', 'Quảng Nam'],
    'Quảng Ngãi' => ['Quảng Ngãi', 'Kon Tum'],
    'Gia Lai' => ['Gia Lai', 'Bình Định'],
    'Khánh Hòa' => ['Khánh Hòa', 'Ninh Thuận'],
    'Đắk Lắk' => ['Đắk Lắk', 'Phú Yên'],
    'Lâm Đồng' => ['Lâm Đồng', 'Đắk Nông', 'Bình Thuận'],
    'Đồng Nai' => ['Đồng Nai', 'Bình Phước'],
    'Hồ Chí Minh' => ['Hồ Chí Minh', 'Bình Dương', 'Bà Rịa - Vũng Tàu'],
    'Tây Ninh' => ['Tây Ninh', 'Long An'],
    'Cần Thơ' => ['Cần Thơ', 'Sóc Trăng', 'Hậu Giang'],
    'Vĩnh Long' => ['Vĩnh Long', 'Bến Tre', 'Trà Vinh'],
    'Đồng Tháp' => ['Đồng Tháp', 'Tiền Giang'],
    'Cà Mau' => ['Cà Mau', 'Bạc Liêu'],
    'An Giang' => ['An Giang', 'Kiên Giang'],
];
