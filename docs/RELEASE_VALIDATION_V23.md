# Release Validation V23

## Static checks

- PHP syntax lint toàn bộ app/config/database/routes/tests.
- ZIP CRC sau khi đóng gói.

## Frontend

Môi trường đóng gói không có `node_modules`, nên production build không chạy lại trong sandbox. Source CSS/JS đã được cập nhật. Đồng thời `public/build/assets/pushsale-*.css` được append lớp CSS V23 để deploy không bắt buộc build lại ngay.

## Runtime test cần chạy trên server

```bash
npm ci
npm run build
php artisan test
php artisan optimize:clear
php artisan horizon:terminate
```
