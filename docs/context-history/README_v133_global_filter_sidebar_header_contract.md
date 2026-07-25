# v133 — Global filter/sidebar/header contract

## Scope

This version consolidates recurring UI fixes into the final canonical CSS layer instead of adding page-specific override files.

## Changes

- Restored visible/clickable native date inputs for date range filters.
- Standardized select/filter arrows across native `<select>` and React `PushsaleSelect`.
- Re-aligned rebuilt AdminLTE page titles close to the left edge.
- Re-aligned combo/team-style page headers through the canonical contract.
- Strengthened sidebar second-level hover rules for menu items with and without third-level submenu.
- Removed the `active` early-return in `AppSidebar.forceSecondLevelHover()` so runtime hover styling can still neutralize legacy white hover layers.

## Contract

- New global UI fixes go in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Do not add another one-off sidebar hover or generic filter CSS file.
- Page-specific CSS may still exist, but must not override the canonical sidebar/select/date/header rules globally.
