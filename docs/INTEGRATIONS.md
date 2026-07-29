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
