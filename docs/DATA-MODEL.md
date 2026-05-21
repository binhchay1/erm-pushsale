# ERM SaleOps — Biến dữ liệu (theo UI tham chiếu pushsale.vn)

> **Lưu ý:** Dự án triển khai là **ERM SaleOps**, không phải pushsale.vn. Cấu trúc field bám sát UI gốc để map API/DB sau này.

## Quy ước chung

| Ký hiệu | Ý nghĩa |
|---------|---------|
| `qty` | Số lượng |
| `revenue` | Doanh số (VND) |
| `rate` | Tỷ lệ % (0–100) |
| `Filter*` | Tham số query bộ lọc |
| `*At` | datetime ISO |

### Bộ lọc dùng chung (nhiều màn)

```ts
// ReportFilters — CEO, Marketing, Sale, Kế toán, Thủ kho…
{
  sourceType: string;           // "Chuẩn …" / campaign source
  dateFrom: string;
  dateTo: string;
  dateType: string;             // Kiểu ngày: data về, chốt đơn, sale nhận data…
  deliveryStatus: string | null;
  reconciliationStatus: string | null;  // Đối soát
  discountMode: 'before_discount' | 'after_discount';
  parentProductId: string | null;
  productId: string | null;
  teamLeaderId: string | null;
  teamId: string | null;
  saleId: string | null;
  marketingTeamLeaderId: string | null;
  marketingTeamId: string | null;
  marketerId: string | null;
  warehouseId: string | null;
  shippingMethod: string | null;  // PTGH
  page: number;
  perPage: number;              // limit_value, mặc định 20
  noClosingDateLimit: boolean;
}
```

### MetricPair (cột Số lượng + Doanh số)

```ts
{ qty: number; revenue: number }
```

---

## 1. Báo cáo CEO (`CeoReport`)

**Màn:** Báo cáo CEO — badge trạng thái + bảng SALE + bảng MARKETING.

### 1.1 Filter (bổ sung)

| Biến | Mô tả |
|------|--------|
| `FilterSourceType` | Nguồn/chuẩn hệ thống |
| `FilterNoClosingDateLimit` | Không giới hạn ngày chốt |

### 1.2 StatusSummaryBadge

```ts
{
  waitingDelivery: number;      // CHỜ GIAO
  cancelWaybill: number;        // HỦY VẬN ĐƠN
  delivering: number;           // ĐANG GIAO
  delivered: number;            // ĐÃ GIAO
  paid: number;                 // ĐÃ THANH TOÁN
  returned: number;             // ĐÃ HOÀN
}
```

### 1.3 CeoSalePerformanceRow

| Field | Kiểu | UI |
|-------|------|-----|
| `stt` | int | STT |
| `saleStaffId` | string | |
| `saleStaffName` | string | Tên hiển thị |
| `saleUsername` | string | (username) |
| **Khách mới** | | |
| `newContact` | int | Tiếp xúc |
| `newClosed` | int | Chốt |
| `newCloseRate` | float | % chốt |
| `newProductQty` | int | SP |
| `newEstRevenue` | decimal | DS tạm tính |
| **Khách cũ** | | |
| `oldContact` | int | |
| `oldClosed` | int | |
| `oldCloseRate` | float | |
| `oldProductQty` | int | |
| `oldEstRevenue` | decimal | |
| **Tổng** | | |
| `totalEstRevenue` | decimal | DS tạm tính tổng |
| `codFee` | decimal | Phí COD |
| `codSupport` | decimal | Hỗ trợ COD |
| `bankTransfer` | decimal | CK |
| `deposit` | decimal | Đặt cọc |
| `salesKpi` | decimal | KPI |
| `achievementRate` | float | % đạt KPI |

### 1.4 CeoMarketingPerformanceRow

| Field | Kiểu | UI |
|-------|------|-----|
| `stt` | int | |
| `marketerId` | string | |
| `marketerName` | string | |
| `budget` | decimal | Ngân sách |
| `contactPrice` | decimal | Giá contact |
| `budgetRevenueRatioNew` | float | % NS/DSTT khách mới |
| `budgetRevenueRatioTotal` | float | % NS/DSTT tổng |
| *(các cột tương tự sale nếu bật trên UI)* | | |

---

## 2. Dashboard Marketing (`MarketingDashboard`)

**Màn:** Dashboard marketing — bảng nguồn + hiệu quả, nested row.

### 2.1 Filter (bổ sung)

