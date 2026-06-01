# Admin CEO Dashboard — Layout Redesign

## Mục tiêu

Loại bỏ khoảng trống UI trên màn **Tổng quan CEO** (`/admin/dashboard`) bằng cách tái cấu trúc grid chart và thêm chart **Lead 7 ngày** để cân bằng visual.

## Bối cảnh

### Vấn đề hiện tại

File `resources/js/pages/Admin/Dashboard.jsx` dùng một grid `lg:grid-cols-3` chứa 3 chart:

1. `RevenueAreaChart` — `col-span-2` (chiếm 2/3 hàng 1)
2. `OrdersBarChart` — 1 cột (1/3 hàng 1)
3. `LeadSourcePieChart` — 1 cột, xuống hàng 2 → **2 cột trống** bên phải

### Phạm vi

| Trong phạm vi | Ngoài phạm vi |
|---------------|---------------|
| Layout `Admin/Dashboard.jsx` | Dashboard Marketing, Sales, Warehouse, … |
| Skeleton loading admin | Refactor global `RevenueAreaChart` col-span |
| Thêm `lead_series` vào static `adminSnapshot()` | Chart tỷ lệ giao 7 ngày (series mới) |
| Cập nhật test admin dashboard | Thay đổi thứ tự Phễu / Rankings / Alerts |
| Realtime payload (đã có qua `dashboardSnapshot`) | Compact variant `OpsAlerts` |

## Giải pháp đã chọn

**Phương án B — Sửa đúng chỗ trống + chart mới**

Tách thành **2 grid riêng** (`lg:grid-cols-3`), mỗi hàng tự khép kín 3 cột.

### Wireframe

```
┌─────────────────────────────────────────────────────────────┐
│ KPI × 5 (DashboardKpiGrid)                                  │
├──────────────────────────────────────┬──────────────────────┤
│ Doanh thu 7 ngày (2/3)               │ Đơn phát sinh (1/3)  │
├──────────────┬──────────────────────────────────────────────┤
│ Nguồn lead   │ Lead 7 ngày (2/3) — CHART MỚI              │
│ hôm nay (1/3)│                                              │
├─────────────────────────────────────────────────────────────┤
│ Phễu chuyển đổi (full width)                                 │
├──────────────────────────────┬──────────────────────────────┤
│ Top sale (1/2)               │ Top nguồn (1/2)              │
├─────────────────────────────────────────────────────────────┤
│ Cảnh báo vận hành (full width)                              │
└─────────────────────────────────────────────────────────────┘
```

## Thay đổi frontend

### 1. `resources/js/pages/Admin/Dashboard.jsx`

Thay block grid hiện tại:

```jsx
<div className="grid gap-4 lg:grid-cols-3">
  <RevenueAreaChart ... />
  <OrdersBarChart ... />
  <LeadSourcePieChart ... />
</div>
```

Bằng hai hàng:

```jsx
<div className="grid gap-4 lg:grid-cols-3">
  <RevenueAreaChart
    data={stats.revenue_series}
    title="Doanh thu 7 ngày"
    description="Doanh thu từ đơn delivered/paid"
  />
  <OrdersBarChart
    data={stats.orders_series}
    title="Đơn phát sinh 7 ngày"
    description="Số đơn tạo mới theo ngày"
  />
</div>

<div className="grid gap-4 lg:grid-cols-3">
  <LeadSourcePieChart data={stats.lead_sources} title="Nguồn lead hôm nay" />
  <RevenueAreaChart
    data={stats.lead_series}
    title="Lead 7 ngày"
    description="Lead ingest theo ngày"
    valueFormatter={(v) => formatNumber(v)}
    yTickFormatter={(v) => String(v)}
  />
</div>
```

Import thêm `formatNumber` từ `@/lib/format`.

**Lưu ý:** `RevenueAreaChart` giữ `col-span-full lg:col-span-2` nội bộ — chart thứ hai mỗi hàng tự chiếm 1 cột; chart đầu mỗi hàng chiếm 2 cột.

