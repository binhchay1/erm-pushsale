# ERM SaleOps — Kiến trúc hệ thống

> **Đối tượng:** Developer, architect. Luồng nghiệp vụ người dùng: [BUSINESS_WORKFLOW.md](./BUSINESS_WORKFLOW.md). Route/API: [API_AND_ROUTES.md](./API_AND_ROUTES.md).

---

## 1. Tech stack

| Lớp | Công nghệ |
|-----|-----------|
| Backend | Laravel 13, Sanctum, Inertia, Reverb |
| Frontend | React 19, shadcn/ui, Radix UI, Recharts, Echo |
| Database | PostgreSQL / MySQL / SQLite |
| Realtime | Laravel Reverb + Laravel Echo |
| Auth web | Session (Inertia) |
| Auth API | Laravel Sanctum Bearer token |
| UI delivery | Inertia pages |

### Dev commands

```bash
composer run dev   # serve + reverb + dashboard:broadcast + vite
php artisan migrate --seed
```

---

## 2. Layered architecture

```text
HTTP / Inertia / API
    → Controllers (thin) + InteractsWithReportFilters
    → Form Requests / DTOs (ReportFilterData, MetricPairData)
    → Services / Use cases (Reports/*, Operations/*, Inventory/*, Leads/*)
    → Repositories (OrderRepositoryInterface → EloquentOrderRepository)
    → Models + Scopes (Order::applyReportFilter)
    → Database
```

### Design patterns

| Pattern | Vị trí | Mục đích |
|---------|--------|----------|
| **Repository** | `Contracts/Repositories`, `Repositories/` | Trừu tượng truy vấn Order |
| **DTO** | `Data/ReportFilterData` | Immutable filter transport |
| **Service / Use case** | `Services/Reports/*`, `Operations/*` | Nghiệp vụ từng màn |
| **Presenter** | `OrderOperationPresenter` | Model → JSON/Inertia props |
| **Strategy** | `RevenueMetricsCalculator` | Công thức metric (1)–(19) MKT & Sale |
| **Template Method** | `RevenueReportService` | `forMarketers` / `forSales` cùng pipeline |
| **Factory** | `ReportFilterData::fromRequest` | Chuẩn hóa query + scope sales |
| **Dependency Injection** | `AppServiceProvider` | Bind interface → implementation |

---

## 3. Phân quyền (6 role)

| Role | Mã | Scope dữ liệu |
|------|-----|---------------|
| Quản trị | `admin` | Toàn hệ thống |
| Telesale | `sales` | Chỉ `sale_user_id` = auth user |
| Marketing | `marketing` | Campaign, dashboard MKT của team |
| Kho | `warehouse` | Đơn kho, tồn, vận đơn |
| Chia số | `allocator` | Lead ingest, phân bổ |
| Kế toán | `accounting` | COD, đối soát |

**Nguyên tắc:**

- Middleware `role:{role}` trên route groups trong `web.php`.
- `ReportFilterData::fromRequest` ép `sale_id` khi user là sales.
- Repository/query không bypass scope — filter tại DTO + model scopes.

---

## 4. Frontend architecture

```text
resources/js/
  pages/Admin/, Sales/, Marketing/, Warehouse/, Accounting/, Allocator/
  components/reports/, operations/, charts/, data-table/, filters/, layout/
  hooks/use-report-search.ts, use-realtime-dashboard.ts
  providers/theme-provider.tsx
  types/backend.d.ts
```

**Nguyên tắc:**

- Page compose layout + components; logic filter trong hooks.
- Pagination: dùng `IPaginationResponse`, `IErrorPaginationResponse`, `IPaginatedResponse` từ `backend.d.ts`.
- Filter đồng bộ URL query → Inertia partial reload.
- Theme: `config/saleops.php`, localStorage `saleops-appearance`.

---

## 5. Domain model (entities)