| Biến | Mô tả |
|------|--------|
| `FilterTeamLeaderId` | Trưởng nhóm |
| `FilterTeamId` | Nhóm |
| `FilterDepartmentId` | Phòng |
| `FilterUtmCode` | UTM |
| `FilterSourceName` | Tên nguồn |
| `FilterContactPhone` | SĐT |
| `FilterAdvancedUtm` | boolean | UTM nâng cao |
| `FilterChannel` | Kênh ads |

### 2.2 MarketingSourceRow

| Field | Kiểu | Ghi chú |
|-------|------|---------|
| `id` | string | |
| `stt` | int | |
| `parentId` | string? | null = dòng cha |
| `isExpanded` | boolean | |
| `sourceName` | string | Tên nguồn |
| `productName` | string | |
| `adChannel` | string | Youtube, Facebook ads… |
| `utmSource` | string | |
| `utmCampaign` | string | Dòng con |
| `budget` | decimal | (1) |
| `interactions` | int | (2) Tương tác |
| `contacts` | int | (3) Contact |
| `contactRate` | float | (4) = contacts/interactions×100 |
| `costPerContact` | decimal | (5) = budget/contacts |
| `closedOrders` | int | Đơn chốt |
| `closingRate` | float | (7) % — sparkline đỏ |
| `productQuantity` | int | (8) |
| `avgProductPerOrder` | float | (9) |
| `totalRevenue` | decimal | (10) — sparkline xanh |
| `revenueAfterDiscount` | decimal | (11) — sparkline xanh dương |
| `budgetRevenueRatio` | float | (12) % |
| `budgetNetRevenueRatio` | float | (13) % |

### 2.3 MarketingDashboardTotals

```ts
{
  filterTotal: Partial<MarketingSourceRow>;  // Tổng filter
  pageTotal: Partial<MarketingSourceRow>;   // Tổng trang
}
```

---

## 3. Báo cáo doanh số Marketing (`MarketingRevenueReport`)

**Màn:** Báo cáo DS marketing — công thức (1)–(19) + bảng theo marketer.

### 3.1 MarketingRevenueRow (theo công thức legend)

| # | Field | MetricPair / scalar | Công thức UI |
|---|-------|---------------------|--------------|
| 1 | `closedOrders` | MetricPair | Đơn chốt |
| 2 | `confirmedDelivery` | MetricPair | Xác nhận giao hàng |
| 3 | `canceledShipping` | MetricPair | Hủy vận đơn |
| 4 | `transferredToCarrier` | MetricPair | Chuyển ĐVGH |
| 5 | `returned` | MetricPair | Đã hoàn |
| 6 | `returning` | MetricPair | Đang hoàn |
| 7 | `delivered` | MetricPair | Đã giao hàng |
| 8 | `paid` | MetricPair | Đã thanh toán |
| 9 | `successfulDelivery` | MetricPair | Giao thành công |
| 10 | `returnRate` | float | (5)/(4) |
| 11 | `shippingCancelRate` | float | (3)/(1) |
| 12 | `confirmRate` | float | (2)/(1) |
| 13 | `successRate` | float | (9)/(4) |
| 14 | `contacts` | int | Contact |
| 15 | `closingRate` | float | qty(1)/contacts |
| 16 | `productCount` | int | Số SP |
| 17 | `averageOrderValue` | decimal | revenue(1)/qty(1) |
| 18 | `revenueReturnRate` | float | revenue(5)/revenue(2)×100 |
| 19 | `revenueCancelRate` | float | (rev3+rev_hủy_dở)/rev(1)×100 |

| Field | Kiểu |
|-------|------|
| `marketerId` | string |
| `marketerName` | string |
| `isTotalRow` | boolean |

---

## 4. Báo cáo doanh số Sale chi tiết (`SaleRevenueReport`)

Cùng schema **MarketingRevenueRow** (19 metric), thay `marketerName` →:

| Field | Kiểu |
|-------|------|
| `saleId` | string |
| `saleName` | string |
| `saleUsername` | string |

Filter thêm: `FilterDateType = 'sale_received_data' | ...`

---

## 5. Sale tác nghiệp (`SaleOperations`)

**Màn:** Danh sách lead/đơn — pipeline gọi + bảng wide.

### 5.1 Filter (bổ sung)

| Biến | Mô tả |
|------|--------|
| `FilterHideNoPhone` | Ẩn tác nghiệp không số |
| `FilterDataSourceId` | Nguồn dữ liệu |
| `FilterOperationStatus` | Trạng thái tác nghiệp |
| `FilterOperationResult` | Kết quả tác nghiệp |
| `FilterClosingStatus` | Trạng thái chốt đơn |
| `FilterBaseProductId` | Sản phẩm gốc |

