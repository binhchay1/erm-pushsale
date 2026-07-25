# Deploy Permission Contract

## Vì sao deploy có thể fail ở bước Vite build?

Vite luôn làm sạch `public/build` trước khi build. Nếu trong thư mục này còn file chunk cũ thuộc owner khác, ví dụ `root` hoặc `www-data`, user chạy deploy không unlink được file đó và build dừng với lỗi:

```text
EACCES: permission denied, unlink '/var/www/erm-pushsale/public/build/assets/...js'
```

Đây không phải lỗi React/Vite hay do xóa-copy source zip. Đây là lỗi quyền file runtime trên server.

## Quy ước quyền

- Source code và `public/build`: `deploy:www-data`.
- `storage` và `bootstrap/cache`: deploy user và `www-data` cùng ghi được.
- Không commit `public/build`, vì thư mục này là output của Vite và đã nằm trong `.gitignore`.
- Không chạy `pnpm build` bằng root nếu deploy hook chạy bằng user `deploy`, trừ khi chown lại sau build.

## Lệnh vá nhanh trên server

```bash
cd /var/www/erm-pushsale
sudo chown -R deploy:www-data public/build storage bootstrap/cache
sudo chmod -R ug+rwX public/build storage bootstrap/cache
sudo setfacl -R -m u:deploy:rwx -m u:www-data:rwx public/build storage bootstrap/cache || true
sudo setfacl -dR -m u:deploy:rwx -m u:www-data:rwx public/build storage bootstrap/cache || true
```

Sau đó chạy lại:

```bash
pnpm build
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

## Script trong repo

Repo có script:

```bash
deploy/fix-build-permissions.sh
```

Các script `deploy/ssd-deploy.sh` và `deploy/prod-deploy.sh` gọi script này trước `pnpm run build` để tránh lỗi EACCES lặp lại.

Nếu server dùng bare git hook riêng không gọi hai script trên, hãy thêm dòng này vào hook trước `pnpm build`:

```bash
APP_DIR=/var/www/erm-pushsale DEPLOY_USER=deploy WEB_GROUP=www-data bash deploy/fix-build-permissions.sh
```
