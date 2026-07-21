# V70 — table/filter/dialog polish

## Scope

- Marketing dashboard `2.1`: remove horizontal table scroll while keeping `Tên nguồn dữ liệu` and `Sản phẩm` as the widest business columns.
- Landing connection `2.4.1`: remove horizontal table scroll, align title/header/filter row with other Pushsale/AdminLTE pages.
- Landing source dialog: keep new business fields but restyle into compact Pushsale/AdminLTE form geometry.
- Shared controls: normalize `ps-control`, `form-control`, and `ps-date-range` so filters look consistent across rebuilt pages.

## Implementation

- Added `resources/css/pushsale-v70-page-polish.css` as the last loaded page-polish layer.
- Updated `resources/js/lib/uiShellStyles.js` to load `pushsale.css` then `pushsale-v70-page-polish.css`.
- Updated `Page_2_4_1.jsx` so the Radix dialog outer body uses `pslc-dialog-shell`; the actual form keeps one scroll body and one footer.

## Notes

This patch does not rollback the restored V69 frontend shell and does not alter backend business logic. It only fixes frontend table geometry, filters, page title chrome, and dialog layout.
