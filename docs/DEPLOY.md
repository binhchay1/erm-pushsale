# Deploy

Production: **salesloop.vn**. Local → `git push origin main` + `git push ssd main` (post-receive build/optimize).

## Remotes

| Remote | URL |
| --- | --- |
| `origin` | GitHub (`binhchay1/erm-pushsale`) |
| `ssd` | `deploy@salesloop.vn:/var/git/erm-pushsale.git` |

Hook on `ssd`: `pnpm build`, `artisan migrate --force`, optimize, reload PHP-FPM, restart Horizon/Reverb.

## Scripts giữ lại (`deploy/`)

| Script | Việc |
| --- | --- |
| `ssd-deploy.sh` / `prod-deploy.sh` | Deploy build path |
| `fix-build-permissions.sh` | Owner `public/build` trước Vite |
| `install-scheduler-cron.sh` | Cron scheduler |
| `harden-prod-env.sh` | Hardening env prod |
| `post-deploy-check.sh` / `prod-smoke-test.sh` | Smoke sau deploy |
| `supervisor/*` | Horizon / worker conf |

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

## Security & backup (ops)

Topology:

| Role | Host |
| --- | --- |
| App (nginx/PHP/Redis/Horizon/Reverb) | `salesloop.vn` / `14.225.204.137` |
| MySQL 8 | `14.225.205.65` (not on app VM) |

### Local DR backup (app VM)

- Command: `php artisan ops:backup` (scheduled **02:00** via `schedule:run`).
- Output: `OPS_BACKUP_PATH` (default `/var/backups/erm-pushsale` or `/home/deploy/backups/erm-pushsale`).
- Contents: `database.sql.gz` + `storage-app.tar.gz` + `meta.json` (sha256).
- Retention: 7 daily / 4 weekly / 3 monthly (~DB ~0.5GB raw; gzip nhỏ hơn nhiều).
- Prefer `mysqldump` (install via root harden); PDO stream is fallback.

### One-shot root harden

```bash
sudo bash /var/www/erm-pushsale/deploy/ops-harden-root.sh
```

Applies: `fail2ban`, `mysql-client`, UFW (22/80/443), SSH key-only, Reverb `127.0.0.1:8080`, nginx rate-limit + deny sensitive paths, scheduler cron, first backup.

Without root, deploy user can still install scheduler fallback:

```bash
bash /var/www/erm-pushsale/deploy/ops-install-deploy-cron.sh
```

### Provider snapshots (bắt buộc cho DR thật)

Local dump **không** thay snapshot hypervisor. Trên panel VPS (cả **hai** máy App + DB): snapshot tuần (giữ ≥4 bản) + ideally copy offsite. App-only snapshot **không** đủ vì MySQL nằm VM khác.

### DDoS / edge

App hiện serve thẳng nginx (không Cloudflare). Để chống DDoS L7: bật Cloudflare (orange cloud) + WAF rate rules; giữ origin firewall chỉ allow CF IP nếu khóa chặt. UFW + fail2ban + nginx `limit_req` chỉ là lớp host, không thay CDN/WAF.
