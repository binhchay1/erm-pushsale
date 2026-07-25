# v127 — Interaction, CSS hover and dialog contract fix

## Scope
- Manual data distribution `/admin/leads` now submits via an explicit `router.post()` action from the button click, with loading/success/error toast feedback. This avoids the old symptom where selecting quantities and pressing "Phân bổ data" appeared to do nothing.
- Landing approval `/admin/marketing/landing-approvals` now submits the approval dialog via an explicit `router.post()` payload instead of relying on `useForm.transform().post()` chaining. It validates selected products client-side, shows toast errors, and keeps the dialog state stable.
- Approval filter header is aligned in one compact row with the page title and consistent top/bottom spacing.
- Product taxonomy popups now have a visible close action and are forced to a full-window Pushsale popup layout instead of collapsing to a narrow left column.
- Sidebar second-level menu hover is fixed at both React inline-style level and the final canonical CSS layer. Leaf menu items no longer keep the white background / faint blue top border inherited from legacy AdminLTE focus rules.

## Contract
- New React dialogs must use `PushsaleDialog`.
- New select controls should use `PushsaleSelect` / `PushsaleMultiSelect`.
- Page-level Pushsale CSS must be scoped by page/root class. Cross-page fixes belong in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Do not create new root README version files. Keep version notes under `docs/context-history/`.
