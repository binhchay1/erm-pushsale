# Context Handoff V23

V23 tiếp nối V22, tập trung xử lý yêu cầu: mọi phần còn dùng giao diện riêng phải đồng bộ với giao diện hiện có.

## Đã thay đổi

- Thêm `resources/css/pushsale-system-v23.css` và import cuối trong `pushsale.css`.
- Đồng bộ các component UI chung (`Card`, `Button`, `Input`, `Dialog`, `StatusBadge`) về Pushsale-style trong scope nội bộ ERM.
- Gắn wrapper cho các màn riêng: System Monitor, Integrations/Pancake extension, Shipping Orders, Shipping Reconciliation.
- Chỉnh modal Customer Messages/Pancake Chat sang class contract mới `ps-customer-chat-modal`, `ps-chat-tabs`, `ps-chat-thread`, `ps-chat-composer`.
- Sửa `StatusBadge` nhận thêm prop `label` để các màn chi tiết payload hiển thị nhãn đúng.

## Không đổi

- Không thay đổi business, queue, Horizon hoặc báo cáo.
- Không khôi phục Nhật ký lead cũ.
- Không đổi flow Phân bổ data và Cấu hình giao hàng V22.
- Không tác động public/login CSS.

## Kiểm tra sau deploy

Mở nhanh các URL:

```text
/admin/system-monitor
/admin/system-monitor?tab=queues
/admin/system-monitor?tab=reports
/admin/integrations
/admin/shipping/orders
/admin/shipping/reconciliation
/admin/leads
/admin/shipping-partners
/admin/marketing/landing-connections
```

Các điểm cần nhìn bằng mắt:

- Header/sidebar cùng font và kích thước.
- Không còn card bo tròn kiểu app riêng.
- Bảng custom có header xanh và border Pushsale.
- Modal chat Pancake/tin nhắn nội bộ không tràn màn hình, header xanh, tab dạng Pushsale.
- Form webhook/Pancake extension dùng input/button cùng style với phần mềm.
