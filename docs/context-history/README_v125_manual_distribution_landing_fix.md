# v125 — Manual data distribution + landing pending flow fix

## Business contract

### 1. Manual data distribution `/admin/leads`
- The page is not a static UI. The submit button posts to `/admin/leads/distribute`.
- Selected product quantities are distributed round-robin across selected sales.
- `operation_policy` is applied after order creation:
  - `keep`: keep default operation stage from lead/order factory.
  - `new_customer`: force `operation_stage = new_customer`.
  - `follow_up`: force `operation_stage = call_2`.
- Product sale permissions are enforced through `products.sale_user_ids` / `available_sale`.

### 2. Landing connection `/admin/marketing/landing-connections`
- Creating a connection does **not** require product/package selection.
- The create/update form only creates pending landing connection records:
  - `landing_connections`
  - `landing_connection_sources`
  - `landing_connection_sales`
- It must not create/sync `marketing_sources` until approval.
- Approval and product/package/budget binding live in `/admin/marketing/landing-approvals`.

## Technical fixes
- Replaced `Collection::filter('is_array')` with explicit closures because Laravel passes value and key to callbacks, causing `ArgumentCountError: is_array() expects exactly 1 argument, 2 given`.
- Manual distribution frontend now uses `router.post` with explicit payload and loading state instead of relying on persistent `useForm.transform` side effects.
- Sidebar second-level leaf hover now has a React hover state + inline style fallback, not only CSS cascade, to bypass legacy AdminLTE overrides.
- Added generic route transition overlay to reduce perceived CSS/layout flicker during Inertia navigation.

## Deploy smoke
```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```
