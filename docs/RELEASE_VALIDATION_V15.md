# Release validation V15

Kết quả kiểm tra tại thời điểm đóng gói:

- PHP syntax lint: 698 file, không có lỗi cú pháp.
- Vite production build: thành công, 3.430 module.
- `npm audit --audit-level=high`: 0 lỗ hổng.
- Pushsale template audit V8: 65 trang, 9 module gộp, 79 template đã sanitize, 0 lỗi.
- 9 template-six không còn thẻ script thực thi; CSS được scope theo `data-template-code`.
- AdminLTE 2, Bootstrap, Select2, Datepicker và Font Awesome tham chiếu đã có trong `public/vendor`.
- PHPUnit chưa chạy trong môi trường đóng gói vì không có `vendor/autoload.php` và Composer; test V15 đã được thêm để chạy trên server/CI sau `composer install`.
