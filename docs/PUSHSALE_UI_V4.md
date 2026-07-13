# Pushsale UI V4 — đã được thay thế

Tài liệu này được giữ để đánh dấu lịch sử nâng cấp. Kiến trúc hiện hành là V5, mô tả tại `docs/PUSHSALE_TEMPLATE_V5.md`.

V5 dùng route/controller/component riêng cho từng mã menu, không dùng route legacy hoặc bảng JSON module dùng chung. Migration `2026_07_12_030000_drop_generic_pushsale_module_tables.php` chỉ có nhiệm vụ dọn các bảng generic nếu một bản thử nghiệm cũ từng được triển khai.
