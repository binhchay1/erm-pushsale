# v135 — Product taxonomy modal contract fix

Scope:
- `/admin/products` product taxonomy dialogs: categories, attributes, attribute values.

Fix:
- The taxonomy modal is forced to a full viewport Pushsale popup from the canonical-last CSS layer.
- The dialog body uses a two-pane grid: list table on the left and update form on the right.
- The search/filter row is constrained to the popup width and no longer collapses to a narrow left column.
- The malformed duplicate selector in `pushsale-product-taxonomy-dialog-contract.css` was corrected.

Contract:
- Future taxonomy modal layout changes should be made in `resources/css/pushsale-adminlte-canonical-contract.css` and `resources/js/pages/Admin/Products/Index.jsx` only.
