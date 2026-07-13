# Customer Profile V9 — Pushsale parity

## Scope

The customer profile page is a dedicated live-data screen for the following menu routes:

- `2.3` → `/admin/marketing/customers`
- `3.1` → `/admin/customer-management`
- `4.2` → `/admin/sales/customers`
- shared route → `/customers`

The supplied `template-first/html.txt` is used as the visual/interaction specification. Captured data rows are not used at runtime.

## Data rules

- One rendered row represents one normalized phone number.
- Phone normalization removes separators and converts the Vietnamese `84...` prefix to `0...`.
- The representative row is the latest matching order ID after all active filters are applied.
- Purchase history, internal messages, and same-phone operation history use the same normalized-phone identity.

## Live backend sources

- `orders`, `order_items`
- `users`, `teams`
- `marketing_sources`
- `products`, `warehouses`
- `order_operation_histories`
- `customer_internal_messages`
- Pancake customer message services
- `activity_logs` for customer-data view history

## Page actions

- Four CSV export layouts
- Immediate reassignment
- Move selected profiles back to the allocation queue
- Recall selected profiles
- Delete selected operation histories (administrator only)
- Customer internal messaging and Pancake chat
- Operation history, including all orders with the same phone
- Purchase history by phone
- Customer-data view history for the latest 30 days
- Existing landing upsale/supplement review dialogs

## Pagination

The customer list is paginated at SQL level after phone grouping. Generic Pushsale business pages now render pagination even when the result has one page. Existing merged employee, team, product, warehouse, and inventory modules already use server-side pagination.