| Entity | Mô tả |
|--------|-------|
| `users` | Nhân sự + role |
| `orders` | Đơn hàng, trạng thái sale/kho/giao/kế toán |
| `customers` | Hồ sơ khách |
| `products`, `warehouses`, `inventory_items` | Sản phẩm, kho, tồn |
| `lead_ingestions` | Log lead từ nền tảng ngoài |
| `integration_connections` | Cấu hình tích hợp platform |
| `marketing_sources` / campaigns | Nguồn, campaign, UTM |
| `order_operations` | Log gọi/chăm sóc/chốt |
| `warehouse_inventory_movements` | Biến động tồn kho |

---

## 6. Reporting engine

### Bộ lọc chung (`ReportFilterData`)

Dùng cho CEO, Marketing, Sale, Kế toán, Thủ kho:

```ts
{
  sourceType, dateFrom, dateTo, dateType,
  deliveryStatus, reconciliationStatus,
  discountMode: 'before_discount' | 'after_discount',
  parentProductId, productId,
  teamLeaderId, teamId, saleId,
  marketingTeamLeaderId, marketingTeamId, marketerId,
  warehouseId, shippingMethod,
  page, perPage, noClosingDateLimit
}
```

### MetricPair (cột Số lượng + Doanh số)

```ts
{ qty: number; revenue: number }
```

### Revenue report — 19 metric (Marketing & Sale)

| # | Field | Ý nghĩa |
|---|-------|---------|
| 1 | `closedOrders` | Đơn chốt |
| 2 | `confirmedDelivery` | Xác nhận giao hàng |
| 3 | `canceledShipping` | Hủy vận đơn |
| 4 | `transferredToCarrier` | Chuyển ĐVGH |
| 5 | `returned` | Đã hoàn |
| 6 | `returning` | Đang hoàn |
| 7 | `delivered` | Đã giao hàng |
| 8 | `paid` | Đã thanh toán |
| 9 | `successfulDelivery` | Giao thành công |
| 10–13 | rates | returnRate, shippingCancelRate, confirmRate, successRate |
| 14 | `contacts` | Contact |
| 15 | `closingRate` | qty(1)/contacts |
| 16–19 | productCount, AOV, revenueReturnRate, revenueCancelRate | |

---

## 7. Screen schemas (checklist developer)

> Khi thêm màn/bảng mới, implement đủ field dưới đây. Quy ước: `qty` = số lượng, `revenue` = VND, `rate` = % (0–100), `*At` = datetime ISO.

### §1 Báo cáo CEO (`/admin/reports/ceo`)

**StatusSummaryBadge:** `waitingDelivery`, `cancelWaybill`, `delivering`, `delivered`, `paid`, `returned`

**CeoSalePerformanceRow:** `stt`, `saleStaffId/Name/Username`, khách mới (`newContact`, `newClosed`, `newCloseRate`, `newProductQty`, `newEstRevenue`), khách cũ (`old*`), tổng (`totalEstRevenue`, `codFee`, `codSupport`, `bankTransfer`, `deposit`, `salesKpi`, `achievementRate`)

**CeoMarketingPerformanceRow:** `marketerId/Name`, `budget`, `contactPrice`, `budgetRevenueRatioNew/Total`

### §2 Dashboard Marketing

**Filter bổ sung:** `teamLeaderId`, `teamId`, `departmentId`, `utmCode`, `sourceName`, `contactPhone`, `advancedUtm`, `channel`

**MarketingSourceRow:** `id`, `stt`, `parentId`, `sourceName`, `productName`, `adChannel`, `utmSource/Campaign`, `budget`, `interactions`, `contacts`, `contactRate`, `costPerContact`, `closedOrders`, `closingRate`, `productQuantity`, `avgProductPerOrder`, `totalRevenue`, `revenueAfterDiscount`, `budgetRevenueRatio`, `budgetNetRevenueRatio`

**Totals:** `filterTotal`, `pageTotal`

### §3 BC doanh số Marketing / §4 BC doanh số Sale

Schema **MarketingRevenueRow** (19 metric) + `marketerId/Name` hoặc `saleId/Name/Username`, `isTotalRow`.

