# v134 — combo dialog fit, operation category backend, team title alignment

## Scope

- Fix combo create/edit dialog table overflow inside the modal frame.
- Convert menu 1.8.1 operation results from read-only fake inputs into persisted business settings.
- Add backend storage for operation result labels and close-order flags.
- Make `/admin/teams` and PageFrame titles start near the left page gutter instead of floating deep in the header.

## Business contract

- `operation_categories` still controls operation stages such as gọi lần 1, kho số, duration.
- `operation_workflows` still controls automatic stage transitions after a result.
- New `operation_result_settings` stores result labels and whether a result triggers the real order-closing flow.
- If the table is missing, backend falls back to the hard-coded enum: only `closed_success` closes the order.

## CSS contract

- Final global corrections remain in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Do not create new ad-hoc page CSS for combo dialog fit, PageFrame title alignment, or sidebar hover unless the canonical contract cannot express the rule.
