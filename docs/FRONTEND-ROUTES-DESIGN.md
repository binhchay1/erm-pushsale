# ERM SaleOps — Frontend routes và style design

## 1. Mục tiêu frontend

Frontend là app vận hành nội bộ dạng `Enterprise SaaS Dashboard`, phục vụ nhiều vai trò với nhiều bảng dữ liệu, filter, KPI, báo cáo và thao tác nhanh.

Ưu tiên thiết kế:

- Đọc số liệu nhanh.
- Lọc dữ liệu nhanh.
- Thao tác đơn hàng/lead không bị rời context.
- Bảng dữ liệu mạnh, dense, hỗ trợ vận hành hằng ngày.
- Realtime rõ ràng nhưng không gây nhiễu.

## 2. Route groups

Frontend chia 3 nhóm route:

| Nhóm | Prefix | Người dùng |
|------|--------|------------|
| Admin | `/admin/*` | Quản trị, CEO, marketing manager, kế toán, kho |
| Sales | `/sales/*` | Nhân viên telesale |
| Shared | `/settings`, auth pages | Tất cả user đã đăng nhập |

## 3. Frontend route map

### Admin routes

| Path | Page | Mục đích |
|------|------|----------|
| `/admin/dashboard` | `pages/Admin/Dashboard.tsx` | Dashboard tổng quan + realtime |
| `/admin/reports/ceo` | `pages/Admin/Reports/Ceo.tsx` | Báo cáo CEO |
| `/admin/marketing/dashboard` | `pages/Admin/Marketing/Dashboard.tsx` | Dashboard marketing theo nguồn/campaign |
| `/admin/marketing/revenue` | `pages/Admin/Marketing/Revenue.tsx` | Báo cáo doanh số marketing |
| `/admin/sales/revenue` | `pages/Admin/Sales/Revenue.tsx` | Báo cáo doanh số sales |
| `/admin/accounting` | `pages/Admin/Accounting/Index.tsx` | Kế toán tác nghiệp |
| `/admin/warehouse/operations` | `pages/Admin/Warehouse/Operations.tsx` | Thủ kho tác nghiệp |
| `/admin/warehouse/inventory` | `pages/Admin/Warehouse/Inventory.tsx` | Sản phẩm và tồn kho |
| `/admin/orders/failed` | `pages/Admin/Orders/Failed.tsx` | Đơn hàng lỗi |

### Sales routes

| Path | Page | Mục đích |
|------|------|----------|
| `/sales/workspace` | `pages/Sales/Workspace.tsx` | Sale tác nghiệp, pipeline gọi/chốt |
| `/sales/customers` | `pages/Sales/Customers.tsx` | Hồ sơ khách hàng của sale |

### Shared routes

| Path | Page | Mục đích |
|------|------|----------|
| `/settings` | `pages/Settings/Index.tsx` | Theme, notification, profile preferences |
| `/login` | `pages/Auth/Login.tsx` | Đăng nhập |
| `/forgot-password` | `pages/Auth/ForgotPassword.tsx` | Quên mật khẩu nếu bật |
| `/reset-password/{token}` | `pages/Auth/ResetPassword.tsx` | Reset password nếu bật |

## 4. Layout strategy

### Admin layout

Dùng `AdminLayout` cho toàn bộ route `/admin/*`.

Cấu trúc:

```text
AdminLayout
  ├─ AppSidebar
  ├─ Topbar
  │   ├─ GlobalSearch
  │   ├─ DateRangeQuickSelect
  │   ├─ NotificationBell
  │   └─ UserMenu
  └─ MainContent
      └─ Page
```

Sidebar sections:

- Tổng quan
- Báo cáo
- Marketing
- Sales
- Kế toán
- Kho
- Đơn lỗi
- Cấu hình

### Sales layout

Dùng `SalesLayout` cho `/sales/*`.

Cấu trúc tối giản hơn admin:

```text
SalesLayout
  ├─ CompactSidebar
  ├─ Topbar
  │   ├─ TodayStats
  │   ├─ NotificationBell
  │   └─ UserMenu
  └─ MainContent
```

Sidebar sections:

- Workspace
- Khách hàng
- Lịch gọi
- Cài đặt