Sale filter thêm: `dateType = 'sale_received_data' | ...`

### §5 Sale tác nghiệp (`/sales/workspace`)

**Pipeline (`OperationStage`):** `new_customer`, `call_2`…`call_6`, `care_1`…`care_3`, `skipped`, `no_operation`, `all`

**SaleOperationRow:** `orderCode`, `sourceName`, `dataArrivedAt`, `saleId/Name/Group`, `assignedAt`, `customerName/Phone`, `phoneCarrier`, `customerNote`, `currentOperation`, `operationResult`, `nextOperationAt`, `followUpAfter/Remaining`, `products[]`, `subtotal`, `discount`, `vat`, `shippingFeeCollected`, `total`, `deposit`, `deliveryStatus`, `desiredDeliveryAt`

**OrderLineItem:** `productId`, `productName`, `sku?`, `quantity`, `unitPrice`

### §6 Hồ sơ khách hàng

Giống SaleOperationRow + `isDuplicatePhone`, `isReturningCustomer`, `allocationType`, `marketingId/Name/Team`, `closedAt`, `warehouseName`, `trackingCode`, `internalReconNote`

Filter: `duplicatePhone`, `oldCustomer`, `allocation`, `internalRecon`

### §7 Kế toán tác nghiệp

**AccountingShippingStatus tabs:** `waiting_waybill`, `delivering`, `delivered`, `paid`, `returned`, `cancel_waybill`, …

**AccountingOrderRow:** `orderCode`, `dataArrivedAt`, `closedAt`, sale/care fields, `warehouseName`, `carrierName`, `trackingNumber`, `shippingStatus`, `items[]`, totals, `amountToCollect`, `carrierServiceFee`, `shippingSupportFee`, `shippingAddress`, `eInvoiceId`

### §8 Thủ kho tác nghiệp

Gần AccountingOrderRow + `shippingNotes`, `eInvoiceStatus`, `codAmount`, `internalReconStatus`

### §9 Đơn hàng lỗi

Filter: `platform`, `warehouseId`, `shopId`, `partnerOrderId`, date range. Row: `partnerOrderId`, `errorDescription`, `updatedAt`. Action: `syncMissingOrders()`

### §10 Sản phẩm kho

**InventoryRow:** `warehouseId/Name`, `productId/Name`, `sku`, `uom`, `batchCode`, `expiryDate`, `locationCode`, `stockQuantity`, `pendingSalesQuantity`, `isDiscontinued`

Filter: `warehouseId`, `productId`, `locationCode`, `batchCode`, `businessStatus`, `productNameSearch`

---

## 8. Enums tham chiếu

| Enum | Giá trị |
|------|---------|
| **DeliveryStatus** | `waiting_waybill`, `delivering`, `delivered`, `paid`, `returned`, `returning`, `cancel_waybill`, `cannot_deliver`, `redelivery`, … |
| **DiscountMode** | `before_discount` \| `after_discount` |
| **ReconciliationStatus** | `pending`, `reconciled`, `mismatch` |
| **DateType** | `sale_received_data`, `closing_date`, `data_arrival`, `care_update`, … |
| **OperationStage** | Xem §5 pipeline |

---

## 9. Realtime (Reverb)

| Channel | Người nghe | Mục đích |
|---------|------------|----------|
| `dashboard.admin` | Admin | KPI, lead/order toàn hệ thống |
| `dashboard.sales.{userId}` | Sales | Lead/order được gán |
| `orders.{orderId}` | User có quyền | Chi tiết order |

| Event | Use case |
|-------|----------|
| `LeadIngested` / `lead.ingested` | Toast lead mới, refresh summary |
| `OrderCreated`, `OrderUpdated`, `OrderStatusChanged` | Cập nhật badge, row |
| `OrderAssigned` | Thông báo sales |
| `PaymentReconciled` | Accounting badge |
| `InventoryLowStock` | Cảnh báo kho |
| `stats.updated` | Dashboard KPI slice |

Frontend hook: `useRealtimeDashboard`. Không reload full page — partial state update.

---

