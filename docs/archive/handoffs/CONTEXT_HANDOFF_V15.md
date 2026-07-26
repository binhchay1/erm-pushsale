# Context handoff V15

## Mục tiêu release

V15 hoàn thiện 9 trang `template-six`, bổ sung upsale vào công thức Pushsale cũ, chuẩn hóa 12 nhóm doanh số, đưa giao một phần thành trạng thái chính thức và tái sử dụng cùng backend report cho menu theo từng role.

## Triển khai

```bash
cd /var/www/erm-pushsale

php artisan down
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear

npm ci --ignore-scripts
npm run build

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
php artisan up
```

Không copy `.env`, `vendor`, `node_modules`, log hoặc cache runtime từ gói release. Giữ `.env` hiện tại của server.

## Kiểm tra sau deploy

```bash
php artisan about
php artisan route:list | grep reports
php artisan horizon:status
php artisan queue:monitor redis:reports,redis:exports --max=100
php artisan test --filter=TemplateSixReportsTest
```

## Kiểm tra thủ công theo vai trò

- Admin: các trang 4.5.1–4.5.10, 6.3.1–6.3.12 và CEO V2.
- Sales staff: 4.5.1–4.5.4 và 4.5.8; dữ liệu tự giới hạn theo user.
- Sales leader/supervisor: thêm 4.5.5, 4.5.6, 4.5.9, 4.5.10 và lọc member trong phạm vi.
- Marketing leader: 2.7.1–2.7.8, gồm ma trận sản phẩm và báo cáo upsale.
- Warehouse staff: 5.5.3, 5.5.9, 5.5.10.
- Accounting staff: các report dùng chung 6.3.2–6.3.12 theo quyền báo cáo.
- Xác minh các role không nhận URL `/admin/reports/extra/*` trong sidebar.

## Đối soát nghiệp vụ

1. Chọn một khách có landing chính và ít nhất một packet upsale.
2. Xác minh Hồ sơ khách hàng chỉ có một customer và contact không bị nhân đôi.
3. Xác minh các dòng bán thêm có `order_items.item_type = upsell`.
4. So sánh cùng kỳ giữa 4.5.2, 4.5.4, 4.5.5, 4.5.6, 4.5.7 và 6.3.9.
5. Tổng doanh số phải gồm upsale; cột upsale chỉ chứa doanh thu dòng upsale.
6. Mở đủ 12 nhóm ở 4.5.5/4.5.6 và đối chiếu số đơn, số sản phẩm, doanh số.
7. Đơn `partial_delivery` phải xuất hiện trong giao thành công, giao một phần và đúng nhóm đối soát.
8. Chiết khấu, phí vận chuyển và doanh số thuần phải dùng snapshot/order thực tế.
9. Export phải giữ đủ cột VND và không xuất dữ liệu mẫu từ HTML tham chiếu.

## Nguồn sự thật mới

- `OrderRevenueClassifier`: 12 nhóm doanh số dùng chung cho report và Marketing Dashboard.
- `DeliveryStatus::revenueEligible()`: gồm `partial_delivery`.
- `LeadContactMetrics`: contact chỉ từ packet `counts_as_lead=true`.
- `Order::netRevenue()`: công thức doanh số thuần chung.
- `NavigationService` + report registry: menu role và quyền xem báo cáo.

## Ghi chú test trong môi trường đóng gói

Frontend production build và PHP syntax lint chạy độc lập. PHPUnit cần `vendor/autoload.php`, vì vậy phải chạy sau `composer install` trên server, CI hoặc môi trường dev có Composer.
