# Customer chat realtime + security hardening

This update makes both customer conversation lanes live without refreshing the page:

1. **Internal customer messages**
   - Private Reverb channel: `customer.internal.{company_id}.{hmac}`
   - Event: `.customer.internal-message.created`
   - Queue: `broadcasts-internal-chat`
   - Fallback refresh while dialog is open: 15 seconds

2. **Pancake customer chat**
   - Private Reverb channel: `customer.pancake.{company_id}.{hmac}`
   - Event: `.customer.pancake-message.created`
   - Webhook/cache queue: `pancake-chat`
   - Broadcast queue: `broadcasts-pancake-chat`
   - Fallback refresh while tab is open: 7 seconds

## New/updated environment values

```env
QUEUE_INTERNAL_CHAT_BROADCASTS=broadcasts-internal-chat
QUEUE_PANCAKE_CHAT_SYNC=pancake-chat
QUEUE_PANCAKE_CHAT_BROADCASTS=broadcasts-pancake-chat
PANCAKE_CHAT_WEBHOOK_RATE_LIMIT_PER_MINUTE=120
```

## Pancake inbound message webhook

```txt
POST /api/v1/pancake/messages/{webhook_token}
```

Security behavior:

- `{webhook_token}` must match the Pancake integration connection.
- Payload size is limited by `WEBHOOK_MAX_PAYLOAD_KB`.
- The endpoint is rate-limited by token + IP.
- If the connection has `webhook_secret` or `INTEGRATION_WEBHOOK_SECRET`, requests must include a valid HMAC SHA256 signature or API key.
- Timestamp headers are checked when present.

## Permissions

- Internal chat view: `customers:view`
- Internal chat write: `customers:full`
- Pancake chat view: `customer_chat:view`
- Pancake chat write: `customer_chat:full`

Default behavior remains:

- Admin + Sales can send Pancake chat.
- Other roles can view by default unless custom permission says `none`.
- Any role can be upgraded to send by setting `customer_chat:full`.

## Operational note

Run Reverb plus the split queue workers. The sample supervisor file has been updated:

```txt
deploy/supervisor/pushsale-worker.conf
```
