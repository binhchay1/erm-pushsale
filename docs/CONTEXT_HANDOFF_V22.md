# Context handoff V22

V22 là full source tích lũy từ V13 đến V21, bổ sung:

1. Trang Phân bổ data Pushsale-style thay cho Nhật ký lead.
2. Backend phân bổ data theo sản phẩm, Sale, team, khoảng ngày, nguồn data và trạng thái nhận data.
3. Bảng `data_distribution_batches` lưu batch phân bổ.
4. Cấu hình giao hàng Pushsale-style, bỏ giao diện shipping cũ.
5. CSS menu/header/hamburger nội bộ chuẩn hóa lại bằng `pushsale-system-v22.css`.

Route chính:

- `GET /admin/leads` → `DataDistributionController@index`
- `POST /admin/leads/distribute` → `DataDistributionController@store`
- `GET /marketing/leads` → cùng controller
- `POST /marketing/leads/distribute` → cùng controller
- `GET /allocator/workspace` → cùng controller
- `POST /allocator/leads/distribute` → cùng controller
- `GET /admin/shipping-partners` → cấu hình giao hàng mới

Route Nhật ký lead cũ không còn được đăng ký trong `routes/web.php`; phần audit packet kỹ thuật được chuyển về log/raw payload và các màn review exception liên quan.
