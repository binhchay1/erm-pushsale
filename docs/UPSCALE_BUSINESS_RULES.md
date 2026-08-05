# Landing / Upsale business rules

## How the system identifies a packet

The canonical source of truth is `lead_ingestions.packet_type` plus `counts_as_lead`.

A landing packet becomes a primary packet when it comes from a `landing_connection_sources.source_type = main` source or from the normal campaign `/receive` endpoint. It is stored as:

- `packet_type = lead`
- `counts_as_lead = true`

An upsale packet becomes supplemental when one of these conditions is true:

- the submitted source is `landing_connection_sources.source_type = upsell`;
- the request is sent to the campaign `/upsell` endpoint;
- the payload explicitly carries `is_upsell = 1/true`, `item_type = upsell`, or an upsell/addon field name.

Supplemental packets are stored as:

- `packet_type = upsell`, `late_upsell`, or `orphan_upsell`;
- `counts_as_lead = false`.

## When an upsale is counted in Marketing

Marketing dashboard/report contact uses a packet contract, not the global customer contact contract:

`Marketing contact = primary packet + valid upsale packet`

A valid upsale packet must be:

- `status = processed`;
- `counts_as_lead = false`;
- not requiring review;
- linked to an effective order through `order_id`, `related_order_id`, or the parent primary ingestion's order.

The following packets are audit-only and must not increase Marketing contact totals:

- duplicate;
- failed;
- pending/gathering upsale;
- needs-review upsale;
- orphan upsale that has not been merged or converted into a supplemental order;
- follow-up packets.

## Production audit

Run:

```bash
php artisan landing:upsale-audit --from=2026-08-01 --to=2026-08-06 --json
```

The command should report `partition_ok = true`, meaning:

`Tất cả = Khách mới + Khách cũ`