## 5. Style design phù hợp

Style chính: `Enterprise SaaS Operations Dashboard`.

Không nên dùng landing-page style, quá nhiều gradient hoặc animation. Đây là tool vận hành nội bộ nên cần rõ ràng, dense, ổn định và nhanh.

### Design principles

1. `Clarity first`: số liệu, trạng thái, hành động chính phải thấy ngay.
2. `Dense but readable`: bảng nhiều cột nhưng vẫn có spacing, sticky columns.
3. `Context-preserving`: mở chi tiết bằng drawer/sheet thay vì chuyển trang liên tục.
4. `Role-focused`: admin thấy báo cáo/tổng quan; sales thấy việc cần gọi/chốt hôm nay.
5. `Realtime subtle`: toast/badge update nhẹ, tránh nhảy layout.
6. `Keyboard-friendly`: search/filter/table cần dùng tốt bằng bàn phím.

## 6. Visual system

### Theme

Hỗ trợ các theme đã định nghĩa:

- `brand`
- `ocean`
- `sunset`
- `violet`

Base UI nên dùng neutral palette:

- Background: `slate-50` hoặc `zinc-50`
- Surface: `white`
- Border: `slate-200`
- Text chính: `slate-950`
- Text phụ: `slate-500`

Dark mode nếu có:

- Background: `slate-950`
- Surface: `slate-900`
- Border: `slate-800`
- Text chính: `slate-50`
- Text phụ: `slate-400`

### Semantic status colors

| Trạng thái | Màu | Dùng cho |
|------------|-----|----------|
| Success | Green | Đã thanh toán, đã giao, hoàn tất |
| Info | Blue | Đang giao, đang xử lý, đang gọi |
| Warning | Amber | Chờ xử lý, thiếu thông tin, cần kiểm tra |
| Danger | Red | Hủy, hoàn, lỗi, thất bại |
| Neutral | Slate | Nháp, chưa thao tác, không xác định |
| Purple | Violet | Automation/realtime/system event |

### Component style

| Component | Style khuyến nghị |
|-----------|-------------------|
| Card | Border nhẹ, shadow rất nhẹ, radius `xl` |
| Button | shadcn variants: default/secondary/outline/ghost/destructive |
| Table | Sticky header, sticky first column, zebra hover, compact row |
| Badge | Semantic colors, readable contrast |
| Filter | Collapsible filter bar + active filter chips |
| Drawer | Chi tiết order/customer |
| Dialog | Confirm destructive/action quan trọng |
| Toast | Realtime/new lead/action result |
| Chart | Card-contained, tooltip rõ, legend gọn |

## 7. Shared frontend components cần có

```text
components/
  layout/
    admin-layout.tsx
    sales-layout.tsx
    app-sidebar.tsx
    topbar.tsx
    user-menu.tsx
    notification-bell.tsx
  filters/
    report-filter-bar.tsx
    date-range-filter.tsx
    source-filter.tsx
    team-filter.tsx
    product-filter.tsx
    active-filter-chips.tsx
  data-table/
    data-table.tsx
    data-table-toolbar.tsx
    data-table-pagination.tsx
    data-table-column-header.tsx
    column-visibility-menu.tsx
  reports/
    metric-card.tsx
    metric-pair-cell.tsx
    status-summary-badges.tsx
    revenue-metric-table.tsx
  operations/
    order-detail-drawer.tsx
    customer-detail-drawer.tsx
    operation-stage-tabs.tsx
    order-status-badge.tsx
    payment-status-badge.tsx
    shipping-status-badge.tsx
  charts/
    revenue-chart.tsx
    source-performance-chart.tsx
    status-distribution-chart.tsx
```

## 8. Hooks cần có

```text
hooks/
  use-report-search.ts
  use-report-filters.ts
  use-table-state.ts
  use-realtime-dashboard.ts
  use-order-drawer.ts
  use-active-theme.ts
```

Mục tiêu:

- Đồng bộ filter với URL query.
- Debounce search.
- Preserve scroll/table state khi Inertia reload.
- Subscribe Reverb channels.
- Mở/đóng drawer theo selected id.

## 9. TypeScript conventions

