# Operations — business flows

End-to-end ops for humans and agents. Tech contract: [PROJECT_CONTRACT.md](./PROJECT_CONTRACT.md). Integrations: [INTEGRATIONS.md](./INTEGRATIONS.md).

## Pipeline

```text
Landing / ads → Lead ingest → Sale tác nghiệp (4.1)
  → Chốt đơn → Kho đăng đơn (5.1) → Vận chuyển
  → Kế toán đối soát (6.1) → Báo cáo / CEO
```

## Marketing — Landing connection (2.4.1)

1. Tạo kết nối: loại, chia số, tên nguồn, URL, kênh, upsale URL, sale ưu tiên.
2. **Không** gắn sản phẩm lúc tạo.
3. Duyệt tại `/admin/marketing/landing-approvals` → gắn gói/SP + ngân sách → sync `marketing_sources`.
4. Form landing/upsale submit public API (token theo source).

## Sale tác nghiệp (4.1)

- UI: `SaleWorkspaceTable` + shared cells `OpsTableCells`; CSS `pushsale-sale-operations-contract.css`.
- Cột: checkbox, mã đơn, nguồn, sale, khách, tin nhắn, TN cần, kết quả, TN tiếp, còn lại, SP, tiền, cọc, TTGH.
- Icon layout Pushsale: `div.text-right` / float chat+save trên TN cần.
- **Tin nhắn** = note landing (read-only). **TN cần** = ghi chú sale (`txt-mof`).
- Upsale: cờ + dòng `—` trong cột SP (bổ sung so với mẫu gốc).
- Tab trạng thái: Gọi lần / Chăm sóc / Bỏ qua / Chưa TN / Tất cả.

## Kho tác nghiệp (5.1)

- UI: `WarehouseOrderTable` (`variant=warehouse`); CSS `pushsale-warehouse-operations-contract.css`.
- Nguồn list: cùng pool đơn với filter báo cáo + tab TTGH — **không** khóa `closed_at` (luồng mới: webhook/sale có thể đã ở `deliver_now` / `waiting_waybill` trước khi chốt).
- Chốt đơn vẫn gắn `closed_at` + `waiting_waybill` và cấp mã đơn; action đăng vận đơn/in đơn vẫn theo policy shipment.
- Icon: `fa-repeat` trên mã đơn; care 3 `span-col`; TTGH bomb/refresh/history; khách calendar/clipboard/truck.
- Tab TTGH + FAB hàng loạt (đăng vận đơn, in, sync…).

## Kế toán tác nghiệp (6.1)

- Cùng `WarehouseOperationService` + bảng kho với `variant="accounting"`: `retweet` thay bomb; không clipboard tách đơn.
- Cùng rule hiển thị list với kho (không khóa `closed_at`); focus đối soát / tiền / trạng thái giao hàng.

## Báo cáo trưởng sale (4.6.x)

| Code | Path | Note |
| --- | --- | --- |
| 4.6.1 | `/admin/sales/reports/operation-conversion` | Filter 1 hàng; Excel khớp bảng |
| 4.6.2 | `.../work` | Excel |
| 4.6.3 | `.../teams` | Filter + Excel |
| 4.6.4 | `.../data` | Filter + Excel |
| 4.6.5 | `.../optimization` | Filter + Excel |

Export: `ReportExcelExporter` + `SalesLeaderReportExcelLayout` (header nhiều hàng = UI).

## Phân số / ownership

- Allocator chia contact; chống trùng SĐT theo policy hiện tại.
- Sale chỉ thấy data trong scope team/user.
- Upsell hold window: `config/saleops.php` (hold seconds).
