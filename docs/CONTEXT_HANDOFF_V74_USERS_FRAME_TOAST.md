# V74 - Users page dialogs, shared header frame, action cells, toast bridge

## Scope
- Rebuild `Danh sách nhân viên` UI instead of generic broken edit dialog.
- Add separate create/update account dialogs.
- Keep HR user actions wired to real backend tables (`users`, `user_operational_profiles`) and business role/permission rules.
- Normalize action-cell sizing and page header full-bleed border across shared Pushsale frames.
- Restore global toast visibility for all Inertia flash/error responses.

## Main changes
- `resources/js/pages/Admin/Users/Index.jsx`
  - Uses `PushsalePageFrame`.
  - Create dialog title is `THÊM TÀI KHOẢN`.
  - Update dialog title is `CẬP NHẬT TÀI KHOẢN`.
  - Removed the old `# / 0` row.
  - Edit icon opens update dialog instead of leaving the page.
  - Bulk create dialog posts to `/admin/users/bulk` and explains business effect.
- `app/Http/Controllers/Admin/UserController.php`
  - Index now sends real row fields needed by dialogs: `email_local`, `team_id`, `manager_user_id`, `work_shift_id`.
  - Adds `quickUpdate()` so dialog edits do not wipe advanced permission config.
  - Default role permissions are applied when creating from compact dialog without explicit permission matrix.
- `routes/web.php`
  - Adds `PATCH /admin/users/{user}/quick-update`.
- `resources/js/hooks/useFlashToast.js`
  - Displays success/error/warning/info flash and validation errors globally.
- `resources/js/app.jsx`
  - Sets Sonner options and class for stable Pushsale toast z-index.
- `resources/css/pushsale-users-frame-toast.css`
  - Shared full-bleed page titlebar border.
  - User page table/action/dialog CSS.
  - Global row action cell normalization.
  - Toast z-index fix.
- `resources/js/lib/uiShellStyles.js`
  - Loads V74 CSS after V70-V73.

## Business wiring
- Single account create uses existing `UserController::store()` + `UserRequest`.
- Update dialog uses `UserController::quickUpdate()` and preserves advanced permissions.
- Bulk account create uses existing `UserController::storeBulk()`.
- Row receive-data / locked toggles use `updateOperationalStatus()` and write to `user_operational_profiles`.
- Password reset uses `updatePassword()`.
- Delete uses `destroy()`.

## Test checklist
- Add one account from dialog, verify success toast and row appears.
- Update one account from edit icon, verify role/team/receive_data persisted.
- Create multiple accounts, verify duplicates handled and toast visible.
- Toggle receive data and active status, verify immediate update and toast.
- Verify action column height/width on users and generic tables.
- Verify page header line reaches both edges while title/search/filter remain padded.