### 5.2 OperationStatusTab (pipeline)

```ts
type OperationStage =
  | 'new_customer'      // Khách mới
  | 'call_2' | 'call_3' | 'call_4' | 'call_5' | 'call_6'
  | 'care_1' | 'care_2' | 'care_3'
  | 'skipped'
  | 'no_operation'
  | 'all';

// Tab: { stage, label, count, totalCount? }
```

### 5.3 SaleOperationRow (Lead/Order line)

| Field | Kiểu | UI cột |
|-------|------|--------|
| `id` | string | |
| `orderCode` | string | Mã đơn |
| `sourceName` | string | Nguồn dữ liệu |
| `dataArrivedAt` | datetime | Ngày data về |
| `saleId` | string | |
| `saleName` | string | Sale |
| `saleGroup` | string | |
| `assignedAt` | datetime | Ngày nhận data |
| `customerName` | string | Họ tên |
| `customerPhone` | string | SĐT |
| `phoneCarrier` | string | VIETTEL, VINAPHONE… |
| `customerNote` | string | Tin nhắn / địa chỉ |
| `currentOperation` | string | TN cần (Khách mới…) |
| `operationResult` | string | Kết quả (dropdown) |
| `nextOperationAt` | datetime? | TN tiếp |
| `followUpAfter` | string? | Sau |
| `followUpRemaining` | string? | Còn lại |
| `products` | OrderLineItem[] | SP - SL - Đơn giá |
| `subtotal` | decimal | Thành tiền |
| `discount` | decimal | CK |
| `vat` | decimal | VAT |
| `shippingFeeCollected` | decimal | Phí VC |
| `total` | decimal | Tổng |
| `deposit` | decimal | Đặt cọc |
| `deliveryStatus` | string | Trạng thái GH |
| `desiredDeliveryAt` | date? | Ngày muốn nhận |

```ts
interface OrderLineItem {
  productId: string;
  productName: string;
  sku?: string;
  quantity: number;
  unitPrice: number;
}
```

---

## 6. Hồ sơ khách hàng (`CustomerProfile`)

Gần giống **SaleOperationRow**, thêm:

| Field | Kiểu |
|-------|------|
| `isDuplicatePhone` | boolean | Trùng số |
| `isReturningCustomer` | boolean | Khách cũ |
| `allocationType` | string | Phân bổ |
| `marketingId` | string | |
| `marketingName` | string | |
| `marketingTeam` | string | |
| `closedAt` | datetime? | Ngày chốt đơn |
| `warehouseName` | string | Kho |
| `trackingCode` | string | Mã giao vận |
| `internalReconNote` | string | ĐSNB |

Filter thêm: `FilterDuplicatePhone`, `FilterOldCustomer`, `FilterAllocation`, `FilterInternalRecon`.

---

## 7. Kế toán tác nghiệp (`AccountingOperations`)

**Màn:** Đơn theo trạng thái kế toán / giao vận.

### 7.1 Filter (bổ sung)

| Biến | Mô tả |
|------|--------|
| `FilterHideZeroStatus` | Ẩn trạng thái không số |
| `FilterPrintStatus` | In đơn |
| `FilterCareOrder` | Care đơn |
| `FilterCarePersonId` | Người care |
| `FilterInternalRecon` | Đối soát nội bộ |
| `FilterDepositStatus` | Đặt cọc |
| `FilterOrderTracking` | Theo dõi đơn |
| `FilterExportEInvoice` | Xuất HĐĐT |
| `FilterQuantity` | Lọc số lượng |

### 7.2 AccountingStatusTab

```ts
type AccountingShippingStatus =
  | 'waiting_waybill' | 'delivering' | 'cannot_deliver'
  | 'delivered' | 'returning' | 'returned'
  | 'deliver_now' | 'delivery_complete' | 'cancel_waybill'
  | 'cancel_closing' | 'picking_up' | 'cannot_pickup'
  | 'redelivery_request' | 'paid' | 'refund'
  | 'posted' | 'all';
// { status, label, count }
```

### 7.3 AccountingOrderRow

