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

## Pancake

- Assignment + customer chat realtime (Echo/Reverb).
- Keep channel auth + policy scoped to conversation owners.
- Details live in code under `app/Services` / listeners — do not fork one-off MD per tweak.

## Horizon / Redis

- Queues for ingest, broadcast, heavy reports.
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
  - Of the fields we send, query and the [webhook](https://steplap.gitbook.io/netship/webhook) echo `customerCode` (our `order_code`). NetShip adds `id`, `linkId`, and `externalCode`. Dev confirmed `externalCode` is column `Mã đơn` of the reconciliation Excel. Create stores it as `shipments.tracking_number` and `orders.tracking_number`. The full response is kept on `shipping_gateway_traces` (`external_code`, `gateway_order_id`, `lookup_code`) for later lookup. `id` / `linkId` stay on the payload for cancel and sync. The webhook body is only `{id, customerCode, status, cod, fee, reason}`.
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
