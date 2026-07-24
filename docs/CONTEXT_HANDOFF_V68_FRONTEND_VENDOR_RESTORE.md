# V68 - Frontend vendor/CSS restore from pre-cleanup source

## Why

After the cleanup around commit `6b722cf9760729b0e308dd80b0defce047c1d4a9`, the source no longer carried the legacy AdminLTE/FontAwesome vendor assets that the Pushsale-like UI depended on. The React menu still emitted `fa fa-*` classes, but the FontAwesome 4 font files were gone, so icons rendered as wrong private glyphs/random symbols. Menu spacing then drifted because Bootstrap/AdminLTE base CSS was also no longer guaranteed locally.

## Restored from uploaded pre-cleanup source

- `public/vendor/adminlte2/bootstrap/css/bootstrap.min.css`
- `public/vendor/adminlte2/bootstrap/fonts/*`
- `public/vendor/adminlte2/dist/css/AdminLTE.min.css`
- `public/vendor/adminlte2/dist/css/skins/skin-blue-light.min.css`
- `public/vendor/adminlte2/dist/img/*`
- `public/vendor/adminlte2/plugins/datepicker/datepicker3.css`
- `public/vendor/adminlte2/plugins/select2/select2.min.css`
- `public/vendor/font-awesome/css/font-awesome.min.css`
- `public/vendor/font-awesome/fonts/*`

## Integration approach

This does **not** roll the app back to the old codebase. New React routes/business logic stay in place. V68 only restores the static frontend contract the current shell expects.

`resources/js/lib/uiShellStyles.js` now loads local vendor CSS first, with CDN only as a last-resort fallback. `resources/css/pushsale-parity-final.css` is imported last and pins the FontAwesome `@font-face`, sidebar contract, menu row/icon sizing, action icons, shared controls, and dialog positioning.

## Deploy note

The project is PNPM-only. `package-lock.json` must not be tracked. After applying this patch:

```bash
git rm package-lock.json 2>/dev/null || true
git add public/vendor resources/js/lib/uiShellStyles.js resources/css/pushsale-parity-final.css docs/CONTEXT_HANDOFF_V68_FRONTEND_VENDOR_RESTORE.md
git commit -m "Restore legacy frontend vendor assets and menu icon CSS"
git push ssd main
```

Then hard refresh the browser (`Ctrl+F5`) because icon/font CSS is heavily cached.
