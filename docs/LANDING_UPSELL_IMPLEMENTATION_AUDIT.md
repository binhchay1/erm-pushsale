# Landing/Upsell implementation audit

## Source of truth

- Lead/contact: `lead_ingestions.counts_as_lead = 1` và status không phải duplicate/failed/needs_review.
- Order/doanh thu: `orders` + `order_items`, bao gồm đơn mua thêm được tạo thủ công.
- Packet audit: mọi request Landing đều có một `lead_ingestions`; packet bổ sung không làm tăng lead.
- Quan hệ đơn bổ sung: packet có `order_id = đơn mới` và `related_order_id = đơn gốc`.

## Case matrix

| Case | Kết quả |
|---|---|
| Base rồi upsell trong 90s | Một order, một sale, nhiều item |
| Add-on gửi nhầm `/receive` | Nhận diện follow-up, gộp order, không tăng lead |
| Retry cùng submission ID | Trả bản ghi cũ, không nhân item/order |
| Hai upsell song song | Row lock order, không mất update |
| Upsell tới trước base | Gathering, tự reconcile khi base tới |
| Orphan không có base | Needs review, không tạo/chia order |
| Upsell sau 90s | Liên kết order gốc, không auto-merge/reroute |
| Đơn gốc còn sửa an toàn | Người có quyền có thể gộp thủ công |
| Đơn gốc đã chốt/kho/vận đơn | Tạo đơn mua thêm giữ sale/team/source |
| Kho/Marketing/Kế toán | Xem packet nhưng không quyết định nghiệp vụ sale |
| Cùng trình duyệt có khách mới | Landing chính sinh opaque session mới, không dùng storage cũ |

## Các báo cáo đã đồng bộ

- Marketing Dashboard nguồn cha tổng hợp cả nguồn con nhưng tổng trang không double-count.
- Campaign report không dùng `max(lead, order)`; đơn supplemental không tăng leadsGenerated.
- CEO report dùng contact order canonical cho tỷ lệ chốt; doanh thu vẫn gồm đơn supplemental.
- Báo cáo Telesale/Marketing bổ sung tách contact khỏi order supplemental.
- Revenue report chỉ coi order thật đã chốt là closed, conversion dùng contact canonical.
- Allocator report so sánh enum status đúng kiểu, tránh các bucket assigned/pending trả 0 giả.

## Deploy validation

- PHP lint toàn bộ source/test.
- Vite production build.
- Feature tests bổ sung cho merge, late/orphan review, permission, parent/child campaign và report parity.
- PHPUnit cần chạy trên server sau `composer install` vì artifact không đóng gói `vendor`.
