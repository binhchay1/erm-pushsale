# V105 — Route smoke + Product business status

Bản này đi tiếp từ `erm-pushsale-v104-full-seed-qa-command.zip`.

## Các lỗi chính đã xử lý

1. **Khoảng trắng đầu trang / vùng title**
   - Thêm lớp CSS cuối cùng trong `resources/css/pushsale-unified-page-shell-contract.css` để ép các trang `ps-adminlte-page` và `pushsale-page` không tạo top spacer.
   - Header đầu trang giảm về nhịp 42px, bỏ padding/margin thừa phía trên.
   - Riêng trang sản phẩm ép lại vùng bảng để không còn khoảng trống thừa trái/phải như ảnh chụp.

2. **Checkbox “Ngừng KD” ở `/admin/products`**
   - Checkbox giờ click được thật.
   - Frontend gọi `PATCH /admin/products/{product}/business-status`.
   - Backend validate boolean, kiểm quyền admin, cập nhật đồng bộ:
     - `is_active`
     - `available_marketing`
     - `available_sale`
     - `available_care`
   - Khi ngừng kinh doanh, sản phẩm không còn đi vào các luồng phát sinh mới cho marketing/sale/CSKH. Lịch sử đơn, tồn kho, báo cáo cũ vẫn giữ để đối soát.
   - Trả flash `success` để AppLayout hiện toast.

3. **Fix bug update sản phẩm có thể gây 500**
   - `ProductController::update()` thiếu biến `$hasAttributeValueIds`; đã bổ sung.

4. **Trang quản lý combo 1.3.2 / `/admin/catalog/combos`**
   - Component React được defensive hơn khi thiếu props, thiếu rows/pagination/filterOptions/routeUrl.
   - Sửa suy luận trạng thái combo để “Ngừng áp dụng” không bị hiểu thành đang áp dụng.
   - `date_from/date_to` sai định dạng trong filter không làm route/runtime vỡ nữa; backend bỏ qua giá trị ngày không parse được.

5. **Route smoke test**
   - `erm:test-all` mặc định có thêm bước `routes:view-smoke`.
   - Thêm option:
     ```bash
     php artisan erm:test-all --route-smoke
     php artisan erm:test-all --route-smoke --no-route-query-noise
     ```
   - `deploy/test-all.sh` mặc định bật route smoke, có thể tắt bằng:
     ```bash
     ROUTE_SMOKE=0 bash deploy/test-all.sh
     ```

6. **PHPUnit route smoke**
   - Thêm `tests/Feature/Pushsale/AdminViewRoutesSmokeTest.php`.
   - Test các route view quan trọng không trả 500 khi có query lạ.
   - Test route combo, sản phẩm, kho, sale, marketing, accounting, docs.
   - Test checkbox “Ngừng KD” cập nhật đúng business flags.

## Lệnh test đề xuất

```bash
composer install
pnpm install
pnpm build
php artisan optimize:clear
php artisan erm:test-all --fresh --seed --phpunit --audit --route-smoke --landing-flow --flow --base-url=https://salesloop.vn --json
```

Test riêng phần mới:

```bash
php artisan test --filter=AdminViewRoutesSmokeTest
php artisan test --filter=product_business_status_checkbox_updates_business_flags
php artisan erm:test-all --route-smoke --base-url=https://salesloop.vn --json
```

Nếu muốn route smoke live đi thẳng vào trang admin thay vì bị redirect login, bật access chụp ảnh/test AI trước rồi tắt ngay sau đó:

```bash
APP_DIR=/var/www/erm-pushsale DOMAIN=salesloop.vn bash deploy/enable-ai-screenshot-access.sh
APP_DIR=/var/www/erm-pushsale BASE_URL=https://salesloop.vn ROUTE_SMOKE=1 PAGES=1 bash deploy/test-all.sh
APP_DIR=/var/www/erm-pushsale bash deploy/disable-ai-screenshot-access.sh
```
