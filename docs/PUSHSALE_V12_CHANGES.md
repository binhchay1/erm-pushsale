# Pushsale V12 changes

- Authenticated content shell no longer adds an outer page gutter.
- Marketing dashboard now uses a source-faithful 1960px base table and 2170px advanced-UTM table with explicit `colgroup` widths.
- Ranking detail table uses explicit 1880px column geometry.
- All supported modal systems are centered against the browser viewport and constrained to `100dvh`.
- Direct dialog-child margins that caused horizontal overflow were removed.
- Warehouse operations now paginate in SQL and return `rows.data` + `rows.meta`.
- Warehouse status-tab counts are calculated in SQL from the same filtered base query.