| Field | Kiểu |
|-------|------|
| `orderId` | string |
| `orderCode` | string | PS00… |
| `dataArrivedAt` | datetime |
| `closedAt` | datetime |
| `saleId`, `saleName`, `saleGroup` | |
| `carePersonId`, `carePersonName` | |
| `careUpdatedAt` | datetime |
| `accountingNotes` | text |
| `warehouseId`, `warehouseName` | |
| `carrierName` | PTGH |
| `trackingNumber` | |
| `shippingStatus` | string |
| `statusUpdatedAt` | datetime |
| `internalCodeBsnb` | string | BSNB |
| `items` | OrderLineItem[] |
| `subtotal` | decimal |
| `discount` | decimal |
| `productVat` | decimal |
| `shippingFeeCollected` | decimal |
| `grandTotal` | decimal |
| `deposit` | decimal |
| `amountToCollect` | decimal | Tiền thu KH |
| `carrierServiceFee` | decimal |
| `shippingSupportFee` | decimal |
| `customerName`, `customerPhone` | |
| `shippingAddress` | text |
| `desiredDeliveryAt` | date |
| `eInvoiceId` | string? |

---

## 8. Thủ kho tác nghiệp (`WarehouseOperations`)

Schema bảng gần **AccountingOrderRow**, nhấn mạnh:

| Field | Kiểu |
|-------|------|
| `shippingNotes` | text |
| `eInvoiceStatus` | string |
| `codAmount` | decimal |
| `internalReconStatus` | string | DSNB |

Filter giống kế toán (in đơn, kho, PTGH, team sale/mkt).

---

## 9. Danh sách đơn hàng lỗi (`FailedOrders`)

| Field | Filter / Row |
|-------|----------------|
| `FilterPlatform` | TikTok, Facebook… |
| `FilterWarehouseId` | Kho |
| `FilterShopId` | Shop |
| `FilterPartnerOrderId` | Mã đơn đối tác (search) |
| `FilterDateFrom`, `FilterDateTo` | |
| `row.stt` | int |
| `row.partnerOrderId` | string |
| `row.errorDescription` | string |
| `row.updatedAt` | datetime |
| Action: `syncMissingOrders()` | Lấy đơn chưa có HT |

---

## 10. Danh sách sản phẩm kho (`WarehouseInventory`)

### 10.1 Filter

| Biến | Mô tả |
|------|--------|
| `FilterWarehouseId` | Chọn kho |
| `FilterProductId` | Sản phẩm |
| `FilterLocationCode` | Mã vị trí |
| `FilterBatchCode` | Mã lô |
| `FilterBusinessStatus` | Trạng thái KD |
| `FilterProductNameSearch` | Tên SP (header) |

### 10.2 InventoryRow

| Field | Kiểu |
|-------|------|
| `id` | string |
| `warehouseId`, `warehouseName` | |
| `productId` | |
| `productName` | |
| `sku` | trong ngoặc SP… |
| `uom` | string | Đơn vị tính |
| `batchCode` | string? |
| `expiryDate` | date? |
| `locationCode` | string? |
| `stockQuantity` | int | Tồn kho |
| `pendingSalesQuantity` | int | Chờ xuất BH |
| `isDiscontinued` | boolean | Ngừng KD |

---

## 11. Enum tham chiếu nhanh

### DeliveryStatus (giao hàng)

`waiting_waybill`, `delivering`, `delivered`, `paid`, `returned`, `returning`, `cancel_waybill`, `cannot_deliver`, `redelivery`, …

### DiscountMode

`before_discount` | `after_discount` (Trước / Sau chiết khấu)

### ReconciliationStatus

`pending`, `reconciled`, `mismatch` (Đối soát)

### DateType

`sale_received_data`, `closing_date`, `data_arrival`, `care_update`, …

---

## 12. Map màn hình → route ERM SaleOps (dự kiến)

| UI gốc | Route dự kiến | Role |
|--------|----------------|------|
| Báo cáo CEO | `/admin/reports/ceo` | admin |
| Dashboard marketing | `/admin/marketing/dashboard` | admin |
| BC doanh số MKT | `/admin/marketing/revenue` | admin |
| BC doanh số Sale | `/admin/sales/revenue` | admin |
| Sale tác nghiệp | `/sales/workspace` | sales |
| Hồ sơ KH | `/sales/customers` | sales |
| Kế toán tác nghiệp | `/admin/accounting` | admin |
| Thủ kho tác nghiệp | `/admin/warehouse/operations` | admin |
| Đơn hàng lỗi | `/admin/orders/failed` | admin |
| Sản phẩm kho | `/admin/warehouse/inventory` | admin |

---

## 13. Realtime (WebSocket)

| Event | Channel | Payload |
|-------|---------|---------|
| `stats.updated` | `dashboard.admin` / `dashboard.sales` | `CeoReport` slice / sale counters |
| `lead.created` | `user.{id}` | `SaleOperationRow` (tương lai) |
| `order.status_changed` | `dashboard.admin` | `{ orderId, shippingStatus, counts }` |

Khi implement từng màn, dùng file này làm checklist field — tránh thiếu cột so với UI gốc.
