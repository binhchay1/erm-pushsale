# V46 - Customer profile UI + Customer 360 management

## Scope

This patch keeps the project architecture rule used from V44 onward:

- Pushsale source URLs are only visual/business references.
- ERM routes/controllers/services stay business-oriented.
- UI/UX visible to users follows Pushsale/AdminLTE 2 patterns.
- Shared layout/table/action rules live in `resources/css/pushsale.css`; do not create one-off CSS files for each page.

## Hồ sơ khách hàng

- Removed the duplicated search button inside the expanded filter area. The top-right search remains the canonical search action.
- Product and money multi-line cells no longer render nested bordered table cells. They use shared stack elements:
  - `.ps-split-stack`
  - `.ps-split-row`
  - `.ps-money-stack`
- Global CSS also neutralizes legacy nested table borders for existing pages that still render `tb-in-sp`/money nested structures.
- Floating action buttons are controlled by one shared `action-container` pattern and stay fixed to the viewport bottom-left.
- Bulk actions now use the correct base endpoint for `/admin/...` customer pages and have admin aliases to avoid broken action URLs.
- Bulk action controller catches runtime exceptions and returns a 422 JSON message instead of leaking 500 to the UI.

## 3.1 Quản lý khách hàng / Khách hàng 360

- Added `Customer360ManagementController`.
- Added React page `resources/js/pages/Customers/Management.jsx`.
- The page provides the Pushsale-like customer management UI:
  - title/filter row,
  - advanced filters,
  - campaign actions,
  - customer segment management,
  - selectable customer table,
  - export.
- Backend data is built from real orders grouped by normalized phone/customer, not static template rows.
- Campaign creation/attachment uses `CustomerCareCampaign`.
- Segment configuration is persisted in `AppSetting` under `customer360.segments`.

## Routes

Business route:

- `/admin/customer-management`

Compatibility/reference alias inside the admin group:

- `/admin/ld/customers/list-customers`

Legacy page redirect:

- `/admin/pages/3-1-quan-ly-khach-hang` -> `/admin/customer-management`

## Important note

Menu 2.3 remains a role/customer-profile workflow. Menu 3.1 is the customer management / customer 360 page. They must not be collapsed into the same screen even though both relate to customer data.
