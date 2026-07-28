# Luồng kết nối landing sau v120

1. Marketing/Admin tạo nguồn dữ liệu ở menu **2.4.1 Kết nối landing**.
2. Dialog tạo nguồn không yêu cầu sản phẩm/gói sản phẩm. Các trường chính giữ theo mẫu Pushsale: loại kết nối, cấu hình chia số, tên nguồn, URL nguồn, kênh quảng cáo, upsale URL, chọn nhanh sale, ưu tiên sale, nhập thủ công.
3. Kết nối mới ở trạng thái **chờ duyệt**.
4. Admin/Marketing leader vào menu **2.4.3 Duyệt kết nối dữ liệu**.
5. Người duyệt mở bản ghi, **tuỳ chọn** gắn sản phẩm/gói + ngân sách, rồi duyệt (không bắt buộc sản phẩm).
6. Khi đã duyệt, hệ thống đồng bộ:
   - `landing_connections.is_approved = true`
   - `marketing_sources.is_approved = true` (`product_id` có thể null)
   - product mapping trong `landing_connection_products` nếu có chọn
   - ngân sách trên landing connection và marketing source

Từ chối lưu `metadata.rejected_*` + trạng thái từ chối trên list 2.4.1 (không còn hiện “Chờ duyệt gắn sản phẩm”).
Webhook vẫn nhận lead khi chưa map sản phẩm (xem PROJECT_CONTRACT v128).
