<?php

return [
    [
        'id' => 'tab_1',
        'index' => 1,
        'title' => 'A. Chốt đơn, đăng đơn',
        'rows' => [
            [
                'key' => 'row_SettingGhiChuGiaoHangSale',
                'label' => '1. Ghi chú giao hàng dành cho sale',
                'controls' => [
                    [
                        'key' => 'SettingGhiChuGiaoHangSale',
                        'type' => 'long_text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => 'Tối đa 500 ký tự. Ví dụ mẫu : Cho xem không cho thử ; Cho xem và cho thử',
                        'default' => 'Cho xem hàng, không được thử, không bóc seal. Ko giao được liên hệ Shop. Khách hoàn đơn thu 30K ship, Ko tự ý hoàn, Ko GỌI SHOP SẼ BỒI HOÀN',
                    ],
                ],
                'help' => 'Tạo ra các mẫu ghi chú sẵn để sale lựa chọn nhanh khi chốt đơn',
                'note' => '',
            ],
            [
                'key' => 'row_SettingMaDonPrefix',
                'label' => '2. Mã đơn prefix',
                'controls' => [
                    [
                        'key' => 'SettingMaDonPrefix',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Mã đơn prefix',
                        'max_length' => 6,
                        'help' => 'Tối đa 6 ký tự, chữ cái không có ký tự đặc biệt, không khoảng trắng, không có dấu .',
                        'default' => 'PS',
                    ],
                ],
                'help' => 'Mã đơn tạo ra khi chốt đơn sẽ luôn bắt đầu bằng \'Mã đơn prefix\'',
                'note' => '',
            ],
            [
                'key' => 'row_SettingMaDonCoDinh',
                'label' => '3. Không cho phép thay đổi mã đơn',
                'controls' => [
                    [
                        'key' => 'SettingMaDonCoDinh',
                        'type' => 'boolean',
                        'label' => 'Không cho phép bộ phận vận đơn thay đổi mã đơn',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Khóa chức năng thay đổi mã đơn (đối với các đơn chưa đăng) của bộ phận vận đơn',
                'note' => '',
            ],
            [
                'key' => 'row_SettingNgayChotDonCoDinh',
                'label' => '4. Không thay đổi ngày chốt đơn',
                'controls' => [
                    [
                        'key' => 'SettingNgayChotDonCoDinh',
                        'type' => 'boolean',
                        'label' => 'Không thay đổi ngày chốt đơn',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định khi một đơn hàng được chốt nhiều lần (hủy/chốt lại) ngày chốt đơn sẽ tính là lần cuối cùng chốt. Lựa chọn này cho phép giữ nguyên thời điểm chốt đơn lần đầu là ngày chốt đơn.',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoDangDon',
                'label' => '5. Cho phép kho đăng đơn',
                'controls' => [
                    [
                        'key' => 'SettingKhoDangDon',
                        'type' => 'boolean',
                        'label' => 'Cho phép kho đăng đơn',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoHuyDangDon',
                'label' => '6. Cho phép kho hủy đăng đơn',
                'controls' => [
                    [
                        'key' => 'SettingKhoHuyDangDon',
                        'type' => 'boolean',
                        'label' => 'Cho phép kho hủy đăng đơn',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKeToanDangDon',
                'label' => '7. Cho phép kế toán đăng',
                'controls' => [
                    [
                        'key' => 'SettingKeToanDangDon',
                        'type' => 'boolean',
                        'label' => 'Cho phép kế toán đăng đơn',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKeToanHuyDangDon',
                'label' => '8. Cho phép kế toán hủy đăng đơn',
                'controls' => [
                    [
                        'key' => 'SettingKeToanHuyDangDon',
                        'type' => 'boolean',
                        'label' => 'Cho phép kế toán hủy đăng đơn',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoDocLap',
                'label' => '9. Bộ phận kho vận chỉ nhìn thấy kho mình quản lý',
                'controls' => [
                    [
                        'key' => 'SettingKhoDocLap',
                        'type' => 'boolean',
                        'label' => 'Bộ phận kho vận chỉ nhìn thấy kho mình quản lý',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định tài khoản kho sẽ có quyền xem dữ liệu tất cả các kho. Lựa chọn này sẽ giới hạn tài khoản kho chỉ thấy dữ liệu các kho do mình quản lý',
                'note' => '',
            ],
            [
                'key' => 'row_SettingChotDonChuyenChamSoc',
                'label' => '10. Chuyển CSKH khi',
                'controls' => [
                    [
                        'key' => 'SettingChotDonChuyenChamSoc',
                        'type' => 'select',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '0',
                        'options' => [
                            [
                                'value' => '0',
                                'label' => 'Đếm ngược CSKH khi đăng đơn',
                            ],
                            [
                                'value' => '1',
                                'label' => 'Đếm ngược CSKH khi chốt đơn',
                            ],
                            [
                                'value' => '2',
                                'label' => 'Đếm ngược CSKH theo cấu hình giao hàng',
                            ],
                        ],
                    ],
                ],
                'help' => 'Mặc định bộ đếm ngược sẽ đếm thời gian chuyển sang CSKH sau khi đơn được đăng. Lựa chọn này cho phép bộ đếm ngược bắt đầu ngay sau khi đơn hàng được chốt hoặc theo cấu hình giao hàng',
                'note' => 'Cấu hình giao hàng',
            ],
            [
                'key' => 'row_SettingGiaoVanCapNhatPTGH',
                'label' => '11. Bộ phận kho sửa thông tin giao vận của đơn hàng',
                'controls' => [
                    [
                        'key' => 'SettingGiaoVanCapNhatPTGH',
                        'type' => 'boolean',
                        'label' => 'Cho phép bộ phận kho sửa thông tin giao vận của đơn hàng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => 'Mặc định tài khoản kho sẽ không thể sửa thông tin của đơn hàng. Lựa chọn này cho phép bộ phận kho sửa kho, họ tên, số điện thoại, đơn vị giao vận, địa chỉ giao hàng, ghi chú của đơn hàng',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSuaSanPham',
                'label' => '12. Bộ phận kho sửa thông tin sản phẩm của đơn hàng',
                'controls' => [
                    [
                        'key' => 'SettingKhoSuaSanPham',
                        'type' => 'boolean',
                        'label' => 'Cho phép bộ phận kho sửa thông tin sản phẩm của đơn hàng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => 'Mặc định tài khoản kho sẽ không thể sửa thông tin sản phẩm của đơn hàng. Lựa chọn này cho phép bộ phận kho sửa sản phẩm của đơn hàng',
                'note' => '',
            ],
            [
                'key' => 'row_SettingDangDonInNgay',
                'label' => '13. Đăng đơn xong in ngay',
                'controls' => [
                    [
                        'key' => 'SettingDangDonInNgay',
                        'type' => 'boolean',
                        'label' => 'Đăng đơn xong in ngay',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Lựa chọn này cho phép tự động mở thêm hộp thoại in đơn ngay sau khi sử dụng chức năng in đơn',
                'note' => '',
            ],
            [
                'key' => 'row_SettingDangDonNguoiNhanSDT',
                'label' => '14. Cố định SĐT người nhận khi đăng đơn',
                'controls' => [
                    [
                        'key' => 'SettingDangDonNguoiNhanSDT',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => 20,
                        'help' => 'Tối đa 20 kí tự, sử dụng trong trường hợp không muốn công khai số điện thoại cho bên đối tác giao hàng.',
                        'default' => '',
                    ],
                ],
                'help' => 'Lựa chọn này cho phép khi đăng đơn hàng lên hệ thống của đơn vị giao vận sẽ thay thế SĐT khách nhận hàng bằng SĐT thiết lập. Sử dụng trong trường hợp không muốn lộ thông tin SĐT của đơn hàng với đơn vị giao vận',
                'note' => '',
            ],
            [
                'key' => 'row_SettingDangDonNguoiGui',
                'label' => '15. Đăng đơn người gửi',
                'controls' => [
                    [
                        'key' => 'SettingDangDonNguoiGui',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => 200,
                        'help' => 'Tối đa 200 ký tự',
                        'default' => '',
                    ],
                ],
                'help' => '',
                'note' => 'Áp dụng cho Giao hàng tiết kiệm, Viettel post, Giao hàng nhanh, VNPOST, J&T, BEST',
            ],
            [
                'key' => 'row_SettingKhoDoiSoat',
                'label' => '16. Cho phép bộ phận kho đối soát',
                'controls' => [
                    [
                        'key' => 'SettingKhoDoiSoat',
                        'type' => 'boolean',
                        'label' => 'Cho phép bộ phận kho đối soát',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => 'Cho phép bộ phận kho đối soát',
            ],
            [
                'key' => 'row_SettingInDonAnhChim',
                'label' => '17. Cho phép sử dụng logo chìm với phiếu gửi hàng, hóa đơn bán hàng',
                'controls' => [
                    [
                        'key' => 'SettingInDonAnhChim',
                        'type' => 'boolean',
                        'label' => 'Cho phép sử dụng logo chìm với phiếu gửi hàng, hóa đơn bán hàng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => 'Cho phép sử dụng logo chìm với phiếu gửi hàng, hóa đơn bán hàng',
            ],
            [
                'key' => 'row_KhoVanChiViewDonDangCare',
                'label' => '18. Kho vận chỉ nhìn thấy đơn mình được care',
                'controls' => [
                    [
                        'key' => 'KhoVanChiViewDonDangCare',
                        'type' => 'boolean',
                        'label' => 'Kho vận chỉ nhìn thấy đơn mình được care',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => 'Kho vận chỉ nhìn thấy đơn mình được care',
            ],
            [
                'key' => 'row_XuatHoaDonDienTuTuDong',
                'label' => '19. Tự động xuất hóa đơn điện tử',
                'controls' => [
                    [
                        'key' => 'XuatHoaDonDienTuTuDong',
                        'type' => 'boolean',
                        'label' => 'Tự động xuất hóa đơn điện tử sau:',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                    [
                        'key' => 'XuatHoaDonDienTuThoiDiemHour',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => 200,
                        'help' => 'Cài đặt thời gian bắt đầu thực hiện xuất hóa đơn điện tử trong ngày. Nhập số nguyên từ 0 - 21',
                        'default' => '0',
                    ],
                ],
                'help' => '',
                'note' => 'Cấu hình trạng thái giao hàng',
            ],
            [
                'key' => 'row_UseEmailVaMST',
                'label' => '20. Sử dụng email và MST',
                'controls' => [
                    [
                        'key' => 'UseEmailVaMST',
                        'type' => 'boolean',
                        'label' => 'Sử dụng email và mã số thuế',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => 'Sử dụng email và mã số thuế',
            ],
        ],
    ],
    [
        'id' => 'tab_2',
        'index' => 2,
        'title' => 'B. Chia số, đội nhóm',
        'rows' => [
            [
                'key' => 'row_SettingTeamWork',
                'label' => '1. Làm việc theo nhóm',
                'controls' => [
                    [
                        'key' => 'SettingTeamWork',
                        'type' => 'boolean',
                        'label' => 'Làm việc theo nhóm',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định sau khi sale chốt, đơn sẽ được chuyển đến bộ phận CSKH (kể cả đã chia đội nhóm). Lựa chọn chạy cho phép sau khi chốt đơn sẽ được chuyển đến \'đội/nhóm\' CSKH tương ứng với \'đội/nhóm\' của sale',
                'note' => '',
            ],
            [
                'key' => 'row_SettingTeamWorkSaleChiaThuCong',
                'label' => '2. Sale leader chia data thủ công',
                'controls' => [
                    [
                        'key' => 'SettingTeamWorkSaleChiaThuCong',
                        'type' => 'boolean',
                        'label' => 'Sale leader chia data thủ công',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Lựa chọn này cho phép chia số mới cho lần lượt các sale leader (được thiết lập trong nguồn dữ liệu, sản phẩm...)',
                'note' => '',
            ],
            [
                'key' => 'row_SoBanGhiCSKHChiaAdmin',
                'label' => '3. Số bản ghi CSKH chia admin',
                'controls' => [
                    [
                        'key' => 'SoBanGhiCSKHChiaAdmin',
                        'type' => 'boolean',
                        'label' => 'Số bản ghi CSKH chia admin',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Lựa chọn này sẽ luôn chia số bản ghi CSKH cho Admin',
                'note' => '',
            ],
            [
                'key' => 'row_SettingTeamWorkCSKHChiaThuCong',
                'label' => '3.1 CSKH leader chia data thủ công',
                'controls' => [
                    [
                        'key' => 'SettingTeamWorkCSKHChiaThuCong',
                        'type' => 'boolean',
                        'label' => 'CSKH leader chia data thủ công',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Lựa chọn này cho phép chia số chăm sóc cho lần lượt các CSKH leader (được thiết lập trong nguồn dữ liệu, sản phẩm...)',
                'note' => '',
            ],
            [
                'key' => 'row_SettingSaleXoaSoTrung',
                'label' => '4. Cho phép sale xóa số trùng từ nguồn dữ liệu',
                'controls' => [
                    [
                        'key' => 'SettingSaleXoaSoTrung',
                        'type' => 'boolean',
                        'label' => 'Cho phép sale xóa số trùng từ nguồn dữ liệu',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Lựa chọn này cho phép sale xóa số trùng từ nguồn dữ liệu',
                'note' => '',
            ],
            [
                'key' => 'row_KhachCuChiaTheoSoMoi',
                'label' => '5. Chia số khách cũ cho sale',
                'controls' => [
                    [
                        'key' => 'KhachCuChiaTheoSoMoi',
                        'type' => 'boolean',
                        'label' => 'Chia số khách cũ cho sale',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => 'Số khách cũ sẽ được chia lại cho Sale (cấu hình trong sản phẩm) thay vì chia cho CSKH (cấu hình trong sản phẩm), ưu tiên chia cho Sale gần nhất nhận số. Nếu sale nhận số gần nhất không được nhận data hoặc bị khóa sẽ thực hiện chia data cho sale khác',
            ],
            [
                'key' => 'row_SettingDiffLandingNewContact',
                'label' => '6. Số từ nguồn dữ liệu khác sản phẩm coi là số mới (không báo trùng)',
                'controls' => [
                    [
                        'key' => 'SettingDiffLandingNewContact',
                        'type' => 'boolean',
                        'label' => 'Số từ nguồn dữ liệu khác sản phẩm coi là số mới (không tính là trùng số, khách cũ)',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định dữ liệu cùng số điện thoại có thể là trùng hoặc khách cũ. Lựa chọn này cho phép nếu dữ liệu về từ 2 nguồn dữ liệu (nguồn) khác nhau và không cùng sản phẩm thì sẽ coi là số mới (Chỉ áp dụng số về tự động từ nguồn dữ liệu, Facebook)',
                'note' => '',
            ],
            [
                'key' => 'row_Enable_MKT_ChiaSo_NhapThuCong',
                'label' => '7. Cho phép MTK chia số khi nhập thủ công',
                'controls' => [
                    [
                        'key' => 'Enable_MKT_ChiaSo_NhapThuCong',
                        'type' => 'boolean',
                        'label' => 'Cho phép MTK chia số khi nhập thủ công',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_ChiaSo_FixUuTienSale',
                'label' => '8. Nếu sale nhận số gần nhất không nằm trong danh ưu tiên thì chia sale khác',
                'controls' => [
                    [
                        'key' => 'ChiaSo_FixUuTienSale',
                        'type' => 'boolean',
                        'label' => 'Nếu sale nhận số gần nhất không nằm trong danh ưu tiên (cấu hình trong nguồn dữ liệu) thì chia sale khác nằm trong danh sách ưu tiên',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_ChiaSo_SoTrung_SaleKoDuocNhanSoThiChiaSaleKhac',
                'label' => '9. Lựa chọn chia số trùng',
                'controls' => [
                    [
                        'key' => 'ChiaSo_SoTrung_SaleKoDuocNhanSoThiChiaSaleKhac',
                        'type' => 'boolean',
                        'label' => 'Khi trùng số nếu sale không được nhận dữ liệu thì chia sale khác',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Khi chia số trùng nếu sale nhận data gần nhất không được cấu hình nhận dữ liệu thì chia cho sale khác',
                'note' => '',
            ],
            [
                'key' => 'row_MacDinhDungChiaSoV2',
                'label' => '10. Mặc định dùng chia số V2',
                'controls' => [
                    [
                        'key' => 'MacDinhDungChiaSoV2',
                        'type' => 'boolean',
                        'label' => 'Mặc định dùng chia số V2, bắt buộc phải chọn cấu hình chia số',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Khi thiết lập mặc định chia số V2 thì khi tạo nguồn dữ liệu sẽ bắt buộc phải chọn cấu hình chia số',
                'note' => '',
            ],
            [
                'key' => 'row_DungChiaSoCu_ChiSaleUuTienTaoSoThuCong',
                'label' => '11. Dùng chia số cũ, chỉ cho phép sale được ưu tiên trong nguồn dữ liệu được tạo số thủ công',
                'controls' => [
                    [
                        'key' => 'DungChiaSoCu_ChiSaleUuTienTaoSoThuCong',
                        'type' => 'boolean',
                        'label' => 'Dùng chia số cũ, chỉ cho phép sale được ưu tiên trong nguồn dữ liệu được tạo số thủ công',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Khi nguồn dữ liệu dùng chia số cũ, chỉ cho phép sale được ưu tiên trong nguồn dữ liệu được tạo số thủ công',
                'note' => '',
            ],
            [
                'key' => 'row_EnableSaleLeaderXoaSoTrungSaleMember',
                'label' => '12. Cho phép sale leader xóa số trùng của sale thành viên',
                'controls' => [
                    [
                        'key' => 'EnableSaleLeaderXoaSoTrungSaleMember',
                        'type' => 'boolean',
                        'label' => 'Cho phép sale leader xóa số trùng của sale thành viên',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => 'Cho phép sale leader xóa số trùng của sale thành viên',
                'note' => '',
            ],
            [
                'key' => 'row_EnableChiaSoXoaSoTrung',
                'label' => '13. Cho phép chia số xóa số trùng',
                'controls' => [
                    [
                        'key' => 'EnableChiaSoXoaSoTrung',
                        'type' => 'boolean',
                        'label' => 'Cho phép chia số xóa số trùng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => 'Cho phép chia số xóa số trùng',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_3',
        'index' => 3,
        'title' => 'C. Cấu hình chung',
        'rows' => [
            [
                'key' => 'row_SettingEnableMultipleLogin',
                'label' => '1. Cho phép đăng nhập tài khoản login từ nhiều IP',
                'controls' => [
                    [
                        'key' => 'SettingEnableMultipleLogin',
                        'type' => 'boolean',
                        'label' => 'Cho phép đăng nhập tài khoản từ nhiều IP',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => 'Mặc định người dùng sẽ không thể đăng nhập từ nhiều IP. Lựa chọn này cho phép nhiều người dùng có thể đăng nhập chung một tài khoản từ nhiều IP cùng một lúc',
                'note' => '',
            ],
            [
                'key' => 'row_2_Bu_c_thay_i_m_t_kh_u_to_n_n_v',
                'label' => '2. Buộc thay đổi mật khẩu toàn đơn vị',
                'controls' => [],
                'help' => 'Lựa chọn này sẽ bắt buộc thay đổi mật khẩu đối với các tài khoản trong đơn vị (không bao gồm tài khoản hiện tại)',
                'note' => '',
            ],
            [
                'key' => 'row_SettingDoanhSoTruChietKhau',
                'label' => '3. Tính doanh số trừ chiết khấu',
                'controls' => [
                    [
                        'key' => 'SettingDoanhSoTruChietKhau',
                        'type' => 'boolean',
                        'label' => 'Tính doanh số trừ chiết khấu',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingAdminChangeLandingMKT',
                'label' => '4. Cho phép Admin đơn vị đổi MKT của Nguồn dữ liệu tự động (chỉ áp dụng Contact mới)',
                'controls' => [
                    [
                        'key' => 'SettingAdminChangeLandingMKT',
                        'type' => 'boolean',
                        'label' => 'Cho phép Admin đơn vị đổi MKT của Nguồn dữ liệu tự động (chỉ áp dụng Contact mới)',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingLandingSetMKTAuto',
                'label' => '5. Cho phép gán tự động cho MKT chỉnh sửa đầu tiên Nguồn dữ liệu tự động (chỉ áp dụng Contact mới)',
                'controls' => [
                    [
                        'key' => 'SettingLandingSetMKTAuto',
                        'type' => 'boolean',
                        'label' => 'Cho phép gán tự động cho MKT chỉnh sửa đầu tiên Nguồn dữ liệu tự động (chỉ áp dụng Contact mới)',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingAdminChangeLandingMKTManual',
                'label' => '6. Cho phép Admin đơn vị đổi MKT của Nguồn dữ liệu thủ công(chỉ áp dụng Contact mới)',
                'controls' => [
                    [
                        'key' => 'SettingAdminChangeLandingMKTManual',
                        'type' => 'boolean',
                        'label' => 'Cho phép Admin đơn vị đổi MKT của Nguồn dữ liệu thủ công (chỉ áp dụng Contact mới)',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => true,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingCheckDeviceIdentity',
                'label' => '7. Phê duyệt đăng nhập',
                'controls' => [
                    [
                        'key' => 'SettingCheckDeviceIdentity',
                        'type' => 'boolean',
                        'label' => 'Cho phép tài khoản phải được phê duyệt mới đăng nhập được vào hệ thống',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingCheDoanhSoMKT',
                'label' => '8. Che doanh số với marketing',
                'controls' => [
                    [
                        'key' => 'SettingCheDoanhSoMKT',
                        'type' => 'boolean',
                        'label' => 'Che doanh số với marketing',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingCheDoanhSoSale',
                'label' => '9. Che doanh số với sale',
                'controls' => [
                    [
                        'key' => 'SettingCheDoanhSoSale',
                        'type' => 'boolean',
                        'label' => 'Che doanh số với sale',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingExcelPermission',
                'label' => '10. Phân quyền xuất Excel',
                'controls' => [
                    [
                        'key' => 'SettingExcelPermission',
                        'type' => 'excel_columns',
                        'label' => '',
                        'placeholder' => 'Các tài khoản cách nhau bởi dấu ; ',
                        'max_length' => 200,
                        'help' => 'Các tài khoản cách nhau bởi dấu ; ',
                        'default' => ['ttgroup2.admin'],
                    ],
                    [
                        'key' => 'SettingExcelPermissionEX',
                        'type' => 'excel_columns',
                        'label' => '',
                        'placeholder' => 'Các tài khoản cách nhau bởi dấu ; ',
                        'max_length' => null,
                        'help' => 'Các tài khoản cách nhau bởi dấu ; ',
                        'default' => [],
                    ],
                ],
                'help' => 'Chỉ những tài khoản được phân quyền mới được phép xuất excel',
                'note' => '',
            ],
            [
                'key' => 'row_FeedbackPermission',
                'label' => '11. Phân quyền quản lý ý kiến khách hàng',
                'controls' => [
                    [
                        'key' => 'FeedbackPermission',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Các tài khoản cách nhau bởi dấu ; ',
                        'max_length' => 200,
                        'help' => 'Các tài khoản cách nhau bởi dấu ; ',
                        'default' => '',
                    ],
                ],
                'help' => 'Chỉ những tài khoản được phân quyền mới được quyền xem ý kiến khách hàng',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKeywordMinLength',
                'label' => '12. Số kí tự ít nhất khi tìm kiếm',
                'controls' => [
                    [
                        'key' => 'SettingKeywordMinLength',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => 2,
                        'help' => '',
                        'default' => '',
                    ],
                ],
                'help' => 'Thiết lập độ dài nhỏ nhất của từ khóa khi người dùng tim kiếm ở hồ sơ khách hàng, tác nghiệp sale. Sử dụng trong trường hợp không muốn sale gõ số 0 ra toàn bộ data của đơn vị',
                'note' => '',
            ],
            [
                'key' => 'row_DefaultProductQuantity',
                'label' => '13. Số lượng sản phẩm mặc định khi tạo đơn từ Nguồn dữ liệu',
                'controls' => [
                    [
                        'key' => 'DefaultProductQuantity',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => 2,
                        'help' => '',
                        'default' => '',
                    ],
                ],
                'help' => 'Số lượng sản phẩm mặc định khi tạo đơn từ Nguồn dữ liệu',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhachCuTheoTTGHCauHinh',
                'label' => '14. Khách cũ theo trạng thái giao hàng',
                'controls' => [
                    [
                        'key' => 'SettingKhachCuTheoTTGHCauHinh',
                        'type' => 'boolean',
                        'label' => 'Khách cũ theo trạng thái giao hàng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Lựa chọn này cho phép hệ thống check data có phải là khách cũ hay không. Khách cũ là số đã về hệ thống và có bản ghi đang ở trạng thái giao hàng , sau thời điểm có trạng thái giao hàng đó số về tiếp được tính là số khách cũ ',
                'note' => 'Cấu hình giao hàng',
            ],
            [
                'key' => 'row_SettingLogoInChim',
                'label' => '15. Logo in chìm website',
                'controls' => [
                    [
                        'key' => 'SettingLogoInChim',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'URL file / logo',
                        'max_length' => 500,
                        'help' => 'Lưu URL file/logo. Upload file thật sẽ xử lý ở module media sau.',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingLogoInChim__FileUrls',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingLogoInChim__FileUpload',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingLogoInChim__FileFilter',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '.jpg;.png;.jpeg',
                    ],
                    [
                        'key' => 'SettingLogoInChim__OptionAutoUpload',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => 'TRUE',
                    ],
                    [
                        'key' => 'SettingLogoInChim__OptionCallBack',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingLogoInChim__OptionSize',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '1000',
                    ],
                ],
                'help' => 'Lựa chọn này cho phép hệ thống hiển thị logo in chìm website ',
                'note' => '',
            ],
            [
                'key' => 'row_SettingSoundNoti',
                'label' => '16. File âm thanh thông báo khi có thông báo mới',
                'controls' => [
                    [
                        'key' => 'SettingSoundNoti',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'URL file / logo',
                        'max_length' => 500,
                        'help' => 'Lưu URL file/logo. Upload file thật sẽ xử lý ở module media sau.',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingSoundNoti__FileUrls',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingSoundNoti__FileUpload',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingSoundNoti__FileFilter',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '.mp3',
                    ],
                    [
                        'key' => 'SettingSoundNoti__OptionAutoUpload',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => 'TRUE',
                    ],
                    [
                        'key' => 'SettingSoundNoti__OptionCallBack',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '',
                    ],
                    [
                        'key' => 'SettingSoundNoti__OptionSize',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => '500',
                    ],
                ],
                'help' => 'Lựa chọn này cho phép hệ thống phát âm thanh khi có thông báo thay vì âm thanh mặc định, nếu xóa file hoặc không cấu hình hệ thống sẽ sử dụng file âm thanh mặc định',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_4',
        'index' => 4,
        'title' => 'D. Kho số thả nổi',
        'rows' => [
            [
                'key' => 'row_SettingKhoSo',
                'label' => '1. Kho số thả nổi',
                'controls' => [
                    [
                        'key' => 'SettingKhoSo',
                        'type' => 'boolean',
                        'label' => 'Cho phép sử dụng kho số thả nổi',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định là không sử dụng. Lựa chọn này cho phép đơn vị sử dụng kho số thả nổi. Chỉ khi tắt kho số thả nổi mới khởi tạo lại được kho số.',
                'note' => 'Khởi tạo lại kho số',
            ],
            [
                'key' => 'row_SettingKhoSoForTeam',
                'label' => '2. Kho số của team',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoForTeam',
                        'type' => 'boolean',
                        'label' => 'Chỉ hiển thị các số của team trong kho số với sale',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định là không sử dụng. Lựa chọn này cho phép đơn vị sử dụng kho số với từng team, sale chỉ lấy số từ team trực thuộc.',
                'note' => 'Cấu hình kho số theo team',
            ],
            [
                'key' => 'row_SettingKhoSoShowKhachHangPhone',
                'label' => '3. Hiển thị số điện thoại trong kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoShowKhachHangPhone',
                        'type' => 'boolean',
                        'label' => 'Hiển thị số điện thoại trong kho số',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định là không sử dụng. Lựa chọn này cho phép đơn vị hiển thị tin nhắn nội bộ và thông tin các số điện thoại trong kho số thả nổi.',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoShowLichSuTacNghiep',
                'label' => '4. Hiển thị lịch sử tác nghiệp trong kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoShowLichSuTacNghiep',
                        'type' => 'boolean',
                        'label' => 'Hiển thị lịch sử tác nghiệp trong kho số',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định là không sử dụng. Lựa chọn này cho phép đơn vị hiển thị lịch sử tác nghiệp các số điện thoại trong kho số thả nổi.',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoGioiHanVaoKho',
                'label' => '5. Số lần tối đa đưa vào kho số của số không chốt đơn',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoGioiHanVaoKho',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Số lần tối đa vào kho',
                        'max_length' => 3,
                        'help' => 'Sau X lần đưa vào kho số đã bằng số lần tối đa thì hệ thống sẽ không đưa số vào kho số các số vượt cấu hình. Giá trị phải nhập vào',
                        'default' => '',
                    ],
                ],
                'help' => 'Sau X lần đưa vào kho số đã bằng số lần tối đa thì hệ thống sẽ không đưa số vào kho số các số vượt cấu hình. Giá trị phải nhập vào',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoTuGio',
                'label' => '6. Sau x phút kể từ khi nhận data mà không chốt đơn sẽ bị thả nổi',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoTuGio',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Giá trị nhỏ nhất',
                        'max_length' => 3,
                        'help' => 'Sau x phút kể từ khi nhận data mà không chốt đơn sẽ bị thả nổi',
                        'default' => '',
                    ],
                ],
                'help' => 'Sau X phút kể từ khi sale nhận được data mà không chốt đơn và rơi vào các tác nghiệp cấu hình vào kho số, không ở trạng thái chuyển tác nghiệp thì số sẽ bị đưa vào kho số. Giá trị phải nhập vào',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoDenGio',
                'label' => '7. Không thả nổi những contact quá x phút kể từ khi sale nhận data',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoDenGio',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Giá trị lớn nhất',
                        'max_length' => 3,
                        'help' => 'Không thả nổi những đơn quá x phút kể từ khi nhận data',
                        'default' => '',
                    ],
                ],
                'help' => 'Giả sử X = 43,200 nghĩa là sau 1 tháng kể từ khi sale nhận data mà không chốt đơn thì sẽ không cho vào kho số nữa',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoSaleLayGioiHan',
                'label' => '8. Số lượng giới hạn Sale lấy số trong ngày từ kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoSaleLayGioiHan',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Số lượng giới hạn',
                        'max_length' => 3,
                        'help' => 'Số lượng giới hạn Sale lấy số trong ngày từ kho số',
                        'default' => '',
                    ],
                ],
                'help' => 'Trong 1 ngày sale giới hạn số lần nhận số trong kho số. Giá trị phải nhập vào',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoSaleThoiGianMoiLan',
                'label' => '9. Thời gian (phút) giữa mỗi lần Sale lấy số trong ngày từ kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoSaleThoiGianMoiLan',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Thời gian (phút)',
                        'max_length' => 3,
                        'help' => 'Thời gian (phút) giữa mỗi lần Sale lấy số trong ngày từ kho số',
                        'default' => '',
                    ],
                ],
                'help' => 'Khoảng thời gian cấu hình giữa mỗi lần sale nhận số từ kho số. Giá trị phải nhập vào',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoSaleXemSoThoiGianMoiLan',
                'label' => '10. Thời gian (phút) giữa mỗi lần Sale xem số trong ngày từ kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoSaleXemSoThoiGianMoiLan',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Thời gian (phút)',
                        'max_length' => 3,
                        'help' => 'Thời gian (phút) giữa mỗi lần Sale xem số trong ngày từ kho số',
                        'default' => '',
                    ],
                ],
                'help' => 'Khoảng thời gian cấu hình giữa mỗi lần sale xem số từ kho số. Giá trị phải nhập vào. Chỉ áp dụng khi tắt hiển thị số điện thoại trong kho số.',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoContactXemSoThoiGianMoiLan',
                'label' => '11. Thời gian (phút) giữa mỗi lần Contact mở xem số trong ngày từ kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoContactXemSoThoiGianMoiLan',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Thời gian (phút)',
                        'max_length' => 3,
                        'help' => 'Thời gian (phút) giữa mỗi lần Contact xem số trong ngày từ kho số',
                        'default' => '',
                    ],
                ],
                'help' => 'Khoảng thời gian cấu hình giữa mỗi lần Contact xem số từ kho số. Giá trị phải nhập vào. Chỉ áp dụng khi tắt hiển thị số điện thoại trong kho số.',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoShowTextBoxSearch',
                'label' => '12. Hiển thị ô tìm kiếm số điện thoại trong kho số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoShowTextBoxSearch',
                        'type' => 'boolean',
                        'label' => 'Hiển thị ô tìm kiếm số điện thoại trong kho số',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Mặc định là không sử dụng. Lựa chọn này cho phép đơn vị hiển thị ô tìm kiếm số điện thoại trong kho số thả nổi.',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoNotTime',
                'label' => '13. Thả nổi toàn bộ số trong tác nghiệp, bỏ qua rule đếm thời gian',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoNotTime',
                        'type' => 'boolean',
                        'label' => 'Thả nổi toàn bộ số trong tác nghiệp, bỏ qua rule đếm thời gian',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Thả nổi toàn bộ số trong tác nghiệp, bỏ qua rule đếm thời gian',
                'note' => '',
            ],
            [
                'key' => 'row_SettingKhoSoView',
                'label' => '14. Cho phép sử dụng con mắt view số',
                'controls' => [
                    [
                        'key' => 'SettingKhoSoView',
                        'type' => 'boolean',
                        'label' => 'Cho phép sử dụng con mắt view số',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Cho phép sử dụng con mắt view số',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_5',
        'index' => 5,
        'title' => 'E. Facebook',
        'rows' => [
            [
                'key' => 'row_SettingFacebookCheckPhone',
                'label' => '1. Cho phép hệ thống kiểm tra đúng sđt từ tin nhắn FB',
                'controls' => [
                    [
                        'key' => 'SettingFacebookCheckPhone',
                        'type' => 'boolean',
                        'label' => 'Cho phép hệ thống kiểm tra đúng sđt từ tin nhắn FB',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => 'Hệ thống sẽ kiểm tra đúng các đầu số điện thoại di động, cố định của Việt Nam với số về từ Facebook.',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_6',
        'index' => 6,
        'title' => 'F. Thanh toán Online',
        'rows' => [
            [
                'key' => 'row_NganLuong_MerchantId',
                'label' => '1. Ngân lượng MerchantId',
                'controls' => [
                    [
                        'key' => 'NganLuong_MerchantId',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'NganLuong MerchantId',
                        'max_length' => null,
                        'help' => 'Nhập thông tin merchant Id',
                        'default' => '',
                    ],
                ],
                'help' => 'Nhập dữ liệu merchant id.',
                'note' => '',
            ],
            [
                'key' => 'row_NganLuong_SercurePass',
                'label' => '2. Ngân lượng SercurePass',
                'controls' => [
                    [
                        'key' => 'NganLuong_SercurePass',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'NganLuong SercurePass',
                        'max_length' => null,
                        'help' => 'Nhập thông tin merchant SercurePass',
                        'default' => '',
                    ],
                ],
                'help' => 'Nhập dữ liệu merchant secure pass.',
                'note' => '',
            ],
            [
                'key' => 'row_NganLuong_Receiver',
                'label' => '3. Ngân lượng Receiver',
                'controls' => [
                    [
                        'key' => 'NganLuong_Receiver',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Tài khoản ngân lượng',
                        'max_length' => null,
                        'help' => 'Nhập thông tin tài khoản ngân lượng',
                        'default' => '',
                    ],
                ],
                'help' => 'Nhập dữ liệu tài khoản ngân lượng.',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_7',
        'index' => 7,
        'title' => 'G. Tổng Đài',
        'rows' => [
            [
                'key' => 'row_Prefix_Omi',
                'label' => '1. Prefix',
                'controls' => [
                    [
                        'key' => 'Prefix_Omi',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Nhập mã vùng cuộc gọi',
                        'max_length' => 10,
                        'help' => 'Nhập mã vùng cuộc gọi',
                        'default' => '',
                    ],
                ],
                'help' => 'Mã chuyển vùng cuộc gọi thay cho số 0 đầu tiên của số điện thoại khi thực hiện cuộc gọi',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_8',
        'index' => 8,
        'title' => 'H. Ecommerce',
        'rows' => [
            [
                'key' => 'row_Use_Ecommerce',
                'label' => '1. Sử dụng sàn thương mại điện tử',
                'controls' => [
                    [
                        'key' => 'Use_Ecommerce',
                        'type' => 'boolean',
                        'label' => 'Sử dụng sàn thương mại điện tử',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_9',
        'index' => 9,
        'title' => 'I. Xuất Excel',
        'rows' => [
            [
                'key' => 'row_SettingExcelAccounting',
                'label' => '1. Cài đặt các cột xuất excel kho và kế toán',
                'controls' => [
                    [
                        'key' => 'SettingExcelAccounting',
                        'type' => 'excel_columns',
                        'label' => '1. Cài đặt các cột xuất excel kho và kế toán',
                        'placeholder' => '',
                        'help' => 'Chọn cột, xóa cột bằng biểu tượng thùng rác, kéo thả để đổi thứ tự khi trình duyệt hỗ trợ.',
                        'default' => ['STT', 'Id', 'MaDon', 'LandingUrl', 'DonHangTenSanPham', 'DonHangMaSanPham', 'DonHangSoLuong', 'DonHangDonGia', 'DonHangTongThanhTien', 'DonHangChietKhau', 'DonHangGiaCOD', 'DonHangTongTien', 'MaDonGiaoVan', 'GiaoHangHoTen', 'GiaoHangSoDienThoai', 'GiaoHangGhiChu', 'GiaoHangDiaChiTongHop', 'TenPhuongThucGiaoHang', 'GiaoHangTransport', 'TenTrangThaiGiaoHang', 'IsDoiSoatNoiBo_Text', 'DonHangNgayChot_Text', 'SaleDisplayName', 'SaleUsername', 'MarketingDisplayName', 'MarketingUsername', 'NgayTacNghiepCareDon_Text', 'CareDonUsername', 'NgayCapNhatTrangThaiGiaoHang_Text', 'NguoiCapNhatTrangThaiGiaoHang', 'SignPartCod', 'SaleNgayNhanData_Text'],
                        'options' => [
                            [
                                'value' => 'STT',
                                'label' => 'STT',
                            ],
                            [
                                'value' => 'Id',
                                'label' => '#',
                            ],
                            [
                                'value' => 'MaDon',
                                'label' => 'Mã đơn',
                            ],
                            [
                                'value' => 'NgayTao_Text',
                                'label' => 'Ngày data về',
                            ],
                            [
                                'value' => 'TenLanding',
                                'label' => 'Nguồn data',
                            ],
                            [
                                'value' => 'LandingUrl',
                                'label' => 'Link nguồn data',
                            ],
                            [
                                'value' => 'TenKenhQuangCao',
                                'label' => 'Kênh QC',
                            ],
                            [
                                'value' => 'MarketingDisplayName',
                                'label' => 'MKT Tên',
                            ],
                            [
                                'value' => 'MarketingUsername',
                                'label' => 'MKT TK',
                            ],
                            [
                                'value' => 'MarketingMaNV',
                                'label' => 'MKT Mã NV',
                            ],
                            [
                                'value' => 'UTMAgent',
                                'label' => 'UTMAgent',
                            ],
                            [
                                'value' => 'UTMCampaign',
                                'label' => 'UTMCampaign',
                            ],
                            [
                                'value' => 'UTMContent',
                                'label' => 'UTMContent',
                            ],
                            [
                                'value' => 'UTMChannel',
                                'label' => 'UTMChannel',
                            ],
                            [
                                'value' => 'UTMMedium',
                                'label' => 'UTMMedium',
                            ],
                            [
                                'value' => 'UTMSource',
                                'label' => 'UTMSource',
                            ],
                            [
                                'value' => 'UTMTerm',
                                'label' => 'UTMTerm',
                            ],
                            [
                                'value' => 'KhachHangName',
                                'label' => 'KH Họ tên',
                            ],
                            [
                                'value' => 'KhachHangPhone',
                                'label' => 'KH Số ĐT',
                            ],
                            [
                                'value' => 'KhachHangMessage',
                                'label' => 'KH Tin nhắn',
                            ],
                            [
                                'value' => 'SaleDisplayName',
                                'label' => 'Sale Tên',
                            ],
                            [
                                'value' => 'SaleUsername',
                                'label' => 'Sale TK',
                            ],
                            [
                                'value' => 'SaleMaNV',
                                'label' => 'Sale Mã NV',
                            ],
                            [
                                'value' => 'SaleTacNghiepCanTen',
                                'label' => 'Sale Tác nghiệp',
                            ],
                            [
                                'value' => 'SaleTacNghiepKetQuaTen',
                                'label' => 'Sale Kết quả tác nghiệp',
                            ],
                            [
                                'value' => 'SaleTacNghiepNgayCapNhat_Text',
                                'label' => 'Sale Ngày tác nghiệp',
                            ],
                            [
                                'value' => 'SaleNgayNhanData_Text',
                                'label' => 'Sale Ngày nhận data',
                            ],
                            [
                                'value' => 'DonHangNgayChot_Text',
                                'label' => 'Sale Ngày chốt đơn',
                            ],
                            [
                                'value' => 'SaleIdTrangThaiDon',
                                'label' => 'Trạng thái chốt đơn',
                            ],
                            [
                                'value' => 'SaleTacNghiepGhiChu',
                                'label' => 'Sale ghi chú',
                            ],
                            [
                                'value' => 'SaleTacNghiepSauBaoLauTen',
                                'label' => 'Sale tác nghiệp sau bao lâu',
                            ],
                            [
                                'value' => 'SaleTacNghiepTiepNgayBatDau_Text',
                                'label' => 'Sale Ngày tác nghiệp tiếp',
                            ],
                            [
                                'value' => 'PhanLoaiKhach',
                                'label' => 'Phân loại khách',
                            ],
                            [
                                'value' => 'TenKho',
                                'label' => 'Kho',
                            ],
                            [
                                'value' => 'QuanKhoUsername',
                                'label' => 'Quản kho',
                            ],
                            [
                                'value' => 'DonHangTenSanPham',
                                'label' => 'ĐH Tên SP',
                            ],
                            [
                                'value' => 'DonHangTenSanPham_SoLuong',
                                'label' => '(SL) ĐH Tên SP',
                            ],
                            [
                                'value' => 'DonHangMaSanPham',
                                'label' => 'ĐH Mã SP',
                            ],
                            [
                                'value' => 'DonHangMaSanPham_SoLuong',
                                'label' => '(SL) ĐH Mã SP',
                            ],
                            [
                                'value' => 'DonHangDonGia',
                                'label' => 'ĐH Đơn giá SP',
                            ],
                            [
                                'value' => 'DonHangSoLuong',
                                'label' => 'SP Số lượng',
                            ],
                            [
                                'value' => 'DonHangTongSoLuong',
                                'label' => 'ĐH Tổng số lượng',
                            ],
                            [
                                'value' => 'DonHangCanNang',
                                'label' => 'ĐH Cân nặng SP',
                            ],
                            [
                                'value' => 'DonHangTongCanNang',
                                'label' => 'ĐH Tổng cân nặng',
                            ],
                            [
                                'value' => 'DonHangThanhTien',
                                'label' => 'ĐH Thành tiền',
                            ],
                            [
                                'value' => 'DonHangTongThanhTien',
                                'label' => 'ĐH Tổng thành tiền',
                            ],
                            [
                                'value' => 'DonHangChietKhauSanPham',
                                'label' => 'ĐH Chiết khấu theo SP',
                            ],
                            [
                                'value' => 'DonHangChietKhauTheoDon',
                                'label' => 'ĐH Chiết khấu theo đơn',
                            ],
                            [
                                'value' => 'DonHangChietKhau',
                                'label' => 'ĐH Tổng chiết khấu',
                            ],
                            [
                                'value' => 'DonHangPTCKSP',
                                'label' => 'ĐH % CK theo SP 1',
                            ],
                            [
                                'value' => 'DonHangPTCKSP2',
                                'label' => 'ĐH % CK theo SP 2',
                            ],
                            [
                                'value' => 'DonHangSoTienCKSP',
                                'label' => 'ĐH Số tiền CK theo SP 1',
                            ],
                            [
                                'value' => 'DonHangSoTienCKSP2',
                                'label' => 'ĐH Số tiền CK theo SP 2',
                            ],
                            [
                                'value' => 'GiaoHangCOD',
                                'label' => 'Phí dịch vụ COD',
                            ],
                            [
                                'value' => 'DonHangHoTroCOD',
                                'label' => 'ĐH COD hỗ trợ cho khách',
                            ],
                            [
                                'value' => 'DonHangGiaCOD',
                                'label' => 'ĐH COD thu của khách',
                            ],
                            [
                                'value' => 'DonHangTongTien',
                                'label' => 'ĐH Tổng tiền',
                            ],
                            [
                                'value' => 'DonHangDatCoc',
                                'label' => 'ĐH Đặt cọc',
                            ],
                            [
                                'value' => 'DonHangKhachCanThanhToan',
                                'label' => 'ĐH Khách cần thanh toán',
                            ],
                            [
                                'value' => 'GiaoHangHoTen',
                                'label' => 'GH Họ tên',
                            ],
                            [
                                'value' => 'GiaoHangSoDienThoai',
                                'label' => 'GH Số điện thoại',
                            ],
                            [
                                'value' => 'GiaoHangGhiChu',
                                'label' => 'GH Ghi chú',
                            ],
                            [
                                'value' => 'GiaoHangDiaChi',
                                'label' => 'GH Địa chỉ',
                            ],
                            [
                                'value' => 'GiaoHangTenTinh',
                                'label' => 'GH Tỉnh',
                            ],
                            [
                                'value' => 'GiaoHangTenHuyen',
                                'label' => 'GH Huyện',
                            ],
                            [
                                'value' => 'GiaoHangTenXa',
                                'label' => 'GH Xã',
                            ],
                            [
                                'value' => 'GiaoHangDiaChiTongHop',
                                'label' => 'GH Địa chỉ tổng hợp',
                            ],
                            [
                                'value' => 'MaDonGiaoVan',
                                'label' => 'GH Mã vận đơn',
                            ],
                            [
                                'value' => 'TenPhuongThucGiaoHang',
                                'label' => 'GH Đơn vị giao vận',
                            ],
                            [
                                'value' => 'GiaoHangTransport',
                                'label' => 'GH dịch vụ',
                            ],
                            [
                                'value' => 'TenTrangThaiGiaoHang',
                                'label' => 'GH Trạng thái',
                            ],
                            [
                                'value' => 'LastMessage',
                                'label' => 'Tin nhắn nội bộ cuối',
                            ],
                            [
                                'value' => 'NgayTacNghiepCareDon_Text',
                                'label' => 'Ngày care đơn',
                            ],
                            [
                                'value' => 'CareDonUsername',
                                'label' => 'Người care đơn',
                            ],
                            [
                                'value' => 'NgayCapNhatTrangThaiGiaoHang_Text',
                                'label' => 'Ngày cập nhật TTGH',
                            ],
                            [
                                'value' => 'NguoiCapNhatTrangThaiGiaoHang',
                                'label' => 'Người cập nhật TTGH',
                            ],
                            [
                                'value' => 'GhiChuKeToan',
                                'label' => 'KT Ghi chú',
                            ],
                            [
                                'value' => 'IsDoiSoatNoiBo_Text',
                                'label' => 'Đối soát',
                            ],
                            [
                                'value' => 'DoiSoatNoiBoNgayCapNhat_Text',
                                'label' => 'Ngày đối soát',
                            ],
                            [
                                'value' => 'NgayDangDon_Text',
                                'label' => 'Ngày đăng đơn',
                            ],
                            [
                                'value' => 'SignPartCod',
                                'label' => 'Số tiền THMP',
                            ],
                            [
                                'value' => 'CareDonGhiChu',
                                'label' => 'Ghi chú care đơn',
                            ],
                            [
                                'value' => 'DonHangTenCombo',
                                'label' => 'ĐH Tên Combo',
                            ],
                            [
                                'value' => 'DonHangMaCombo',
                                'label' => 'ĐH Mã Combo',
                            ],
                            [
                                'value' => 'DonHangSoLuongCombo',
                                'label' => 'ĐH Số lượng Combo',
                            ],
                            [
                                'value' => 'LyDoTao',
                                'label' => 'Lý do tạo',
                            ],
                            [
                                'value' => 'NhomSale',
                                'label' => 'Nhóm sale',
                            ],
                            [
                                'value' => 'HDDT_MaDienTu',
                                'label' => 'Số hóa đơn điện tử',
                            ],
                        ],
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_SettingExcelAll',
                'label' => '2. Cài đặt cột xuất excel hồ sơ khách hàng',
                'controls' => [
                    [
                        'key' => 'SettingExcelAll',
                        'type' => 'excel_columns',
                        'label' => '2. Cài đặt cột xuất excel hồ sơ khách hàng',
                        'placeholder' => '',
                        'help' => 'Chọn cột, xóa cột bằng biểu tượng thùng rác, kéo thả để đổi thứ tự khi trình duyệt hỗ trợ.',
                        'default' => ['STT', 'Id', 'MaDon', 'NgayTao_Text', 'LandingUrl', 'TenLanding', 'MarketingDisplayName', 'MarketingUsername', 'MarketingMaNV', 'KhachHangName', 'KhachHangPhone', 'KhachHangMessage', 'SaleDisplayName', 'SaleUsername', 'SaleMaNV', 'SaleTacNghiepCanTen', 'SaleTacNghiepKetQuaTen', 'SaleTacNghiepNgayCapNhat_Text', 'DonHangNgayChot_Text', 'DonHangTenSanPham', 'DonHangMaSanPham', 'DonHangDonGia', 'DonHangSoLuong', 'DonHangTongSoLuong', 'DonHangCanNang', 'DonHangTongCanNang', 'DonHangThanhTien', 'DonHangTongThanhTien', 'DonHangChietKhau', 'DonHangChietKhauTheoDon', 'DonHangChietKhauSanPham', 'DonHangGiaCOD', 'DonHangTongTien', 'DonHangDatCoc', 'DonHangKhachCanThanhToan', 'MaDonGiaoVan', 'TenPhuongThucGiaoHang', 'GiaoHangTransport', 'TenTrangThaiGiaoHang', 'IsDoiSoatNoiBo_Text', 'SignPartCod', 'SaleNgayNhanData_Text', 'CareDonGhiChu', 'DonHangTenCombo', 'DonHangMaCombo'],
                        'options' => [
                            [
                                'value' => 'STT',
                                'label' => 'STT',
                            ],
                            [
                                'value' => 'Id',
                                'label' => '#',
                            ],
                            [
                                'value' => 'MaDon',
                                'label' => 'Mã đơn',
                            ],
                            [
                                'value' => 'NgayTao_Text',
                                'label' => 'Ngày data về',
                            ],
                            [
                                'value' => 'TenLanding',
                                'label' => 'Nguồn data',
                            ],
                            [
                                'value' => 'LandingUrl',
                                'label' => 'Link nguồn data',
                            ],
                            [
                                'value' => 'TenKenhQuangCao',
                                'label' => 'Kênh QC',
                            ],
                            [
                                'value' => 'MarketingDisplayName',
                                'label' => 'MKT Tên',
                            ],
                            [
                                'value' => 'MarketingUsername',
                                'label' => 'MKT TK',
                            ],
                            [
                                'value' => 'MarketingMaNV',
                                'label' => 'MKT Mã NV',
                            ],
                            [
                                'value' => 'UTMAgent',
                                'label' => 'UTMAgent',
                            ],
                            [
                                'value' => 'UTMCampaign',
                                'label' => 'UTMCampaign',
                            ],
                            [
                                'value' => 'UTMContent',
                                'label' => 'UTMContent',
                            ],
                            [
                                'value' => 'UTMChannel',
                                'label' => 'UTMChannel',
                            ],
                            [
                                'value' => 'UTMMedium',
                                'label' => 'UTMMedium',
                            ],
                            [
                                'value' => 'UTMSource',
                                'label' => 'UTMSource',
                            ],
                            [
                                'value' => 'UTMTerm',
                                'label' => 'UTMTerm',
                            ],
                            [
                                'value' => 'KhachHangName',
                                'label' => 'KH Họ tên',
                            ],
                            [
                                'value' => 'KhachHangPhone',
                                'label' => 'KH Số ĐT',
                            ],
                            [
                                'value' => 'KhachHangMessage',
                                'label' => 'KH Tin nhắn',
                            ],
                            [
                                'value' => 'SaleDisplayName',
                                'label' => 'Sale Tên',
                            ],
                            [
                                'value' => 'SaleUsername',
                                'label' => 'Sale TK',
                            ],
                            [
                                'value' => 'SaleMaNV',
                                'label' => 'Sale Mã NV',
                            ],
                            [
                                'value' => 'SaleTacNghiepCanTen',
                                'label' => 'Sale Tác nghiệp',
                            ],
                            [
                                'value' => 'SaleTacNghiepKetQuaTen',
                                'label' => 'Sale Kết quả tác nghiệp',
                            ],
                            [
                                'value' => 'SaleTacNghiepNgayCapNhat_Text',
                                'label' => 'Sale Ngày tác nghiệp',
                            ],
                            [
                                'value' => 'SaleNgayNhanData_Text',
                                'label' => 'Sale Ngày nhận data',
                            ],
                            [
                                'value' => 'DonHangNgayChot_Text',
                                'label' => 'Sale Ngày chốt đơn',
                            ],
                            [
                                'value' => 'SaleIdTrangThaiDon',
                                'label' => 'Trạng thái chốt đơn',
                            ],
                            [
                                'value' => 'SaleTacNghiepGhiChu',
                                'label' => 'Sale ghi chú',
                            ],
                            [
                                'value' => 'SaleTacNghiepSauBaoLauTen',
                                'label' => 'Sale tác nghiệp sau bao lâu',
                            ],
                            [
                                'value' => 'SaleTacNghiepTiepNgayBatDau_Text',
                                'label' => 'Sale Ngày tác nghiệp tiếp',
                            ],
                            [
                                'value' => 'PhanLoaiKhach',
                                'label' => 'Phân loại khách',
                            ],
                            [
                                'value' => 'TenKho',
                                'label' => 'Kho',
                            ],
                            [
                                'value' => 'QuanKhoUsername',
                                'label' => 'Quản kho',
                            ],
                            [
                                'value' => 'DonHangTenSanPham',
                                'label' => 'ĐH Tên SP',
                            ],
                            [
                                'value' => 'DonHangTenSanPham_SoLuong',
                                'label' => '(SL) ĐH Tên SP',
                            ],
                            [
                                'value' => 'DonHangMaSanPham',
                                'label' => 'ĐH Mã SP',
                            ],
                            [
                                'value' => 'DonHangMaSanPham_SoLuong',
                                'label' => '(SL) ĐH Mã SP',
                            ],
                            [
                                'value' => 'DonHangDonGia',
                                'label' => 'ĐH Đơn giá SP',
                            ],
                            [
                                'value' => 'DonHangSoLuong',
                                'label' => 'SP Số lượng',
                            ],
                            [
                                'value' => 'DonHangTongSoLuong',
                                'label' => 'ĐH Tổng số lượng',
                            ],
                            [
                                'value' => 'DonHangCanNang',
                                'label' => 'ĐH Cân nặng SP',
                            ],
                            [
                                'value' => 'DonHangTongCanNang',
                                'label' => 'ĐH Tổng cân nặng',
                            ],
                            [
                                'value' => 'DonHangThanhTien',
                                'label' => 'ĐH Thành tiền',
                            ],
                            [
                                'value' => 'DonHangTongThanhTien',
                                'label' => 'ĐH Tổng thành tiền',
                            ],
                            [
                                'value' => 'DonHangChietKhauSanPham',
                                'label' => 'ĐH Chiết khấu theo SP',
                            ],
                            [
                                'value' => 'DonHangChietKhauTheoDon',
                                'label' => 'ĐH Chiết khấu theo đơn',
                            ],
                            [
                                'value' => 'DonHangChietKhau',
                                'label' => 'ĐH Tổng chiết khấu',
                            ],
                            [
                                'value' => 'DonHangPTCKSP',
                                'label' => 'ĐH % CK theo SP 1',
                            ],
                            [
                                'value' => 'DonHangPTCKSP2',
                                'label' => 'ĐH % CK theo SP 2',
                            ],
                            [
                                'value' => 'DonHangSoTienCKSP',
                                'label' => 'ĐH Số tiền CK theo SP 1',
                            ],
                            [
                                'value' => 'DonHangSoTienCKSP2',
                                'label' => 'ĐH Số tiền CK theo SP 2',
                            ],
                            [
                                'value' => 'GiaoHangCOD',
                                'label' => 'Phí dịch vụ COD',
                            ],
                            [
                                'value' => 'DonHangHoTroCOD',
                                'label' => 'ĐH COD hỗ trợ cho khách',
                            ],
                            [
                                'value' => 'DonHangGiaCOD',
                                'label' => 'ĐH COD thu của khách',
                            ],
                            [
                                'value' => 'DonHangTongTien',
                                'label' => 'ĐH Tổng tiền',
                            ],
                            [
                                'value' => 'DonHangDatCoc',
                                'label' => 'ĐH Đặt cọc',
                            ],
                            [
                                'value' => 'DonHangKhachCanThanhToan',
                                'label' => 'ĐH Khách cần thanh toán',
                            ],
                            [
                                'value' => 'GiaoHangHoTen',
                                'label' => 'GH Họ tên',
                            ],
                            [
                                'value' => 'GiaoHangSoDienThoai',
                                'label' => 'GH Số điện thoại',
                            ],
                            [
                                'value' => 'GiaoHangGhiChu',
                                'label' => 'GH Ghi chú',
                            ],
                            [
                                'value' => 'GiaoHangDiaChi',
                                'label' => 'GH Địa chỉ',
                            ],
                            [
                                'value' => 'GiaoHangTenTinh',
                                'label' => 'GH Tỉnh',
                            ],
                            [
                                'value' => 'GiaoHangTenHuyen',
                                'label' => 'GH Huyện',
                            ],
                            [
                                'value' => 'GiaoHangTenXa',
                                'label' => 'GH Xã',
                            ],
                            [
                                'value' => 'GiaoHangDiaChiTongHop',
                                'label' => 'GH Địa chỉ tổng hợp',
                            ],
                            [
                                'value' => 'MaDonGiaoVan',
                                'label' => 'GH Mã vận đơn',
                            ],
                            [
                                'value' => 'TenPhuongThucGiaoHang',
                                'label' => 'GH Đơn vị giao vận',
                            ],
                            [
                                'value' => 'GiaoHangTransport',
                                'label' => 'GH dịch vụ',
                            ],
                            [
                                'value' => 'TenTrangThaiGiaoHang',
                                'label' => 'GH Trạng thái',
                            ],
                            [
                                'value' => 'LastMessage',
                                'label' => 'Tin nhắn nội bộ cuối',
                            ],
                            [
                                'value' => 'NgayTacNghiepCareDon_Text',
                                'label' => 'Ngày care đơn',
                            ],
                            [
                                'value' => 'CareDonUsername',
                                'label' => 'Người care đơn',
                            ],
                            [
                                'value' => 'NgayCapNhatTrangThaiGiaoHang_Text',
                                'label' => 'Ngày cập nhật TTGH',
                            ],
                            [
                                'value' => 'NguoiCapNhatTrangThaiGiaoHang',
                                'label' => 'Người cập nhật TTGH',
                            ],
                            [
                                'value' => 'GhiChuKeToan',
                                'label' => 'KT Ghi chú',
                            ],
                            [
                                'value' => 'IsDoiSoatNoiBo_Text',
                                'label' => 'Đối soát',
                            ],
                            [
                                'value' => 'DoiSoatNoiBoNgayCapNhat_Text',
                                'label' => 'Ngày đối soát',
                            ],
                            [
                                'value' => 'NgayDangDon_Text',
                                'label' => 'Ngày đăng đơn',
                            ],
                            [
                                'value' => 'SignPartCod',
                                'label' => 'Số tiền THMP',
                            ],
                            [
                                'value' => 'CareDonGhiChu',
                                'label' => 'Ghi chú care đơn',
                            ],
                            [
                                'value' => 'DonHangTenCombo',
                                'label' => 'ĐH Tên Combo',
                            ],
                            [
                                'value' => 'DonHangMaCombo',
                                'label' => 'ĐH Mã Combo',
                            ],
                            [
                                'value' => 'DonHangSoLuongCombo',
                                'label' => 'ĐH Số lượng Combo',
                            ],
                            [
                                'value' => 'LyDoTao',
                                'label' => 'Lý do tạo',
                            ],
                            [
                                'value' => 'NhomSale',
                                'label' => 'Nhóm sale',
                            ],
                            [
                                'value' => 'HDDT_MaDienTu',
                                'label' => 'Số hóa đơn điện tử',
                            ],
                        ],
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
        ],
    ],
    [
        'id' => 'tab_10',
        'index' => 10,
        'title' => 'K. HĐĐT',
        'rows' => [
            [
                'key' => 'row_HDDT_LamTronDonGia',
                'label' => '1. HĐĐT làm tròn đơn giá',
                'controls' => [
                    [
                        'key' => 'HDDT_LamTronDonGia',
                        'type' => 'boolean',
                        'label' => 'HĐĐT làm tròn đơn giá',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_HDDT_TenNguoiMuaMacDinh',
                'label' => '3. Tên người mua mặc định',
                'controls' => [
                    [
                        'key' => 'HDDT_TenNguoiMuaMacDinh',
                        'type' => 'text',
                        'label' => '',
                        'placeholder' => 'Bán cho người tiêu dùng',
                        'max_length' => 200,
                        'help' => '',
                        'default' => '',
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_HDDT_DonThuongBanChoNguoiTieuDung',
                'label' => '5. Đơn thường xuất mặc định',
                'controls' => [
                    [
                        'key' => 'HDDT_DonThuongBanChoNguoiTieuDung',
                        'type' => 'boolean',
                        'label' => 'Đơn thường xuất: Bán cho người tiêu dùng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
            [
                'key' => 'row_HDDT_DonEcomBanChoNguoiTieuDung',
                'label' => '6. Đơn ECOM xuất mặc định',
                'controls' => [
                    [
                        'key' => 'HDDT_DonEcomBanChoNguoiTieuDung',
                        'type' => 'boolean',
                        'label' => 'Đơn ECOM xuất: Bán cho người tiêu dùng',
                        'placeholder' => '',
                        'max_length' => null,
                        'help' => '',
                        'default' => false,
                    ],
                ],
                'help' => '',
                'note' => '',
            ],
        ],
    ],
];