## 10. Luồng lead ingestion

```text
Webhook/API → LeadIngestionService
  → Kiểm tra trùng external_id
  → Kiểm tra trùng SĐT (LEAD_DUPLICATE_WINDOW_DAYS)
  → LeadRoutingService (round_robin | least_load | random)
  → Tạo Order (operation_stage: new_customer)
  → Event LeadIngested → Reverb
  → Telesale workspace
```

---

## 11. Trạng thái triển khai (current vs planned)

> Cập nhật từ đối chiếu năng lực thực tế. ✅ = có, ⚠️ = một phần, ❌ = chưa.

| Hạng mục | Trạng thái | Ghi chú |
|----------|------------|---------|
| Phễu webhook/API lead | ✅ | 7+ nền tảng, admin UI `/admin/integrations` |
| Chống trùng SĐT | ✅ | `LEAD_DUPLICATE_WINDOW_DAYS` |
| Chia số telesale | ⚠️ | Round-robin / least_load — chưa online-aware / theo team |
| Workspace telesale | ✅ | Pipeline, gọi, chốt — VOIP thật ❌ |
| RBAC 6 role | ✅ | admin, sales, marketing, warehouse, allocator, accounting |
| Báo cáo CEO/MKT/Sale | ✅ | 19 metric, shared filters |
| Kho & tồn | ✅ | UI + movements, intake |
| Vận chuyển GHTK/GHN/VTP | ⚠️ | UI + webhook generic ✅; create order API thật ❌ |
| Webhook VC ngược | ✅ | `/api/v1/shipping/webhooks/{provider}` |
| Kế toán / COD | ✅ | UI đối soát |
| Realtime dashboard | ✅ | Reverb + `dashboard:broadcast` |
| Import Excel | ❌ | Roadmap |
| Shopee/Lazada OAuth pull | ❌ | Webhook generic ⚠️ |
| Click-to-call VoIP | ❌ | Roadmap |
| Cron sync carrier 24/7 | ⚠️ | Queue driver có, job nghiệp vụ chưa đầy đủ |

### Milestone đã đạt

- [x] Auth đa role, theme, notification, Reverb
- [x] Cấu trúc tổ chức + trưởng nhóm + phân nhánh team
- [x] Domain DB + seed demo (`SaleOpsDemoSeeder`)
- [x] 10 nhóm màn UI + Services theo data model
- [x] Admin tích hợp + nhật ký lead (`/admin/integrations`, `/admin/leads`)
- [x] Webhook lead → Order + Reverb `lead.ingested`
- [x] Webhook vận chuyển + đối soát (`/admin/shipping/reconciliation`)
- [x] Báo cáo business (`/admin/reports/business`)
- [x] Xếp hạng (`/admin/rankings`)
- [ ] Vận chuyển thật (GHTK, GHN, VTP) + webhook production map status chi tiết

### Roadmap gợi ý

1. Shipping module — create order + map status từng hãng
2. VOIP — click-to-call (Stringee, OmiCall…)
3. Import Excel lead manual
4. Shopee/Lazada driver payload chuẩn + OAuth refresh
5. Online-aware routing — chỉ chia cho sale đang online

---

## 12. Mở rộng codebase

1. **Thêm màn** → Service + Controller invokable + Page JSX + entry trong `NavigationService`
2. **Thêm field** → migration + cập nhật §7 checklist + Presenter/Service
3. **Event realtime** → Event class + broadcast trên Order lifecycle
4. **Thêm metric báo cáo** → `RevenueMetricsCalculator` strategy

---

## 13. UI design system (tóm tắt)

Style **Enterprise SaaS Operations Dashboard**: sidebar + topbar, bảng dense sticky, badge semantic, drawer chi tiết.

| Trạng thái | Màu |
|------------|-----|
| Thành công / đã thanh toán | Green |
| Đang xử lý / đang giao | Blue |
| Chờ xử lý | Amber |
| Lỗi / hủy / hoàn | Red |

Theme: `brand`, `ocean`, `sunset`, `violet`.
