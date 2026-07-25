# v130 – Landing source manual/approval flags contract

## Mục tiêu

Ổn định lại trang 2.4.1 `Kết nối dữ liệu` theo flow mới:

1. Trang 2.4.1 chỉ tạo/sửa nguồn landing và nguồn upsell.
2. Không gắn sản phẩm/gói ở dialog tạo nguồn.
3. Nguồn landing luôn ở chế độ `Nhập thủ công`.
4. Nguồn landing luôn phải qua menu duyệt trước khi chạy thật.
5. Menu duyệt mới gắn sản phẩm/gói, ngân sách và sync legacy `marketing_sources`.

## Fix chính

- Dialog tạo/sửa không gửi `products` nữa, tránh validate flow cũ kiểu `sources.1.name` khi có upsell URL.
- Backend force `manual_import=true` và `metadata.request_approval=true`.
- Thêm endpoint:

```http
PATCH /admin/marketing/landing-connections/records/{record}/flags
```

Endpoint này dùng cho checkbox trong bảng và luôn bật lại 2 cờ bắt buộc: nhập thủ công + yêu cầu duyệt.

## Test liên quan

- `test_landing_connection_source_update_does_not_require_product_mapping_for_upsell_source`
- `test_landing_connection_flags_endpoint_forces_manual_import_and_approval_request`
