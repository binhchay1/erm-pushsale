# ERM SaleOps — Chi tiết project

## 1. Tổng quan

`ERM SaleOps` là hệ thống quản trị vận hành bán hàng cho doanh nghiệp, tập trung vào toàn bộ luồng từ lúc nhận lead marketing đến khi đơn hàng được sale chốt, kho xử lý, kế toán đối soát và ban lãnh đạo theo dõi hiệu quả.

Project tham chiếu nghiệp vụ từ UI pushsale.vn nhưng triển khai dưới thương hiệu riêng `ERM SaleOps`.

Luồng nghiệp vụ chính:

```text
Marketing chạy ads / form / landing
    → Lead đổ về hệ thống
    → Telesale nhận data, gọi, chăm sóc, chốt đơn
    → Kho xác nhận tồn, đóng gói, chuyển đơn vị giao hàng
    → Kế toán kiểm tra COD, chuyển khoản, đối soát
    → CEO/Admin xem báo cáo tổng hợp realtime
```

## 2. Mục tiêu sản phẩm

- Quản lý tập trung lead, đơn hàng, khách hàng, sản phẩm và tồn kho.
- Theo dõi hiệu quả marketing theo nguồn, campaign, UTM, marketer, team.
- Theo dõi hiệu quả sale theo nhân viên, trạng thái gọi, tỷ lệ chốt, doanh số.
- Hỗ trợ kho xử lý đơn, kiểm tra sản phẩm, cập nhật trạng thái giao hàng.
- Hỗ trợ kế toán đối soát COD, chuyển khoản, đơn hoàn, đơn lỗi.
- Cung cấp dashboard và báo cáo cho CEO/Admin.
- Nhận lead tự động từ Facebook, TikTok, Zalo, Google Lead Form và landing page.
- Cập nhật realtime khi có lead/order/event mới.

## 3. Người dùng và phân quyền

| Role | Mã | Quyền chính |
|------|----|-------------|
| Quản trị | `admin` | Xem toàn bộ hệ thống, báo cáo, cấu hình tích hợp, quản lý vận hành |
| Telesale | `sales` | Xem lead/order được gán cho mình, thao tác gọi/chăm sóc/chốt đơn |

Nguyên tắc phân quyền:

- `admin` có quyền toàn hệ thống.
- `sales` chỉ xem dữ liệu có `sale_user_id` là chính mình.
- Filter báo cáo cần ép scope sales ở backend, không phụ thuộc frontend.
- Repository/query layer không được bypass scope phân quyền.

## 4. Tech stack

| Lớp | Công nghệ |
|-----|-----------|
| Backend | Laravel 13, Sanctum, Inertia, Reverb |
| Frontend | React 19, shadcn/ui, Radix UI, Recharts, Echo |
| Database | PostgreSQL / MySQL / SQLite |
| Realtime | Laravel Reverb + Laravel Echo |
| Auth API | Laravel Sanctum Bearer token |
| UI delivery | Inertia pages |

## 5. Kiến trúc backend

Backend dùng layered architecture:

```text
HTTP / Inertia / API
    → Controllers thin
    → Form Requests / DTOs
    → Services / Use cases
    → Repositories
    → Eloquent Models / Scopes
    → Database
```

### Pattern nên dùng

| Pattern | Mục đích |
|---------|----------|
| Controller mỏng | Chỉ nhận request, gọi service, trả response |
| DTO | Chuẩn hóa filter/report input |
| Service / Use case | Chứa nghiệp vụ từng màn |
| Repository | Gom truy vấn phức tạp, dễ test |
| Presenter / Resource | Map model/service data sang JSON/Inertia props |
| Strategy | Tách công thức tính metric |
| Policy/Middleware | Phân quyền theo role/scope |

## 6. Kiến trúc frontend

Frontend dùng Inertia + React, route theo page.

Cấu trúc khuyến nghị:

```text
resources/js/
  pages/
    Admin/
    Sales/
    Settings/
  components/
    reports/
    operations/
    charts/
    data-table/
    filters/
    layout/
  hooks/
    use-report-search.ts
    use-realtime-dashboard.ts
  providers/
    theme-provider.tsx
  types/
    backend.d.ts
```

Nguyên tắc:

- Page chỉ compose layout + components.
- Logic filter/search/pagination để trong hooks.
- Table/chart/filter là component tái sử dụng.
- Type từ backend dùng interface trong `backend.d.ts`.
- Với pagination response dùng sẵn `IPaginationResponse`, `IErrorPaginationResponse`, `IPaginatedResponse`; không tạo/import type mới.

## 7. 10 nhóm màn hình chính

