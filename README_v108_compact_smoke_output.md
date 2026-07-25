# ERM Pushsale v108 - Compact smoke test output

## Mục tiêu

Bản này chỉ sửa tầng QA command/smoke test để log trên server ngắn, dễ copy lên ChatGPT, không còn in nguyên `generated_urls`/`results` dài hàng nghìn dòng khi route smoke lỗi.

## Thay đổi chính

- `php artisan erm:test-all --route-smoke` hiện in summary dạng:
  - `total`, `passed`, `failed`
  - `status_counts`
  - `error_counts`
  - `failed_top` giới hạn theo `--smoke-limit`
  - `hint` lỗi đã rút gọn
- Thêm option:
  - `--smoke-limit=20`: số lỗi route/page muốn in ra để copy.
  - `--full-json`: khi cần debug sâu mới in full payload cũ.
- `routes:view-smoke` và `pages:scan` cùng có summary compact.
- `--json` mặc định giờ trả JSON gọn, chỉ gồm summary/counters quan trọng. Muốn payload đầy đủ thì thêm `--full-json`.

## Lệnh gợi ý

```bash
php artisan optimize:clear
php artisan erm:test-all --seed --audit --route-smoke --smoke-limit=30 --base-url=https://salesloop.vn --json
```

Khi có nhiều lỗi route, chỉ cần copy đoạn:

```text
ROUTES:VIEW-SMOKE SUMMARY
...
failed_top=
...
```

hoặc JSON compact ở cuối command.

## Lint

Đã chạy lint PHP/shell trong `app`, `database`, `routes`, `tests`, `config`, `bootstrap`, `deploy`: OK.
