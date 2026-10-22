# Integrations

Landing webhooks, Pancake chat, queues. Ops: [OPERATIONS.md](./OPERATIONS.md). Packet rules cũng nằm trong OPERATIONS.

## Landing form / webhook

- Driver: `LandingFormDriver` + `LandingConnectionPayloadMapper`.
- Parse `form_item*` làm dòng SP/upsell text; **không** lấy URL làm `product_name` / giá ảo.
- Cùng `utm_source` + SĐT trong cửa sổ hold → một đơn; cùng URL chính trong hold → duplicate; URL khác → upsale lines.
- Empty product allowed; hydrate phone từ session / `landing_phone`.
- Ingest: `LeadIngestionService` + `LeadOrderFactory` (no default fake campaign product).
- Tests: `LandingConnectionFlowTest`, `LandingProductLabelTest`.

Config hold: `config/saleops.php` (`hold_seconds` / `max_hold_seconds`).

## Pancake (Hệ sinh thái Pancake POS & Pages)

Hệ thống hỗ trợ đồng bộ tự động 2 chiều toàn diện giữa **ERM Pushsale** và **Pancake** (gồm Pancake POS `pos.pages.fm` và Pancake Pages `pages.fm`):

### 1. Luồng nhận dữ liệu vào Pushsale (Inbound Flow)
- **Webhook Đơn hàng (Khuyên dùng)**:
  - Endpoint: `POST /api/v1/webhooks/pancake/{webhook_token}`
  - Nhận sự kiện `order.create` hoặc `order.update` từ Pancake POS.
  - Driver: `PancakeLeadDriver` chuẩn hóa tự động thông tin khách, địa chỉ, sản phẩm/biến thể, chiết khấu, tiền cọc, ghi chú.
  - Phân bổ Sale: `PancakeAssignmentResolver` gán theo mapping nhân viên (`pancake_user_mappings`), chủ hội thoại chat cũ, hoặc round-robin theo luật LeadIngestion.
  - Vết đồng bộ: Lưu lại trong `pancake_sync_records` (`external_id = pancake_order_id`, `external_type = 'order'`).
- **Chrome Extension (Bán tự động / Test nhanh)**:
  - Thư mục: `extensions/pancake-saleops`
  - Nhân viên trực chat trên Pancake mở khung chat/đơn, bấm "Đẩy về Pushsale".
  - Endpoint: `POST /api/v1/pancake/extension/orders` (yêu cầu Sanctum Bearer token).
  - Tự động gán sale theo tài khoản đang đăng nhập Extension.
- **Manual Sync (Đồng bộ tức thời trên UI)**:
  - Endpoint: `POST /admin/integrations/pancake/sync-now`
  - Thao tác: Bấm nút "Đồng bộ đơn ngay" tại menu **1.4 / Quản trị > Kết nối nền tảng** (`/admin/integrations`).
  - Kéo ngay tối đa 20 đơn mới nhất từ Pancake POS Open API (`GET /shops/{shop_id}/orders`), không cần chờ webhook.
- **Scheduler Fallback (Chống rớt đơn)**:
  - Command: `php artisan pancake:sync-orders --limit=50`
  - Chạy ngầm định kỳ mỗi 10 phút (`routes/console.php`), quét và gom bù bất kỳ đơn hàng nào bị gián đoạn mạng.

### 2. Luồng đồng bộ ngược sang Pancake POS (Outbound Flow)
- **Mã vận đơn & Trạng thái giao hàng (Kho 5.1 / Vận chuyển)**:
  - Kích hoạt: Tự động qua `OrderObserver` khi đơn hàng có cập nhật `tracking_number` hoặc `delivery_status`.
  - Job xử lý: `SyncOrderToPancakeJob` (hàng đợi `pancake-orders`).
  - Service: `PancakeOutboundSyncService::syncOrderShipping($order)`.
  - Endpoint Pancake gọi: `PUT https://pos.pages.fm/api/v1/shops/{shop_id}/orders/{pancake_order_id}?api_key={api_key}`.
  - Payload đẩy sang: `tracking_number`, `bill_code`, `partner_order_id`, `shipping_partner`, và mã trạng thái tương ứng (`status`: 2 Đang giao, 3 Hoàn thành/Đã giao, 4 Đang hoàn/Đã hoàn, 5 Đã huỷ).
  - Cập nhật thời điểm `last_synced_at` và log payload vào metadata của `pancake_sync_records`.
- **Biến động tồn kho (Kho 5.3)**:
  - Kích hoạt: Tự động khi hoàn thành phiếu nhập/xuất kho (5.3) hoặc xuất kho theo đơn (`WarehouseInventoryMovement::created`).
  - Job xử lý: `SyncInventoryToPancakeJob`.
  - Service: `PancakeOutboundSyncService::syncInventoryMovement($movement)`.
  - Endpoint Pancake gọi: `POST https://pos.pages.fm/api/v1/shops/{shop_id}/variations/{variation_id}/update_quantity`.
  - Tự động khớp SKU sản phẩm nội bộ với biến thể trên Pancake POS và đồng bộ tồn kho thực tế.

