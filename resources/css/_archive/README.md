# CSS archive (orphans)

These stylesheets are **not loaded** by:

- `resources/js/lib/pushsaleStyleRegistry.js`
- Vite entrypoints (`app.css`, `public.css`, `pushsale.css`)
- Direct page imports (e.g. `Marketing/Docs.jsx` → `pushsale-docs-page.css`)

They were moved here during project cleanup to stop cascade confusion.
Do **not** re-import them into the live registry without an explicit need.

Keep in `resources/css/` (not here): registry modules, Vite shells, and any CSS still `import`ed from a page/component.

Active CSS policy lives in `AGENTS.md` and `docs/PROJECT_CONTRACT.md`.