- Dùng interface cho object shape.
- Dùng named exports.
- Không dùng enum; dùng const map.
- Tên boolean: `isLoading`, `hasError`, `isExpanded`, `canEdit`.
- Handler prefix `handle`: `handleSubmit`, `handleStatusChange`.
- Pagination response dùng type có sẵn trong `backend.d.ts`:
  - `IPaginationResponse`
  - `IErrorPaginationResponse`
  - `IPaginatedResponse`

## 10. Page design details

### `/admin/dashboard`

Mục tiêu: admin nhìn sức khỏe hệ thống trong ngày.

Sections:

- KPI cards: lead mới, đơn chốt, doanh số tạm tính, đơn lỗi.
- Status badges: chờ giao, đang giao, đã giao, đã thanh toán, đã hoàn.
- Revenue chart theo ngày.
- Lead source chart.
- Realtime activity feed.
- Top sales/top campaigns.

UX:

- Auto refresh qua Reverb.
- Date quick filter: hôm nay, 7 ngày, tháng này.
- Click KPI → đi tới màn filtered tương ứng.

### `/admin/reports/ceo`

Mục tiêu: CEO xem hiệu quả tổng hợp sale/marketing.

Sections:

- Filter bar dùng chung.
- Status summary badges.
- Sale performance table.
- Marketing performance table.
- KPI achievement cards.

UX:

- Table wide, sticky first columns.
- Export CSV/XLSX.
- Highlight total rows.

### `/admin/marketing/dashboard`

Mục tiêu: phân tích nguồn/campaign/UTM.

Sections:

- Filter nâng cao: team, source, UTM, channel, product.
- Source performance nested table.
- Totals: filter total + page total.
- Budget vs revenue chart.

UX:

- Expand/collapse campaign rows.
- Sparkline trong table cho closing rate/revenue.
- Cost/contact và budget/revenue ratio dùng màu cảnh báo.

### `/admin/marketing/revenue`

Mục tiêu: đo doanh số theo marketer với 19 metric.

Sections:

- Report filter bar.
- Metric legend collapsible.
- Revenue metric table.
- Total row sticky bottom nếu table dài.

UX:

- Cột dạng `MetricPair`: số lượng + doanh số.
- Tooltip giải thích công thức.
- Column visibility để giảm nhiễu.

### `/admin/sales/revenue`

Mục tiêu: đo doanh số theo sale/team.

Sections tương tự marketing revenue, khác grouping:

- Sale name/username.
- Team/team leader.
- Contacts, close rate, revenue, return/cancel rates.

UX:

- Click sale → mở drawer performance detail.
- Admin có thể filter sale/team/date/status.

### `/sales/workspace`

Mục tiêu: màn làm việc chính của telesale.

Sections:

- Pipeline tabs: khách mới, call 2-6, care 1-3, bỏ qua, chưa tác nghiệp, tất cả.
- Search phone/name/order code.
- Lead/order table.
- Order detail drawer.
- Quick actions: gọi, ghi chú, đổi kết quả, chốt đơn.

UX:

- Mobile/tablet friendly nếu sale dùng nhiều thiết bị.
- Row click mở drawer, không rời table.
- Badge rõ: trạng thái gọi, trạng thái chốt, trạng thái giao.
- Hotkeys tùy chọn: next lead, call, save note.

### `/sales/customers`

Mục tiêu: sales xem hồ sơ khách của mình.

Sections:

- Customer list.
- Customer detail drawer/page.
- Order history.
- Interaction timeline.
- Notes.

UX:

- Tìm theo SĐT là ưu tiên.
- Gộp trùng khách theo phone nếu backend hỗ trợ.

### `/admin/accounting`

Mục tiêu: kế toán xử lý thanh toán/đối soát.

Sections:

- Accounting KPI: COD chờ, CK, đã đối soát, lệch tiền.
- Orders table.
- Payment/reconciliation drawer.
- Bulk actions nếu cần.

UX:

- Cảnh báo lệch COD/phí/hoàn.
- Status badge rõ.
- Audit history cho thay đổi tiền.

### `/admin/warehouse/operations`

Mục tiêu: kho xử lý đơn giao/hoàn.

Sections:

