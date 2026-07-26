# V54 - Customer Profile link finishing

Scope: finish the Customer Profile page before moving to the next page.

Rules implemented:
- Landing/source names in Customer Profile open the real landing URL in a new tab. URL comes from `landing_connection_sources.source_url`, fallback to connection source/success URL when available.
- The same source-link behavior is exposed through `OrderOperationPresenter` so other order tables can reuse it instead of hardcoding `href="#"`.
- Customer Profile product/money split rows use non-table stack elements with dotted row separators, not nested `td` borders.
- Tracking number in Customer Profile links to the role-scoped warehouse operations screen:
  - Admin: `/admin/warehouse/operations`
  - Warehouse: `/warehouse/workspace`
- Tracking links use `search=<tracking_number>&no_closing_date_limit=1`; `Order::scopeApplyReportFilter()` now includes order `tracking_number` and shipment `tracking_number` in search.

Do not reintroduce nested table borders inside Customer Profile product/money cells. Use `.ps-split-stack` / `.ps-split-row` for multi-line cell content.
