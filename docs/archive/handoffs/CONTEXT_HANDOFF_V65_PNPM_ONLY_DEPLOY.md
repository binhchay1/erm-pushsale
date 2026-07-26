# V65 PNPM-only frontend deploy cleanup

## Mục tiêu

Chuẩn hóa source frontend chỉ dùng PNPM để tránh deploy lúc dùng npm, lúc dùng pnpm, lúc Corepack tự kéo sai phiên bản.

## Thay đổi chính

- `package.json`
  - Thêm `packageManager: pnpm@9.15.9`.
  - Thêm `engines.node >=20.0.0` và `engines.pnpm >=9.15.9 <10.0.0`.
  - Thêm `preinstall` để chặn nhầm `npm install`/`npm ci`.
- `pnpm-workspace.yaml`
  - Thêm `packages: ['.']` để sửa lỗi `packages field missing or empty`.
  - Giữ `allowBuilds.msw=false`.
- `.npmrc`
  - Chuyển về cấu hình PNPM sạch, không còn `ignore-scripts=true`.
  - Bật `engine-strict=true` để không cho PNPM 11 chạy nhầm trên Node 20.
- Xóa `package-lock.json` khỏi source.
- `deploy/ssd-deploy.sh` và `deploy/prod-deploy.sh`
  - Đổi frontend build từ npm sang:
    - `pnpm install --frozen-lockfile`
    - `pnpm run build`
  - Không `corepack enable`, không `corepack prepare`, không `npm install -g pnpm` trong deploy script.

## Server requirement

Cài PNPM đúng một lần ở môi trường deploy user, không cài/gỡ global trong hook mỗi lần push.

Khuyến nghị dùng Node hiện tại trên server:

```bash
node -v   # v20.x OK
```

Cài PNPM cho user `deploy`:

```bash
sudo -H -u deploy bash -lc '
set -e
mkdir -p "$HOME/.local/share/pnpm" "$HOME/.cache/node/corepack" "$HOME/.npm"
export PNPM_HOME="$HOME/.local/share/pnpm"
export COREPACK_HOME="$HOME/.cache/node/corepack"
export PATH="$PNPM_HOME:$PATH"
corepack prepare pnpm@9.15.9 --activate
pnpm -v
'
```

Nếu muốn dùng PNPM global bằng root thì cũng phải pin đúng phiên bản:

```bash
corepack prepare pnpm@9.15.9 --activate
pnpm -v
```

## Auto deploy hook expected frontend block

Trong `/usr/local/bin/erm-pushsale-deploy`, frontend block nên là:

```bash
export HOME=/home/deploy
export PNPM_HOME=/home/deploy/.local/share/pnpm
export COREPACK_HOME=/home/deploy/.cache/node/corepack
export PATH="$PNPM_HOME:/usr/local/bin:/usr/bin:/bin:$PATH"

pnpm install --frozen-lockfile
pnpm run build
```

Không để các dòng sau trong hook:

```bash
corepack enable
corepack disable
corepack prepare pnpm@latest --activate
npm install -g pnpm
npm uninstall -g pnpm
npm ci
npm install
npm run build
```

## Kiểm tra sau deploy

```bash
cd /var/www/erm-pushsale
pnpm -v
pnpm install --frozen-lockfile
pnpm run build
ls -la public/build/manifest.json
php artisan horizon:status
supervisorctl status
```
