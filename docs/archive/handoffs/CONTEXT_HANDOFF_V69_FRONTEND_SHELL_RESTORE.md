# V69 - Restore known-good Pushsale frontend shell

## Why

V68 verified the local vendor assets were present and built, but the live menu still looked wrong. That means the issue was not missing FontAwesome/AdminLTE files anymore; it was the later CSS/React shell overrides layered after the pre-deletion frontend.

## What changed

V69 restores the known-good pre-deletion frontend shell files from the provided source snapshot:

- `resources/css/*.css` restored to the pre-deletion snapshot, while leaving unused parity CSS files in place.
- `resources/js/lib/uiShellStyles.js` restored to the simpler vendor + `pushsale.css` loader.
- `resources/js/components/layout/AppSidebar.jsx` restored to the pre-deletion menu renderer.
- `config/pushsale_navigation.php` restored to the pre-deletion menu icon mapping.
- `vite.config.js` restored so only `app.css`, `public.css`, `pushsale.css`, and `app.jsx` are build inputs.

## What stays from newer work

- PNPM-only package setup remains.
- Local vendor assets under `public/vendor/adminlte2` and `public/vendor/font-awesome` remain.
- Laravel/backend/business code remains unchanged.
- V67/V68 parity CSS files can remain in the repo, but V69 no longer imports or builds them.

## Deploy validation

After deploy, verify:

```bash
curl -I https://salesloop.vn/vendor/font-awesome/css/font-awesome.min.css
curl -I https://salesloop.vn/vendor/font-awesome/fonts/fontawesome-webfont.woff2
curl -I https://salesloop.vn/vendor/adminlte2/dist/css/AdminLTE.min.css
```

Then hard refresh browser cache.
