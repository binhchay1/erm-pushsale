# ERM Pushsale V14 — Context handoff

## Phạm vi hoàn thành

- Kết nối Landing có ngân sách tổng/ngày, kỳ chạy và tổng ngân sách dự kiến.
- Admin Dashboard được làm lại theo dữ liệu tài chính thật.
- Tách doanh số chốt, doanh thu ghi nhận, tiền đã thu và COD chưa thu.
- Tính Marketing, giá vốn snapshot, vận chuyển, nhân sự, chi phí vận hành, lợi nhuận gộp/ròng và giá trị tồn kho.
- Hiệu quả Landing có kế hoạch, thực chi, CPL, CPA, ROAS và cảnh báo vượt ngân sách.
- KPI tháng và dashboard dùng cùng công thức lương/thưởng.
- Template-five đã được tạo fragment an toàn, gộp dialog/modal vào đúng mã menu và dùng backend thật.
- Các asset AdminLTE/Bootstrap/Select2/Font Awesome cần cho giao diện template đã được đóng gói trong `public/vendor`.
- Toàn bộ tiền hiển thị theo VND.

## Deploy production

```bash
cd /var/www/erm-pushsale

composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

npm ci --ignore-scripts
npm run build

php artisan horizon:terminate
```

Nếu dùng Supervisor chỉ để giữ Horizon sống, kiểm tra process `php artisan horizon`; không bật lại các worker queue cũ chạy song song.

## Kiểm tra sau deploy

1. Mở menu `2.4.1 — Kết nối landing`, tạo kết nối có ngân sách và kỳ chạy.
2. Submit Landing chính + upsale và xác nhận một order, đúng item, đúng tổng tiền.
3. Chốt đơn, tạo vận đơn, cập nhật giao thành công và COD.
4. Nhập thực chi một vài ngày để kiểm tra trạng thái `mixed`.
5. Tạo KPI tháng có lương, ngày công và % thưởng.
6. Mở Admin Dashboard, đối chiếu:
   - doanh số chốt;
   - doanh thu ghi nhận;
   - tiền thu/COD;
   - Marketing;
   - giá vốn;
   - phí vận chuyển;
   - lương/thưởng;
   - chi phí vận hành;
   - lợi nhuận.
7. Không nhập lương/thưởng lần nữa vào chi phí vận hành; nếu có, dashboard sẽ cảnh báo nguy cơ trùng.

## Kiểm thử release

- `npm run build`
- PHP syntax lint toàn bộ `app`, `config`, `database`, `routes`, `tests`
- `php scripts/audit_pushsale_v8.php`
- `npm audit --omit=dev` — tại thời điểm đóng gói: 0 lỗ hổng
- PHPUnit cần chạy sau khi có `vendor`:

```bash
php artisan test --testsuite=Feature
```

Các test mới đáng chú ý:

- `tests/Feature/Marketing/LandingConnectionBudgetTest.php`
- `tests/Feature/Finance/PayrollCostServiceTest.php`
- `tests/Feature/Leads/LandingConnectionFlowTest.php`

Chi tiết công thức: `docs/FINANCIAL_CONTROL_V14.md`.
