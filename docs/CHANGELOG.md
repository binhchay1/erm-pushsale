# ERM SaleOps — Changelog

Timeline milestone và spec — **mới nhất trước**. Chi tiết kỹ thuật: [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md), [API_AND_ROUTES.md](./API_AND_ROUTES.md).

---

## 2026-06-10 — Phân quyền sơ đồ tổ chức, đơn hoàn & doanh số theo team

- **Sơ đồ tổ chức theo business:** `OrgStructureService` — Admin thấy toàn công ty; **Trưởng bộ phận** (`org_level = head`, mỗi ngành 1 người) thấy toàn ngành; **Leader & nhân viên chỉ thấy team của mình**. Chỉ admin tạo/phân chia team (`/admin/teams`). `OrganizationSeeder` dựng đúng mô hình: ngành → trưởng bộ phận → team nhỏ (leader) → nhân viên (chuỗi `manager_user_id`).
- **Tab "Đơn hoàn" cho Thủ kho:** `WarehouseOperationService` gom tab trạng thái thành nhóm (Chờ vận đơn / Lấy hàng / Đang giao / Đã giao / Đã thanh toán / **Đơn hoàn** / Đã hủy). Nút **"Nhận hàng hoàn"** (dialog nhập lý do) → `InventoryReturnService` cộng lại tồn kho (idempotent, movement type `return`), lưu `orders.return_reason` + `return_restocked_at` (migration `add_return_fields_to_orders`). Route POST `*/shipping/orders/{order}/receive-return` (admin + warehouse).
- **Doanh số theo team (Sales):** `SalesTeamTreeService` — cây Trưởng bộ phận → Team (leader) → Telesale với %chốt + doanh thu, hiển thị trên `/admin/sales/performance` (chỉ admin).
- **Báo cáo doanh số gọn lại:** `revenueReportFilterFields` — bỏ ô tìm tên/SĐT khỏi BC doanh số Sale/Marketing; màn Marketing lọc theo **NV marketing** (`marketer_id`) thay vì NV sales. Fix route filter cho `/marketing/revenue`.
- **Filter kỹ thuật:** `ReportFilterData::withoutDeliveryStatus()` để màn có tab gộp nhóm lọc trạng thái in-memory thay vì SQL.

---

## 2026-06-10 — Tái thiết kế Thủ kho tác nghiệp & dọn dẹp schema

- **Màn Thủ kho làm lại:** `WarehouseOperationService` + `WarehouseOrderTable` — chỉ hiển thị cột phục vụ kho (Mã đơn, KH/địa chỉ, SP/SKU/SL, COD, trạng thái VC, hành động). Bỏ cột Telesale (nguồn MKT, sale, lịch sử gọi).
- **Tạo / In vận đơn tại chỗ:** nút "Tạo vận đơn" (POST create-shipment) và "In vận đơn" (label PDF) ngay trên bảng; modal chi tiết tái sử dụng `ShippingOrderDetailModal`.
- **Chặn hết kho:** `ShippingOrderController::createShipment` + `CreateShipmentService::createForOrder` từ chối tạo vận đơn khi tồn < SL đặt ("Hết hàng trong kho"); UI hiện nút đỏ disabled kèm chi tiết thiếu.
- **Cleanup schema:** migration `cleanup_unused_columns_from_tables` xóa `orders.sales_kpi`, `integration_connections.metadata`, `shipping_partner_connections.metadata` (audit toàn bộ usage); model tương ứng đã gỡ khỏi `$fillable`/`$casts`.

---

## 2026-06 — Báo cáo & vận hành mở rộng

- **Campaign report & performance:** `/admin/marketing/campaign-report`, `/admin/sales/performance`, `/sales/performance`, `/marketing/campaign-report`.
- **Phân bổ lead thủ công:** `ManualLeadAllocationController`, `/admin/leads/allocate`, `/allocator/leads/allocate`.
- **Nhập kho:** `InventoryIntakeController`, POST intake trên admin/warehouse inventory.
- **Biến động tồn:** migration `warehouse_inventory_movements`, `InventoryDeductionService`, `InventoryIntakeService`.
- **Export báo cáo:** `ReportCsvExporter`, `ReportExportButton`.
- **Marketing team tree / KPI hero:** components `MarketingTeamTree`, `MarketingKpiHero`.

