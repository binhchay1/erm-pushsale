# Landing Upsell Review UI

## Mục tiêu

Hoàn thiện luồng xử lý packet mua thêm đến sau cửa sổ tự gộp 90 giây tại trang Hồ sơ khách hàng / Workspace sale.

Luồng này giữ nguyên các nguyên tắc nghiệp vụ:

- Packet audit có thể có nhiều dòng.
- Một packet mua thêm không tạo contact/lead mới.
- Không chạy lại chia sale.
- Chỉ gộp vào đơn gốc khi đơn chưa chốt, chưa trừ kho, chưa có mã giao vận và chưa có shipment.
- Khi đơn không còn an toàn để gộp, tạo một đơn mua thêm riêng nhưng giữ khách hàng, sale, team và campaign của đơn gốc.
- Mọi quyết định đều lưu `review_resolution`, `review_note`, người xử lý và thời gian xử lý.

## Giao diện mới

Badge `upsell chờ xử lý` mở một dialog đầy đủ, không dùng `window.alert()` hoặc `window.confirm()`.

Dialog gồm:

1. Tóm tắt đơn gốc: mã đơn, trạng thái, khách hàng, sale/team, số lượng sản phẩm và giá trị đơn.
2. Tổng quan packet: số packet chờ, giá trị chờ, bộ lọc Cần xử lý / Đã xử lý / Tất cả.
3. Chi tiết từng packet: thời gian nhận, external ID, sản phẩm, số lượng, đơn giá, thành tiền, chiết khấu, giá trị packet, message và lý do cần review.
4. Giải thích rõ vì sao có thể hoặc không thể gộp vào đơn gốc.
5. Ba hành động nghiệp vụ:
   - Gộp vào đơn gốc.
   - Tạo đơn mua thêm.
   - Chỉ ghi nhận, không thay đổi đơn.
6. Mỗi hành động mở dialog xác nhận riêng với mô tả tác động và ô ghi chú. `Chỉ ghi nhận` bắt buộc nhập lý do ở giao diện.
7. Sau khi xử lý, UI hiển thị cách xử lý, người xử lý, thời gian, ghi chú và mã đơn mua thêm nếu có.

## Điều chỉnh an toàn backend

- `LeadSupplementReviewService::mergeBlockReason()` tập trung toàn bộ lý do chặn merge để backend và UI dùng cùng một quy tắc.
- API chỉ bật nút gộp khi packet có dữ liệu có thể xử lý và đơn thực sự còn an toàn.
- API chỉ bật tạo đơn mua thêm khi packet có ít nhất một sản phẩm hợp lệ.
- Controller dùng model đầy đủ của đơn đang hiển thị để kiểm tra `closed_at`, `inventory_deducted_at`, `tracking_number` và shipment; không dùng relation select thiếu cột.
- API trả thêm tổng tiền, số lượng, line total, trạng thái đơn, sale/team và thống kê packet.
- Validation error từ JSON API hiển thị lỗi trường cụ thể thay vì chỉ báo lỗi chung.

## Sửa lỗi kế thừa tên khách

`LandingFormDriver` không còn tự gán `Khách Landing` cho mọi packet. Tên mặc định chỉ được gán cho lead chính; packet upsell/follow-up không gửi tên sẽ kế thừa đúng tên khách từ đơn gốc.

## Kiểm tra

```bash
npm ci
npm run build

php artisan test --filter=LandingSupplementReviewTest
php artisan test --filter=LandingUpsellCompleteBusinessFlowTest
php landing_flow_live_test.php \
  --base=https://your-domain.example \
  --token=YOUR_CAMPAIGN_TOKEN \
  --late=1
```

Kỳ vọng:

- `LandingSupplementReviewTest`: toàn bộ test pass, gồm payload API chi tiết, lý do chặn merge và lưu audit note.
- `LandingUpsellCompleteBusinessFlowTest`: 13/13 pass.
- Live test: toàn bộ case pass; packet sau 90 giây ở `needs_review`, không cộng item vào đơn cũ và không tạo đơn mới âm thầm.

## Deploy

Sau khi chép source:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

Không có migration mới trong thay đổi này.
