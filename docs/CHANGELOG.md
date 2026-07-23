# ERM SaleOps — Changelog

Timeline milestone và spec — **mới nhất trước**. Chi tiết kỹ thuật: [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md), [API_AND_ROUTES.md](./API_AND_ROUTES.md).

---

## 2026-07-23 — V98 Warehouse 5.3 voucher/inventory linkage

- Rebuild 3 template màn **5.3.1–5.3.3** từ `template-ten-1`: Phiếu nhập/xuất kho, Danh sách phiếu xuất/nhập kho, Lịch sử nhập/xuất kho.
- Scope CSS mới `pushsale-v98-warehouse-flow-contract.css` cho nhóm kho, không ghi đè báo cáo/dashboard/menu đã làm.
- Backend phiếu kho chạy transaction: voucher + voucher line + tồn kho + movement cùng commit/rollback.
- Movement sinh từ phiếu kho được link bằng `reference_type=warehouse_voucher`, `reference_id`; 5.3.2 và 5.3.3 dùng cùng dữ liệu business.
- Inventory seeder tạo thêm voucher demo để kiểm thử giao diện kho có dữ liệu thật.
- Thêm test `WarehouseVoucherBusinessLinkTest`.
- Thêm `PROJECT_VERSION_LOG.md` để tổng hợp ngắn toàn bộ version, tránh đọc quá nhiều handoff rời.

---

## 2026-07-14 — V13 Landing Connection + layout/modal hardening

- Thay luồng tạo campaign bằng menu 2.4.1 **Kết nối Landing**.
- Thêm 4 bảng cấu hình connection/source/product/sale và khóa ngoại truy vết trên session/lead/order.
- Thêm public endpoint direct form submit, token riêng từng source, không cần SDK JS.
- Gộp Landing chính + upsale bằng `ps_flow`, fallback SĐT 90 giây, idempotency namespace theo source.
- Giá/item/chiết khấu client không được dùng; order dựng từ sản phẩm backend và tự tính total.
- Mapping gói so khớp chính xác, chặn `ps_flow` nối chéo connection và soft-delete source để bảo toàn audit lịch sử.
- Chia Sale theo danh sách riêng của connection; upsale không phân Sale lần hai.
- Làm lại trang 2.4.1 theo template Pushsale bằng backend thật, có CRUD, batch delete, filter, pagination.
- Redirect route campaign cũ; write cũ trả 410.
- Chuẩn hóa shell để bỏ khoảng trắng đầu trang và đưa mọi modal vào đúng viewport.
- Build Vite production thành công; thêm test `LandingConnectionFlowTest`.

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

---

## 2026-07-23 — V98 Warehouse 5.3 voucher/inventory linkage

- Rebuild 3 template màn **5.3.1–5.3.3** từ `template-ten-1`: Phiếu nhập/xuất kho, Danh sách phiếu xuất/nhập kho, Lịch sử nhập/xuất kho.
- Scope CSS mới `pushsale-v98-warehouse-flow-contract.css` cho nhóm kho, không ghi đè báo cáo/dashboard/menu đã làm.
- Backend phiếu kho chạy transaction: voucher + voucher line + tồn kho + movement cùng commit/rollback.
- Movement sinh từ phiếu kho được link bằng `reference_type=warehouse_voucher`, `reference_id`; 5.3.2 và 5.3.3 dùng cùng dữ liệu business.
- Inventory seeder tạo thêm voucher demo để kiểm thử giao diện kho có dữ liệu thật.
- Thêm test `WarehouseVoucherBusinessLinkTest`.
- Thêm `PROJECT_VERSION_LOG.md` để tổng hợp ngắn toàn bộ version, tránh đọc quá nhiều handoff rời.

---

## 2026-07-14 — V14 Financial control, Landing budget và template-five

- Thêm ngân sách tổng/ngày và kỳ chạy vào Kết nối Landing; phân bổ ngày chính xác, hỗ trợ thực chi/kế hoạch/mixed.
- Làm lại Admin Dashboard bằng dữ liệu backend thật: doanh số chốt, doanh thu ghi nhận, tiền đã thu, COD, Marketing, COGS, vận chuyển, nhân sự, chi phí vận hành, lợi nhuận và tồn kho.
- Snapshot giá vốn vào `order_items`; combo fallback theo thành phần.
- Thêm `PayrollCostService`; KPI tháng và dashboard dùng chung công thức ngày công + thưởng doanh số chốt.
- Thêm cảnh báo vượt ngân sách, COD chưa thu, lỗ ròng, tồn kho thấp, lương dự kiến và nguy cơ nhập trùng chi phí nhân sự.
- Chuẩn hóa các trường tiền sang VND.
- Làm sạch/scope 54 template từ template-five, phục hồi vendor CSS/font cần thiết và giữ backend thật cho từng mã menu.

---

## 2026-07-15 — V17 UI system, dynamic filters và modal contract

- Tách CSS thành ba entry: shared (`app.css`), public/login (`public.css`) và ERM nội bộ (`pushsale.css`).
- Loại các patch CSS V12/V13 khỏi source; `pushsale-system-v17.css` trở thành contract cuối.
- Scope 79 template; loại generated Select2/Chosen DOM, script và dữ liệu tenant Pushsale capture.
- Chuẩn hóa filter Bootstrap thành grid 12 cột, tự bỏ cột trống đầu hàng.
- Chuẩn hóa action cell, không còn border/hộp con quanh icon.
- Thêm `PushsaleModal`, clamp mọi modal vào viewport, header/footer cố định và body cuộn.
- Chuyển các modal hồ sơ khách hàng và editor template sang modal dùng chung.
- Lịch sử đăng nhập lấy user/role/company thật và ghi audit login success/failed/blocked/logout.
- Kết nối filter sản phẩm, phân loại, trạng thái, quyền Marketing/Sale/CSKH, sản phẩm cha và trưởng nhóm vào backend thật.
- Thay bảng xếp hạng Sales capture bằng top 10 backend động.

---

## 2026-07-15 — V18 Historical reporting, daily facts và monthly archive

- Thêm hot/cold report router: hôm nay live từ raw + Redis, ngày cũ từ daily materialized facts.
- Thêm daily facts cho lead, order, product/upsale, shipping cashflow và inventory.
- Thêm closure, full fact checksum, source fingerprint và verify/repair command.
- Thêm dirty-date observer cho dữ liệu đến muộn; reopen ngày cũ và invalidate snapshot tự động.
- Thêm snapshot DB nén cho kỳ quá khứ đã đóng, phân tách theo tenant/user/filter.
- Thêm archive raw theo bảng `*_YYYY_MM`, copy chunk, full-row SHA-256 và manifest.
- Không purge mặc định; orders/order_items/shipments luôn được coi là mutable.
- Nối snapshot vào dashboard, báo cáo, ranking, hourly, đối soát giao vận và các màn operations nặng.
- Warm snapshot mặc định chỉ cho Admin/leader để tránh burst tài nguyên.
