# Luồng kết nối landing sau v120

1. Marketing/Admin tạo nguồn dữ liệu ở menu **2.4.1 Kết nối landing**.
2. Dialog tạo nguồn không yêu cầu sản phẩm/gói sản phẩm nữa. Các trường chính giữ theo mẫu Pushsale: loại kết nối, cấu hình chia số, tên nguồn, URL nguồn, kênh quảng cáo, upsale URL, chọn nhanh sale, ưu tiên sale, nhập thủ công.
3. Kết nối mới ở trạng thái **chờ duyệt**.
4. Admin/Marketing leader vào menu **2.4.3 Duyệt kết nối dữ liệu**.
5. Người duyệt mở bản ghi, gắn sản phẩm/gói sản phẩm, nhập ngân sách nếu có, rồi duyệt.
6. Khi đã duyệt, hệ thống đồng bộ:
   - `landing_connections.is_approved = true`
   - `marketing_sources.is_approved = true`
   - product mapping trong `landing_connection_products`
   - ngân sách trên landing connection và marketing source

Luồng này tránh việc Marketing tạo landing phải biết chính xác sản phẩm/gói ngay từ đầu, nhưng vẫn chặn nhận data thật khi chưa được duyệt cấu hình sản phẩm.
