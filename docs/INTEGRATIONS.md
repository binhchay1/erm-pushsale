# Integrations

Landing webhooks, Pancake chat, queues. Ops flows: [OPERATIONS.md](./OPERATIONS.md).

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

- Status sync via shipping APIs + optional webhooks.
- Warehouse ops update TTGH; accounting syncs reconciliation fields.

### Pancake customer chat wiring notes

- Direct customer chat is not the Chrome Extension itself. The extension/order webhook must persist `page_id` + `conversation_id` into `pancake_sync_records` or `pancake_customer_messages`; the customer dialog then reads `/customers/orders/{order}/pancake-messages`.
- Runtime read/send flow uses Pancake Page API with `page_access_token`; default `page_api_base_url` is `https://pages.fm/api/public_api/v2`. Existing tenants can still override it from the integration connection UI or env.
- Incoming chat webhook endpoint: `/api/v1/pancake/messages/{webhook_token}`. The tokened URL is baseline auth; optional HMAC/API-key headers are accepted when `webhook_secret` is configured.
- Required production setup per tenant/page: enable Pancake connection, store `page_id`, `page_access_token`, webhook token/secret, and map Pancake agent/user to internal sale users where possible.
- If the dialog shows no Pancake history, run `php artisan pancake:doctor --json` and inspect whether the order has a linked conversation record, page token, active user mapping, and running queues.