| # | Module | Mục tiêu |
|---|--------|----------|
| 1 | Báo cáo CEO | Tổng hợp trạng thái đơn, hiệu quả sale/marketing, KPI |
| 2 | Dashboard Marketing | Theo dõi nguồn, campaign, UTM, chi phí, contact, doanh số |
| 3 | Báo cáo doanh số Marketing | 19 metric vận hành theo marketer/team |
| 4 | Báo cáo doanh số Sale | 19 metric vận hành theo sale/team |
| 5 | Sale tác nghiệp | Pipeline gọi, chăm sóc, chốt đơn, ghi chú khách |
| 6 | Hồ sơ khách hàng | Lịch sử mua, thông tin liên hệ, đơn đã phát sinh |
| 7 | Kế toán tác nghiệp | COD, chuyển khoản, đối soát, công nợ, phí |
| 8 | Thủ kho tác nghiệp | Xử lý đơn, đóng gói, trạng thái giao, hoàn |
| 9 | Đơn hàng lỗi | Theo dõi đơn thiếu thông tin, sai trạng thái, lỗi vận chuyển |
| 10 | Sản phẩm kho | Sản phẩm, tồn kho, biến thể, kho hàng |

## 8. Domain dữ liệu chính

Các entity chính cần có:

- `users`: admin/sales/nhân sự vận hành.
- `orders`: đơn hàng, trạng thái sale/kho/giao hàng/kế toán.
- `customers`: hồ sơ khách hàng.
- `products`: sản phẩm.
- `warehouses`: kho.
- `inventory_items`: tồn kho theo sản phẩm/kho.
- `lead_ingestions`: log lead từ nền tảng ngoài.
- `integration_connections`: cấu hình tích hợp từng platform.
- `marketing_sources`: nguồn/campaign/UTM.
- `order_operations`: log thao tác gọi/chăm sóc/chốt.
- `payments` hoặc các field kế toán trên order: COD, chuyển khoản, đặt cọc, đối soát.

## 9. Tích hợp ngoài

Nền tảng nhận lead:

- Facebook Lead Ads
- TikTok Lead Generation
- Zalo OA
- Landing page/form riêng
- Google Ads Lead Form

Nền tảng còn cần làm thật:

- Đơn vị vận chuyển: GHTK/GHN/ViettelPost/J&T tùy yêu cầu.
- VoIP/call center: dùng để click-to-call, log cuộc gọi, recording, call status.

## 10. Realtime

Channel:

- `dashboard.admin`
- `dashboard.sales`

Event:

- `lead.ingested`
- `order.created`
- `order.updated`
- `order.status_changed`
- `payment.reconciled`
- `inventory.low_stock`

Use case realtime:

- Toast khi có lead mới.
- Refresh dashboard summary.
- Cập nhật badge trạng thái đơn.
- Thông báo sales khi được gán lead.

## 11. Style design phù hợp

Project này là nghiệp vụ vận hành nhiều bảng, nhiều filter, nhiều số liệu. Style phù hợp nhất là `Enterprise SaaS Dashboard`.

### Định hướng UI

- Giao diện sáng, sạch, nhiều khoảng trắng nhưng dense đủ cho vận hành.
- Sidebar cố định theo module.
- Header có search, date range, theme switch, notification.
- Card KPI ở đầu màn.
- Data table mạnh: sticky header, sticky columns, column visibility, resize, sort, filter, pagination.
- Chart vừa đủ, ưu tiên đọc số nhanh hơn trang trí.
- Badge màu rõ cho trạng thái đơn/giao hàng/thanh toán.
- Drawer/Sheet cho chi tiết order/customer để không rời màn chính.

### Visual language

| Thành phần | Style |
|------------|-------|
| Layout | Enterprise dashboard, sidebar + topbar |
| Component | shadcn/ui + Radix UI |
| Chart | Recharts, card-based |
| Table | Dense, sticky, spreadsheet-like |
| Color | Neutral base + semantic status colors |
| Theme | `brand`, `ocean`, `sunset`, `violet` |
| Motion | Nhẹ, chỉ cho drawer/toast/loading |

### Semantic colors

| Ý nghĩa | Màu gợi ý |
|---------|-----------|
| Thành công/đã thanh toán | Green |
| Đang xử lý/đang giao | Blue |
| Chờ xử lý | Amber |
| Lỗi/hủy/hoàn | Red |
| Trung lập | Slate/Zinc |

## 12. Ưu tiên triển khai

### Phase 1 — Core foundation

- Auth + role middleware.
- Layout admin/sales.
- Models/migrations/seeds.
- Order/customer/product/inventory base.
- Basic dashboard summary.

### Phase 2 — Sales operations

- Sale workspace.
- Pipeline call stages.
- Customer profile.
- Order detail drawer.
- Notes/history.

### Phase 3 — Reports

- CEO report.
- Marketing dashboard.
- Marketing revenue report.
- Sale revenue report.
- Shared report filters.

### Phase 4 — Warehouse/accounting

- Warehouse operations.
- Inventory product screen.
- Accounting reconciliation.
- Failed orders.

### Phase 5 — Integrations/realtime

- Webhooks lead ingestion.
- Integration admin config.
- Reverb realtime.
- Shipping provider.
- VoIP provider.

## 13. Success criteria

Project được xem là đạt MVP khi:

- Admin xem được dashboard, báo cáo, toàn bộ đơn.
- Sales chỉ xem và xử lý được lead/order của mình.
- Lead từ webhook/landing tạo được ingestion log và order.
- Sale thao tác pipeline gọi/chốt được.
- Kho và kế toán cập nhật trạng thái vận hành được.
- Báo cáo marketing/sale/CEO tính đúng metric chính.
- Realtime báo lead/order mới hoạt động.
