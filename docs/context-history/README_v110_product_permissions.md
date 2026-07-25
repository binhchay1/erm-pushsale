# v110 - Product permission dialog uses real team/user data

## Scope
- Form "Thêm mới / chỉnh sửa sản phẩm" now loads real Marketing teams, Marketing users, Sale teams, Sale users from the current company.
- CSKH currently uses the same Sale team/user pool because the project has not introduced a separate `cskh` role yet. The data is still stored independently in `care_team_ids` and `care_user_ids`, so switching to a real CSKH role later only requires changing the option source.

## Backend contract
Products now have these nullable JSON columns:
- `marketing_team_ids`, `marketing_user_ids`
- `sale_team_ids`, `sale_user_ids`
- `care_team_ids`, `care_user_ids`

Meaning:
- `available_* = true` and `*_user_ids = []`: all active users in that group have access.
- `available_* = true` and `*_user_ids` has values: only selected users have access.
- `available_* = false`: no user in that group may use that product for new operational flows.

## Business flow guard
`DataDistributionService` now checks product `sale_user_ids` before allocating leads. If the selected sales are not allowed for the product, allocation is rejected with a validation error.

## Commands after deploy
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan db:seed
php artisan erm:test-all --seed --audit --route-smoke --smoke-limit=80 --base-url=https://salesloop.vn --json
```