### 2. `resources/js/components/dashboard/DashboardSkeleton.jsx`

Cập nhật skeleton admin để phản ánh 2 hàng chart (thay vì 1 grid 3 cột):

- Hàng 1: wide skeleton (`col-span-2` pattern) + compact skeleton
- Hàng 2: compact skeleton + wide skeleton

Có thể thêm prop `chartRows: 2` vào `roleCopy.admin` hoặc hardcode 2 hàng cho admin trong phần render chart skeleton.

### 3. Null-safe rendering

`useRealtimeDashboard` có thể trả `stats = null` trước khi deferred load xong. `AdminDashboardContent` hiện truy cập `stats.revenue_series` trực tiếp — giữ nguyên pattern hiện tại (Deferred fallback đã có skeleton). Chart mới dùng `stats.lead_series ?? []` qua prop `data` (component đã xử lý `data ?? []`).

## Thay đổi backend

### `app/Services/DashboardStatsService.php`

Thêm `lead_series` vào static fallback `adminSnapshot()` (khi không có filter):

```php
'lead_series' => self::dailyLeadSeries(7),
```

Method `dailyLeadSeries()` đã tồn tại (dùng bởi marketing/allocator snapshot).

**Filtered path:** `dashboardSnapshot()` admin đã có `lead_series` qua `$this->metrics->leadSeries($user, $filter)` — không cần sửa.

### Realtime broadcast

`BroadcastDashboardStatsCommand` / `DashboardStatsUpdated` dùng `DashboardStatsService::adminSnapshot($user, $filter)` hoặc tương đương — field `lead_series` sẽ có sẵn sau khi static path được bổ sung. Không cần event payload riêng.

## Kiểm thử

### `tests/Feature/Admin/DashboardStatsTest.php`

1. Trong `test_admin_dashboard_uses_real_stats_shape`: thêm `->has('stats.lead_series')` sau `lead_sources`.
2. Trong `test_dashboard_stats_service_returns_funnel_rankings_and_alerts`: thêm `$this->assertArrayHasKey('lead_series', $stats)` và assert shape `{ label, value }` (7 phần tử).

### Manual QA

- Mở `/admin/dashboard` viewport `≥ lg`: không còn vùng trắng 2 cột bên phải hàng lead source.
- Chart Lead 7 ngày hiển thị số nguyên, không format VND.
- Skeleton loading khớp layout 2 hàng.
- Realtime cập nhật không làm mất chart lead series.

## Responsive

| Breakpoint | Hành vi |
|------------|---------|
| `< lg` | Mỗi card full width, xếp dọc theo thứ tự DOM |
| `≥ lg` | Mỗi hàng: 2/3 + 1/3 |

## Rủi ro & giảm thiểu

| Rủi ro | Giảm thiểu |
|--------|------------|
| `lead_series` thiếu ở static snapshot | Bổ sung trong `adminSnapshot()` + test |
| Marketing dashboard vẫn có gap tương tự | Ngoài phạm vi spec này; ghi chú follow-up |
| Hai metric “lead” cùng hàng | Pie = phân bổ nguồn hôm nay; line = xu hướng 7 ngày — bổ sung góc nhìn khác nhau |

## Acceptance criteria

- [ ] Không còn khoảng trống 2 cột trên desktop tại hàng chart lead.
- [ ] Chart **Lead 7 ngày** hiển thị đúng dữ liệu và format số.
- [ ] API/static snapshot trả `lead_series` với 7 điểm `{ label, value }`.
- [ ] Test feature admin dashboard pass.
- [ ] Skeleton admin phản ánh layout mới.

## Follow-up (không làm trong spec này)

- Áp dụng pattern 2 hàng grid cho Marketing dashboard (cùng root cause).
- Chart tỷ lệ giao thành công 7 ngày nếu product yêu cầu thêm.
