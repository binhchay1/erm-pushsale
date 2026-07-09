# Pancake customer chat in Customer Profile

The customer message dialog on `/customers` has two isolated conversation lanes:

- **Nội bộ**: internal SaleOps discussion about the selected customer.
- **Chat Pancake**: direct customer conversation through Pancake Page API.

Both lanes now update without a page refresh.

## Realtime model

### Internal messages

- `POST /customers/orders/{order}/messages` stores the message immediately.
- The message is broadcast on a private channel named `customer.internal.{company_id}.{hmac}`.
- The broadcast event is `.customer.internal-message.created`.
- Broadcast jobs use the dedicated queue `broadcasts-internal-chat`.
- Notification fan-out still uses the `messages` → `notifications` flow, so notification latency cannot block the chat request.
- The frontend also refreshes the internal thread every 15 seconds while the dialog is open, so the user still sees updates if Reverb is down.

### Pancake customer chat

- `GET /customers/orders/{order}/pancake-messages` fetches Pancake Page API and refreshes the local cache.
- `POST /customers/orders/{order}/pancake-messages` sends to Pancake, stores the outbound message, then broadcasts it.
- The Pancake tab polls every 7 seconds while open to catch replies if a Pancake webhook is delayed or disabled.
- Optional webhook endpoint for near-realtime inbound replies:

```txt
POST /api/v1/pancake/messages/{webhook_token}
```

The webhook queues `ProcessPancakeMessageWebhookJob` on `pancake-chat`. New cached messages are then broadcast on `broadcasts-pancake-chat` using `.customer.pancake-message.created`.

## Required Pancake config

Set these in `.env` or in `Admin → Kết nối nền tảng → Pancake POS / Extension`:

```env
PANCAKE_PAGE_API_BASE_URL=https://pages.fm/api/public_api/v1
PANCAKE_PAGE_ID=
PANCAKE_PAGE_ACCESS_TOKEN=
```

The POS order sync still uses:

```env
PANCAKE_API_BASE_URL=https://pos.pages.fm/api/v1
PANCAKE_API_KEY=
PANCAKE_SHOP_ID=
```

`conversation_id` is resolved from `pancake_sync_records.metadata.conversation_id` created during Pancake order/lead import. `page_id` is resolved from sync metadata/payload, then falls back to the configured Pancake Page ID.

## Permissions

Custom permission areas:

```txt
customers       Internal customer profile + internal notes/messages
customer_chat   Direct Pancake customer chat
```

Default effective permissions:

| Role | View internal | Send internal | View Pancake chat | Send Pancake chat |
|---|---:|---:|---:|---:|
| Admin | yes | yes | yes | yes |
| Sales | yes | yes | yes | yes |
| Warehouse | yes | yes | yes | no |
| Marketing | yes | no | yes | no |
| Accounting | yes | no | yes | no |
| Allocator | yes | no | yes | no |

Grant `customer_chat:full` to any non-admin/non-sales user when they need to send messages to customers via Pancake.

## Security

- Web routes are protected by the existing `auth`, `tenant`, and `permissions` middleware.
- Private broadcast channel names use an HMAC token, never raw phone numbers or raw Pancake conversation ids.
- Broadcast channel authorization checks company id and the required permission area:
  - internal: `customers:view`
  - Pancake: `customer_chat:view`
- The Pancake webhook endpoint requires the tenant `webhook_token` in the URL.
- If `webhook_secret` is configured on the Pancake connection or `INTEGRATION_WEBHOOK_SECRET` is set, webhook requests must include one of:
  - `X-SaleOps-Signature`
  - `X-Pancake-Signature`
  - `X-Hub-Signature-256`
  - `X-Webhook-Signature`
  - or a bearer/API key matching the secret
- Optional replay protection uses `X-SaleOps-Timestamp`, `X-Pancake-Timestamp`, or `X-Webhook-Timestamp` with `INTEGRATION_WEBHOOK_TOLERANCE`.
- Payload size is capped by `WEBHOOK_MAX_PAYLOAD_KB`.
- Rate limit is controlled by `PANCAKE_CHAT_WEBHOOK_RATE_LIMIT_PER_MINUTE`.

## Queue workers

Recommended production workers:

```bash
php artisan queue:work database --queue=messages,broadcasts-internal-chat,pancake-chat,broadcasts-pancake-chat --sleep=1 --tries=3
php artisan queue:work database --queue=notifications --sleep=1 --tries=3
php artisan queue:work database --queue=default,reports,exports,translations --sleep=3 --tries=3
php artisan reverb:start --host=0.0.0.0 --port=8080
```

The supervisor sample in `deploy/supervisor/pushsale-worker.conf` already follows this split.
