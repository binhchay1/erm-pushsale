# V48 — Error shell + role gate cleanup

## Scope
- Normalize HTTP/Inertia error pages so 401/403/404/419/429/500/503 occupy the full viewport and render centered regardless of the surrounding shell, browser zoom, or app/body width.
- Allow company admin/super admin users to open role-prefixed operational URLs for QA/supervision, for example `/sales/workspace?order_id=...`, without being blocked by the coarse role middleware.

## Notes
- `EnsureUserHasRole` now treats admin as a supervisor role that can enter role-specific areas. Fine-grained authorization remains handled by `EnforcePermissions` and `User::allows()`, where admin already has full permissions.
- The route `/admin/sales/workspace` remains the canonical admin path, but `/sales/workspace` no longer produces a false 403 for admin users.
- `ErrorShell` now uses a fixed full-viewport root so it cannot inherit a constrained layout width from an old shell or cached body class.
