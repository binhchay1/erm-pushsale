# Deploy

Production: **salesloop.vn**. Local → `git push origin main` + `git push ssd main` (post-receive build/optimize).

## Remotes

| Remote | URL |
| --- | --- |
| `origin` | GitHub (`binhchay1/erm-pushsale`) |
| `ssd` | `deploy@salesloop.vn:/var/git/erm-pushsale.git` |

Hook on `ssd`: `pnpm build`, `artisan migrate --force`, optimize, reload PHP-FPM, restart Horizon/Reverb.

## Permissions (`public/build`)

Vite wipes `public/build`. Wrong owner → `EACCES unlink`.

```bash
cd /var/www/erm-pushsale
sudo chown -R deploy:www-data public/build storage bootstrap/cache
sudo chmod -R ug+rwX public/build storage bootstrap/cache
```

- Code + `public/build`: `deploy:www-data`
- Do **not** commit `public/build`
- Do **not** `pnpm build` as root if hook runs as `deploy`

## After deploy smoke

1. Hard refresh admin shell (sidebar + header).
2. Sale 4.1 / Kho 5.1 / KT 6.1 tables load.
3. One landing submit (if testing ingest).
4. One Excel export on a 4.6 report.
