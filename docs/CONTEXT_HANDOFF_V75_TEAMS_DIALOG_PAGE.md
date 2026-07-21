# V75 - Teams dialog page and action column parity

## Scope

- Rebuild `Quản lý đội nhóm` so create/edit happen in Pushsale-style dialogs, not separate pages.
- Remove the floating add button that was clipped at the bottom-left of the screen.
- Rename table `+ Thêm` column to `Thao tác` and normalize action cells.
- Keep backend business wiring through real `teams`, `users.team_id`, `leader_user_id`, `parent_id`, and team permissions.
- Preserve current backend resource routes for direct URL compatibility, but normal user flow now stays inside the list page.

## Business wiring

- Listing reads real `teams` with `leader`, `parent`, and `users` relations.
- Create calls `POST /admin/teams`.
- Update calls `PUT /admin/teams/{team}`.
- Delete calls `DELETE /admin/teams/{team}` and keeps backend guards against deleting teams with children or members.
- Create defaults team permissions by team type so new teams are not disconnected from reporting/operation scopes.
- Update preserves existing permissions unless the form explicitly sends a permission matrix.

## Frontend files

- `resources/js/pages/Admin/Teams/Index.jsx`
- `resources/css/pushsale-v75-teams-page.css`
- `resources/js/lib/uiShellStyles.js`

## Backend files

- `app/Http/Controllers/Admin/TeamController.php`

## QA

- Open `/admin/teams` or `/ld/unit-admin/quan-ly-doi-nhom`.
- Click `Thêm đội nhóm`: dialog opens, no navigation.
- Edit any row: update dialog opens, row data is prefilled.
- Save create/update: toast appears from flash, table reloads.
- Try deleting a team with members: backend should reject with flash error.
- Verify action column has consistent width and header says `Thao tác`.
