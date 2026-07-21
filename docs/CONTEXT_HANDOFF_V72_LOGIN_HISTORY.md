# V72 - Login history page fix

## Scope

- Rebuild page `1.7.1 - Lịch sử đăng nhập` as a dedicated React page instead of mixing the captured Pushsale template title/pager with live rows.
- Keep backend source as real `activity_logs` data.
- Do not change the global shell/menu restored in V69.

## Fixes

- Title and search button no longer overlap.
- Filter row is a single, consistent AdminLTE/Pushsale-style grid.
- Pagination is a compact footer with page size, record range and prev/next controls.
- Table body renders live login rows from `activity_logs`, including IP, company, account, access code, browser, performed time and status.
- Platform admin continues to query logs without tenant scope.

## Backend data source

`PushsalePageService::activityLogs('1.7.1')` queries `ActivityLog` with auth actions:

- `auth.login.success`
- `auth.login.failed`
- `auth.login.blocked`
- `auth.logout`

Login attempts are written by `App\Http\Controllers\Auth\LoginController::logLoginAttempt()` and auto-admin login is written by `AutoLoginAsAdmin::logAutoAdminLoginOncePerSession()`.

## Files

- `resources/js/pages/Pushsale/Pages/Page_1_7_1.jsx`
- `resources/css/pushsale-v72-login-history.css`
- `resources/js/lib/uiShellStyles.js`
- `app/Services/Pushsale/PushsalePageService.php`
