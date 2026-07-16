# Release validation — ERM Pushsale V17

## Kết quả đã chạy

### Frontend production build

```text
Vite 8.0.16
3435 modules transformed
build successful
```

Các entry tách biệt được sinh ra:

- `app-*.css` — shared token/component;
- `public-*.css` — public/login shell;
- `pushsale-*.css` — internal ERM shell.

### Template/UI audit

```bash
python scripts/audit_pushsale_ui_v17.py
```

Kết quả:

```text
UI CONTRACT AUDIT: PASS (79 templates)
```

Audit kiểm tra:

- toàn bộ style template được scope;
- không còn script thực thi;
- không còn generated Select2/Chosen DOM;
- không còn employee option/tên tenant Pushsale mẫu;
- login template có dynamic anchor;
- CSS entry public/internal được tách;
- V17 final contract chứa filter/action/modal/login selectors.

### PHP syntax

Các file backend V17 đã lint riêng và toàn bộ source PHP được lint trước khi đóng gói.

## Chưa chạy được trong môi trường đóng gói

PHPUnit cần `vendor/autoload.php`. Source release không chứa `vendor` và máy đóng gói không có Composer dependency đã cài, nên không tuyên bố test runtime đã chạy.

Sau khi cài dependency, chạy:

```bash
php artisan test --filter=UiContractV17Test
php artisan test --filter=PushsaleTemplateScopeV17Test
```

## Tiêu chí nghiệm thu chính

- Không có filter row chừa khoảng trống đầu hàng do cột Bootstrap rỗng.
- Không có border con trong action cell.
- Login history không có danh sách tên hardcode.
- Login success/failed/blocked/logout có audit log.
- Customer operation history modal không vượt viewport.
- Internal menu/table/form/modal dùng cùng font Arial.
- Public/login không tải CSS AdminLTE/Pushsale.
- Không có dữ liệu tenant mẫu trong template runtime.
