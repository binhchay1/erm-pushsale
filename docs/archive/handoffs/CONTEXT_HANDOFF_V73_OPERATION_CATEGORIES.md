# V73 - Operation categories page frame and business wiring

## Scope

- Rebuild `1.8.1 Quản lý danh mục tác nghiệp` as a dedicated React page instead of the generic captured-template page.
- Introduce a reusable `PushsalePageFrame` for consistent page title/action/filter/content structure.
- Keep the restored Pushsale/AdminLTE shell from V69-V72 intact; CSS is scoped to operation category page.

## UI changes

- Page title and buttons use one shared frame:
  - title centered inside a fixed titlebar
  - actions aligned right
  - filters/summary in a separate strip
  - content tables below, no overlapping header
- Upper content mirrors the Pushsale legacy screen:
  - left: `Danh sách tác nghiệp`
  - right: `Danh sách kết quả tác nghiệp`
- Bottom content renders `Danh sách tác nghiệp sau bao lâu` for workflow transition rules.

## Business wiring

- `operation_categories` remains editable via `/admin/sales/operation-categories/records`.
- Operation categories feed `SaleOperationConfigurationService`, which maps category names/start flags into stable `OperationStage` values.
- `duration_minutes` is used by `OrderOperationPresenter` for sale-operation timing display.
- `operation_workflows` is exposed on page 1.8.1 and editable via `/admin/sales/operation-workflows/records`.
- `SaleOperationStatusService` uses `SaleOperationConfigurationService::nextStage()` and `workflowDelayMinutes()` when a sale updates an order result.
- Operation results are shown from `OperationResult::selectableOptions()` rather than fake HTML rows. They remain stable business enum values; users configure behavior through workflow transitions, not by changing enum identity.

## Safety

- Deleting a start operation category is blocked.
- Deleting an operation category referenced by an operation workflow is blocked.
- To remove an operation without breaking business history, disable `Đang áp dụng` instead of deleting.