### 3. Hội thoại & Chat trực tiếp khách hàng (Pancake Pages)
- Direct customer chat không phụ thuộc Extension. Webhook đơn hàng hoặc Extension lưu `page_id` + `conversation_id` vào `pancake_sync_records` / `pancake_customer_messages`.
- Dialog đơn hàng khách đọc tin nhắn tại: `GET /customers/orders/{order}/pancake-messages`.
- Gửi tin nhắn phản hồi: `POST /customers/orders/{order}/pancake-messages` (yêu cầu quyền `customer_chat:full`).
- Runtime gọi Pancake Page API: `https://pages.fm/api/public_api/v2` với query parameter `page_access_token`.
- Webhook tin nhắn đến: `POST /api/v1/pancake/messages/{webhook_token}` (phát sự kiện Echo/Reverb `PancakeCustomerMessageCreated` realtime).
- Quản trị & kiểm tra hệ thống chat: `php artisan pancake:doctor --json`. Map tài khoản chat: `php artisan pancake:map-user`.

## Horizon / Redis

- Queues for ingest, broadcast, heavy reports, and order journey (`order-traces`, Horizon supervisor `supervisor-order-traces`). The trace worker only inserts; landing and shipping webhooks dispatch onto it and return.
- Local tests: `Queue::fake()` / sync in `tests/TestCase.php` when Redis unavailable.
- Production: Horizon workers + Reverb restarted by deploy hook.

## Carriers / shipping

- Status sync via shipping APIs + optional webhooks (`/api/v1/shipping/webhooks/{provider}`).
- Warehouse ops update TTGH; accounting syncs reconciliation fields.
- **NetShip** (`config/shipping_partners.php` → `netship`) is a **gateway/aggregator**, not a selectable carrier on the order.
  - Staff still choose Viettel Post / GHTK / … on the order.
  - If that carrier’s UI/env credentials are ready → call the **direct** driver.
  - Else, if NetShip is enabled + token set and the provider is in `routed_providers` → create/sync/cancel via **NetShip proxy** (`carrierCode` mapped e.g. `viettel_post` → `VTP`).
  - Create/estimate body is **flat JSON** with exactly the fields in the [NetShip docs](https://steplap.gitbook.io/netship/tao-don-hang) — no `myRequest`, no `ShopID`, no `carrierCode`. The token must be a **shop** "Bên thứ ba" token (the shop and shipping unit come from the token); a master-account token fails with `myRequest.ShopID required`. Live base: `https://netship.vn` (`test.netship.vn` only when `NETSHIP_USE_SANDBOX=true`).
  - Cancel: `POST /api/third-party/order/cancel/{id}` (NetShip status becomes `8`).
  - Of the fields we send, query and the [webhook](https://steplap.gitbook.io/netship/webhook) echo `customerCode` (our `order_code`). NetShip adds `id`, `linkId`, and `externalCode`. Dev confirmed `externalCode` is column `Mã đơn` of the reconciliation Excel. Create stores it as `shipments.tracking_number` and `orders.tracking_number`. The journey (landing received, NetShip response, webhook status) is appended on the `order-traces` queue into `order_traces` — the HTTP handlers only dispatch. Search is menu **10.1.6** `/admin/shipping/order-traces`. `shipping_gateway_traces` is written by that same worker for the raw gateway response. `id` / `linkId` stay on the payload for cancel and sync. The webhook body is only `{id, customerCode, status, cod, fee, reason}`.
  - Sync reads `GET /api/third-party/order`, whose `search` param only matches `externalCode` / `linkId` — not `customerCode` or the NetShip id. Store `externalCode` on create and search with it. Every identifier on the create/query payload is also kept on `response_payload.partner_refs` so a later bill/tracking field matches reconciliation without a code change.
  - Warehouse and accounting share one manual reconciliation upload. Column names live in `config/reconciliation_columns.php` (the header row is whichever row matches the most names, so a banner above the table is ignored). A code is tried as `order_code`, then shipment `tracking_number` / `partner_order_id` / `tracking_id` / `partner_refs`. `COD Gốc` / `Tiền thu hộ` is the COD check; `Giao hàng thành công` and `Hoàn hàng thành công` count as reconciled. NetShip column `Mã đơn` matches the stored `externalCode`.
  - Address IDs are GSO codes of the **pre-2025** 63-province structure. Receiver IDs come straight from `shipping_geo.*_code`; only the warehouse falls back to name lookup, where `config/vn_province_merges_2025.php` maps a merged province back to its old one.
  - NetShip callbacks carry no secret and the URL inside their token cannot be edited, so `security.webhook.provider_ip_allowlist.netship` (env `NETSHIP_WEBHOOK_IPS`) authorises them by source IP; rejects are logged as `shipping.webhook.rejected`.
  - Shipment keeps business `provider`; `response_payload.gateway = netship` + `netship_order_id`.
  - Admin menu **1.4** configures NetShip token; NetShip does **not** appear in sale/warehouse carrier dropdowns.
  - Webhook provider key: `netship` — match by `customerCode` / NetShip `id`, do not overwrite business carrier.

### Pancake customer chat wiring notes

- Direct customer chat is not the Chrome Extension itself. The extension/order webhook must persist `page_id` + `conversation_id` into `pancake_sync_records` or `pancake_customer_messages`; the customer dialog then reads `/customers/orders/{order}/pancake-messages`.
- Runtime read/send flow uses Pancake Page API with `page_access_token`; default `page_api_base_url` is `https://pages.fm/api/public_api/v2`. Existing tenants can still override it from the integration connection UI or env.
- Incoming chat webhook endpoint: `/api/v1/pancake/messages/{webhook_token}`. The tokened URL is baseline auth; optional HMAC/API-key headers are accepted when `webhook_secret` is configured.
- Required production setup per tenant/page: enable Pancake connection, store `page_id`, `page_access_token`, webhook token/secret, and map Pancake agent/user to internal sale users where possible.
- If the dialog shows no Pancake history, run `php artisan pancake:doctor --json` and inspect whether the order has a linked conversation record, page token, active user mapping, and running queues.