---

## 2026-06-01 — Admin CEO Dashboard layout redesign

**Spec:** Admin dashboard grid — loại khoảng trống 2 cột trên desktop.

- Tách chart thành **2 grid** `lg:grid-cols-3` (mỗi hàng 2/3 + 1/3).
- Thêm chart **Lead 7 ngày** (`stats.lead_series`) cạnh pie nguồn lead hôm nay.
- Backend: bổ sung `lead_series` vào `DashboardStatsService::adminSnapshot()`.
- Skeleton admin cập nhật 2 hàng chart.
- **Acceptance (spec):** layout desktop không gap; format số nguyên cho lead series; test `Admin/DashboardStatsTest`.

**Follow-up (chưa làm):** pattern tương tự cho Marketing dashboard; chart tỷ lệ giao 7 ngày.

---

## 2026-05-28 — Login, RBAC, thống kê checklist

**Spec:** Chuẩn bị login/phân quyền; thiết kế thống kê và xếp hạng.

### Đã hoàn thành (P0)

- [x] Login/logout Inertia, redirect theo role, session ổn định.
- [x] **6 role:** `admin`, `sales`, `marketing`, `warehouse`, `allocator`, `accounting` — seed demo từng role.
- [x] Middleware route guard backend; sidebar theo role; URL trực tiếp bị chặn.
- [x] Route chính: admin dashboard/reports, sales workspace/customers, marketing campaigns, warehouse ops/inventory, allocator workspace, accounting workspace.
- [x] QA login từng role, console/log sạch.

### Đang / chưa hoàn thành (P1–P2)

- [ ] Login responsive + keyboard (Enter submit).
- [ ] Dashboard KPI filter date range hoàn thiện (một phần đã có trên admin dashboard).
- [ ] Ranking sales/marketing/warehouse UI đầy đủ (admin `/admin/rankings` ✅; warehouse ranking ⚠️).
- [ ] Export báo cáo (CSV ✅ một phần).
- [ ] So sánh kỳ trước, drilldown KPI, permission chi tiết.

---

## 2026-05 — MVP core (từ PROJECT milestone)

### Foundation

- [x] Auth đa role, theme (`brand`/`ocean`/`sunset`/`violet`), notification, Reverb.
- [x] Cấu trúc tổ chức: trưởng nhóm, phân nhánh team (`/org-chart`).
- [x] Domain DB + `SaleOpsDemoSeeder` (12 đơn mẫu `PS-OPS-00001`…`00012`).

### 10 nhóm màn UI

- [x] Báo cáo CEO, Dashboard MKT, BC doanh số MKT/Sale.
- [x] Sale tác nghiệp, Hồ sơ KH, Kế toán, Thủ kho, Đơn lỗi, Sản phẩm kho.
- [x] Services/Repositories theo data model checklist.

### Tích hợp & realtime

- [x] Admin UI tích hợp (`/admin/integrations`) + nhật ký lead (`/admin/leads`).
- [x] Webhook lead → Order + event `lead.ingested`.
- [x] Webhook vận chuyển generic + màn đối soát (`/admin/shipping/reconciliation`).
- [x] Báo cáo business (`/admin/reports/business`).
- [x] Xếp hạng doanh thu (`/admin/rankings`).

### Chưa đạt

- [ ] Vận chuyển thật GHTK/GHN/VTP — create order production + map status chi tiết.
- [ ] VoIP click-to-call.
- [ ] Import Excel lead.
- [ ] Online-aware lead routing.

---

## Roadmap tóm tắt

| Ưu tiên | Hạng mục |
|---------|----------|
| P0 | ✅ Auth, RBAC 6 role, core routes, seed |
| P1 | Dashboard KPI, ranking UI, export |
| P2 | Shipping API thật, VoIP, Shopee/Lazada OAuth, import Excel |
| P3 | Permission granular, so sánh kỳ, drilldown |
