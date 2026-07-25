# v118 - Landing connections 2.4.1 parity

Base: v117.

## Changes

- Fixed level-2 menu hover for items without level-3 submenu using final high-specificity CSS.
- Reworked `/admin/marketing/landing-connections` / menu 2.4.1 to match the Pushsale sample layout:
  - Header filter: title, "Chỉ lọc tất cả sản phẩm", marketing select, product search, keyword, search, gear, collapse arrow.
  - Tabs: Kết nối Facebook / Kết nối nguồn dữ liệu / Kết nối Website / Tất cả.
  - Table columns follow the sample: STT, Marketing, Tên nguồn kết nối / Url nguồn dữ liệu, Loại kết nối / Kênh quảng cáo, Sản phẩm, Ưu tiên sale, Cấu hình chia số, Url kết nối V2, Nhập TC, Duyệt, Cập nhật, and header action Thêm.
- Reworked add/edit source dialog:
  - Loại kết nối, Cấu hình chia số, Tên nguồn dữ liệu, Url nguồn dữ liệu, Url API, Sử dụng woocommerce, Kênh quảng cáo, Sản phẩm, Upsale URL, Chọn nhanh sale từ Nhóm sale, Ưu tiên sale, Nhập thủ công, Duyệt.
  - All relevant select fields use searchable Pushsale-style selects and real backend data.
- Backend serialization now exposes marketer email, sale emails, product type, and updated-by info for the page.

## Test

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Manual routes:

```text
/admin/marketing/landing-connections
/ld/unit-admin/ket-noi-landing-website?tid=2
```
