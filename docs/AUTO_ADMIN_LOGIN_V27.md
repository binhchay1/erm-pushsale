# V27 — Temporary Auto Admin Login

Mục đích: tắt tạm màn đăng nhập trên môi trường test/staging để QA mở trực tiếp mọi URL trong ERM.

## Bật trên server test

Thêm vào `.env`:

```dotenv
ERM_AUTO_ADMIN_LOGIN=true
ERM_AUTO_ADMIN_LOGIN_HOSTS=erm-pushsale.duckdns.org
```

Nếu muốn chỉ định đúng tài khoản admin:

```dotenv
ERM_AUTO_ADMIN_LOGIN_EMAIL=admin@example.com
```

hoặc:

```dotenv
ERM_AUTO_ADMIN_LOGIN_USER_ID=1
```

Nếu không chỉ định email/user id, hệ thống tự chọn admin công ty active đầu tiên, ưu tiên owner.

Sau khi sửa `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

## Tắt lại sau khi test

```dotenv
ERM_AUTO_ADMIN_LOGIN=false
```

rồi chạy lại:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate
```

## Ghi chú an toàn

- Chỉ áp dụng cho web routes, không áp dụng cho `/api/*`.
- Nên giới hạn `ERM_AUTO_ADMIN_LOGIN_HOSTS` đúng domain test.
- Không bật trên production thật sau khi nghiệm thu xong.
