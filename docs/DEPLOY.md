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

## Smoke sau cleanup dead-asset (A+B)

Commit gộp xóa asset chết + docs slim. **Không** đổi schema/route live. Checklist trên `salesloop.vn` sau `git push ssd` + hook build:

### 0. Preflight (SSH app VM)

```bash
cd /var/www/erm-pushsale
git log -1 --oneline
test ! -f AGENTS.md && test -f docs/AGENTS.md && echo OK_agents
test ! -f config/pushsale_page_merges.php && echo OK_no_merges
test -f scripts/audit-pushsale-contract.mjs && test -f scripts/enforce-pnpm.cjs && echo OK_scripts
test ! -f deploy/security-audit-remote.sh && test -f deploy/ops-harden-root.sh && echo OK_deploy
ls resources/css/_archive/   # chỉ README.md
php artisan route:list --name=admin.marketing.landing-approvals --columns=method,uri,name
php artisan route:list --name=activity-logs --columns=method,uri,name
php artisan about | head
```

### 1. Shell / CSS (hard refresh Ctrl+Shift+R)

- Login admin → sidebar L1/L2 + L3 flyout hover OK (canonical CSS còn).
- Một trang admin bất kỳ: đúng 1 `PageHeader`, không double header.
- Sale 4.1 / Kho 5.1 / KT 6.1 table load + icon legacy FA còn.

### 2. Trang từng đụng file xóa (A)

| Check | URL / hành động | Kỳ vọng |
| --- | --- | --- |
| Landing duyệt | `/admin/marketing/landing-approvals` | `LandingApprovalPage` (không 404/500) |
| Campaigns live | `/admin/marketing/campaigns` (hoặc menu 2.x) | `Marketing/Campaigns/*` |
| Activity logs | menu nhật ký | Index OK; show redirect index |
| Phiếu nhập | menu kho phiếu | Form mở, toast lỗi thân thiện nếu thiếu SP |

### 3. Báo cáo / CEO (không liên quan xóa nhưng regression)

- CEO 7.x yearly/monthly mở được, không 500.
- Một report 4.6 Excel export thử 1 file.

### 4. Playwright local (trước hoặc sau deploy, cần `.env` + APP_KEY)

```bash
pnpm check:frontend
php vendor/bin/phpunit --filter PushsalePageRegistryTest
pnpm e2e:flows -- e2e/flows/01-login.spec.ts e2e/flows/16-warehouse-voucher-inbound.spec.ts e2e/flows/17-ceo-menu7.spec.ts
```

E2E **không** reference file A/B đã xóa. Fail do thiếu seed/kho/SP ≠ fail do cleanup.

### 5. Rollback nếu shell/CSS vỡ

```bash
cd /var/www/erm-pushsale && git revert HEAD --no-edit && git push origin HEAD && git push ssd HEAD
```

Hoặc `git reset --hard <sha-trước>` trên bare + re-push (chỉ khi team đồng ý). CSS archive đã xóa có thể lấy lại từ git history nếu cần debug parity — **không** cần cho runtime.


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
