# ERM Pushsale — Context handoff V12

## Baseline source

Use `erm-pushsale-ui-v12-layout-modal-fix.zip` as the only baseline for the next conversation.
Do not continue from V8/V9/V10/V11 hotfixes individually.

## Business/UI convention

- Every numbered menu item is a real business page.
- The `.txt/.html` file named by menu code is the source structure for that page.
- PNG files with the same code are visual references.
- Suffixes such as `dialog-create`, `modal`, `đầu trang`, `cuối trang` belong to the same page; they are not separate modules.
- Existing ERM services/models must be reused when the business meaning matches.
- New backend tables/services are added only when the project genuinely lacks that business capability.
- Runtime tables and reports must use database data. Captured Pushsale rows are never demo data.

## Completed large pages

- V9: Hồ sơ khách hàng, filters/actions/modals, one visible customer per normalized phone number.
- V10: Sale tác nghiệp, deferred order code until close, operation history, order/update dialogs.
- V11: Marketing dashboard and Marketing ranking, live order/lead/source data.
- V12:
  - Removed the duplicate outer content gutter from the authenticated shell.
  - Normalized Pushsale/AdminLTE typography to Arial.
  - Added stable colgroups matching Pushsale column geometry for Marketing dashboard.
  - Widened the detailed ranking table.
  - Rebuilt viewport centering/overflow rules for Radix, custom React and Bootstrap 3 modals.
  - Added SQL pagination to Thủ kho tác nghiệp instead of loading all closed orders.
  - Kept grouped delivery-status tab counts in SQL.

## Important routes

- `/admin/marketing/dashboard`
- `/admin/rankings`
- `/sales/workspace` and `/admin/sales/workspace`
- `/warehouse/workspace` and `/admin/warehouse/operations`
- `/admin/sales/customers`

## Deployment

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

V12 has no new migration. `public/build` is included.

## Recommended first message in a new conversation

> Bro, tiếp tục project ERM Pushsale từ file V12 mình đính kèm. Hãy đọc `docs/CONTEXT_HANDOFF_V12.md` trước. Mỗi file template theo mã menu là một trang nghiệp vụ riêng; suffix là modal/trạng thái của trang đó. Dữ liệu phải là dữ liệu thật từ backend, không dùng dòng mẫu trong HTML. Mình sẽ gửi tiếp từng trang lớn để đối chiếu pixel và hoàn thiện business.