- Warehouse status tabs.
- Orders table.
- Shipping info panel.
- Create/update shipment actions.

UX:

- Ưu tiên barcode/order code search.
- Bulk update trạng thái nếu cần.
- Highlight đơn thiếu địa chỉ/SĐT/sản phẩm.

### `/admin/warehouse/inventory`

Mục tiêu: quản lý sản phẩm/tồn kho.

Sections:

- Product/inventory table.
- Low stock cards.
- Inventory adjustment dialog.
- Product detail drawer.

UX:

- Tồn thấp màu warning.
- Hết hàng màu danger.
- Lọc theo kho/sản phẩm/danh mục.

### `/admin/orders/failed`

Mục tiêu: xử lý lỗi vận hành.

Sections:

- Failure summary by type.
- Failed orders table.
- Error detail drawer.
- Retry/resolve actions.

UX:

- Error reason phải nổi bật.
- Retry action có loading + result toast.
- Resolve cần confirm nếu irreversible.

### `/settings`

Mục tiêu: cấu hình cá nhân.

Sections:

- Profile.
- Theme.
- Notification preferences.
- Password/security.

UX:

- Theme preview cards.
- Save optimistic nếu an toàn.

## 11. Data table behavior chuẩn

Mọi bảng vận hành nên có:

- Server-side pagination.
- Server-side sort/filter nếu data lớn.
- Debounced search.
- Column visibility.
- Sticky header.
- Sticky first action/name column.
- Row density toggle: comfortable/compact.
- Empty state có hướng dẫn.
- Loading skeleton.
- Error state có retry.

## 12. Filter behavior chuẩn

Report filter nên dùng URL state để share link.

Pattern:

```text
User changes filter
  → update URL query
  → Inertia reload partial props
  → table/chart update
  → active filter chips update
```

Filter bar gồm:

- Date range.
- Date type.
- Source type.
- Delivery status.
- Reconciliation status.
- Product.
- Team/team leader.
- Sale/marketer.
- Warehouse.
- Shipping method.

## 13. Realtime frontend behavior

Hook: `useRealtimeDashboard`.

Events:

- `lead.ingested` → toast + increment badge + optional reload summary.
- `order.updated` → update row nếu row đang visible.
- `order.assigned` → sales notification.
- `payment.reconciled` → accounting badge update.
- `inventory.low_stock` → warning toast cho admin/kho.

Không nên reload toàn page khi có event. Ưu tiên partial refresh/small state update.

## 14. Accessibility

- Đủ contrast cho status badges.
- Button có label rõ, icon-only phải có `aria-label`.
- Dialog/drawer trap focus đúng.
- Table action dùng keyboard được.
- Form errors hiển thị cạnh input.
- Không chỉ dùng màu để biểu thị trạng thái; thêm text/icon.

## 15. Performance

- Dùng server-side pagination cho bảng lớn.
- Dùng lazy-loaded pages theo route nếu bundler hỗ trợ.
- Memoize columns/table config.
- Tránh render chart lớn không cần thiết.
- Inertia partial reload cho filter/report.
- Debounce search 300-500ms.
- Virtualize table nếu số dòng client-side lớn.

## 16. Recommended shadcn/ui components

Nên dùng:

- `button`
- `card`
- `badge`
- `table`
- `tabs`
- `sheet`
- `dialog`
- `dropdown-menu`
- `select`
- `calendar`
- `popover`
- `command`
- `toast/sonner`
- `skeleton`
- `separator`
- `tooltip`
- `alert`

## 17. MVP frontend checklist

- [ ] Admin layout + sidebar/topbar.
- [ ] Sales layout.
- [ ] Login page.
- [ ] Dashboard admin.
- [ ] Shared report filter bar.
- [ ] Reusable data table.
- [ ] Sale workspace.
- [ ] Customer profile/drawer.
- [ ] CEO report.
- [ ] Marketing dashboard.
- [ ] Marketing revenue report.
- [ ] Sale revenue report.
- [ ] Accounting page.
- [ ] Warehouse operations page.
- [ ] Inventory page.
- [ ] Failed orders page.
- [ ] Settings/theme page.
- [ ] Realtime toast/update hook.
