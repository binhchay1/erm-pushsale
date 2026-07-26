# Context handoff — ERM Pushsale V16

## Nền source

V16 được phát triển trực tiếp trên full source V15. Chuỗi kế thừa:

```text
V12 → V13 Landing Connections → V14 Financial Dashboard → V15 Template Six Reports → V16 Warehouse & Shipping
```

Đây là snapshot full source, không phải patch. Các migration, tài liệu, frontend/backend và production build của version trước vẫn nằm trong source.

## Nội dung V16

- Template-seven: màn tác nghiệp kho và toàn bộ modal theo bản ghi.
- Trang cấu hình giao vận mới thay cấu hình cũ.
- Cấu hình hãng vận chuyển theo tenant, mặc định provider/service.
- Adapter direct + generic multi-carrier.
- Webhook có auth, tenant resolution, idempotency, timeline, status mapping và financial mapping.
- Xuất kho idempotent khi tạo vận đơn thành công.
- Nhập hoàn theo biên bản từng sản phẩm, phân loại sellable/damaged/missing/inspection.
- COD/fee/return fee/other fee/compensation đồng bộ vào shipment, order, settlement và báo cáo.
- Bộ lọc và bảng kho có đầy đủ main product + upsale.

## File chính

Backend:

- `app/Services/Operations/WarehouseOperationService.php`
- `app/Services/Warehouse/WarehouseOrderActionService.php`
- `app/Services/Shipping/ShippingWebhookService.php`
- `app/Services/Shipping/CreateShipmentService.php`
- `app/Services/Inventory/InventoryReturnService.php`
- `app/Services/Shipping/Carriers/Generic/GenericCarrier.php`
- `config/shipping_partners.php`
- `database/migrations/2026_07_14_150000_expand_shipping_warehouse_financial_flow.php`

Frontend:

- `resources/js/pages/Admin/Warehouse/Operations.jsx`
- `resources/js/components/operations/WarehouseOrderTable.jsx`
- `resources/js/components/operations/WarehouseActionDialogs.jsx`
- `resources/js/components/operations/WarehouseFilterPanel.jsx`
- `resources/js/pages/Admin/ShippingPartners/Index.jsx`
- `resources/js/components/shipping/ShippingPartnerCard.jsx`
- `resources/css/pushsale-warehouse.css`
- `resources/css/pushsale-shipping-config.css`

Tests:

- `tests/Feature/Shipping/WarehouseShippingFlowV16Test.php`

## Lưu ý production

- Không khẳng định một provider generic đã chạy live khi chưa có credential/contract/mẫu response thật.
- SPX/J&T và một số hãng cấp API theo account/đối tác; endpoint phải đối chiếu tài liệu được cấp cho khách hàng.
- Mỗi webhook production phải có secret hoặc HMAC.
- Kiểm tra cấu hình sender profile/kho lấy hàng trước khi bật auto waybill.
- Đối soát COD vẫn phải import/sync batch khi hãng không gửi dữ liệu settlement đầy đủ qua webhook.
