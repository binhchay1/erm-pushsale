# ERM Pushsale Release Notes

Tập trung lịch sử thay đổi UI/business theo version để không sinh quá nhiều file README lẻ.



---

## README_v102_unified_page_shell_fix

# V102 - Unified Pushsale page shell, seed coverage, and route cleanup

## Main target

This patch fixes the page chrome mismatch shown on `/admin/warehouse/vouchers` compared with Pushsale's `/ld/warehouse/danh-sach-phieu-xuat-nhap-kho`.

The old app allowed each imported/template page to keep its own header row, filter row, title alignment, spacer, and table wrapper. That made pages look different depending on legacy HTML and legacy numbered files. This patch adds one final shared contract layer that normalizes the same structure for all Pushsale pages at runtime.

## Important files

- `resources/js/pages/Pushsale/BusinessPage.jsx`
  - Adds runtime normalization classes for legacy template headers, filter rows, composite filter/table rows, nested template wrappers, and empty spacer nodes.
- `resources/css/pushsale-unified-page-shell-contract.css`
  - Final shared page shell CSS loaded last.
  - Normalizes page header, title, search/action area, filter rows, table wrappers, and page scrolling.
- `resources/js/lib/pushsaleStyleRegistry.js`
  - Loads the final CSS contract last.
- `app/Http/Controllers/Admin/Pushsale/Warehouse/WarehouseVoucherListController.php`
  - Semantic controller for menu/page `5.3.2` voucher list.
- `app/Http/Controllers/Admin/Pushsale/Warehouse/WarehouseVoucherEntryController.php`
  - Semantic controller for menu/page `5.3.1` voucher entry.
- `routes/pushsale_pages.php`
  - Routes warehouse voucher pages through the semantic controllers above.
- `tests/Feature/Pushsale/PushsaleMenuDemoCoverageTest.php`
  - Seeds the application and verifies configured menu pages can return rows/meta through the real `PushsalePageService`.
- `tests/Unit/PushsaleUnifiedShellContractTest.php`
  - Verifies the final page shell CSS is loaded after older page CSS and that the runtime normalizer markers exist.
- `scripts/audit-pushsale-page-shell.mjs`
  - Audits configured Pushsale pages for template/header availability and flags remaining legacy-numbered files.
- `scripts/audit-pushsale-route-semantic-names.mjs`
  - Audits route definitions so new routes do not expose menu-number/page-number naming.
- `docs/PUSHSALE_PAGE_SHELL_REFACTOR_V102.md`
  - Handoff notes for the next context window.

## Commands to run after applying patch

```bash
composer install
pnpm install
php artisan optimize:clear
php artisan migrate --seed
php artisan test --filter=PushsaleUnifiedShellContractTest
php artisan test --filter=PushsaleMenuDemoCoverageTest
node scripts/audit-pushsale-route-semantic-names.mjs
node scripts/audit-pushsale-page-shell.mjs
pnpm build
```

## Notes

Legacy `Page_*` React components and `Page*_Controller` controllers are still present in this patch. They are intentionally not deleted in one sweep because many page keys still map to those files. The new pattern is already applied to warehouse voucher pages and documented; the rest should be migrated module-by-module so old files can be deleted safely once no route/config references them.


---

## README_v104_full_seed_qa

# v104 — full seed + ERM QA command

## Vì sao có bản này

Sau khi copy zip v103 đè lên repo, commit log cho thấy deploy build được, nhưng có 2 rủi ro cần khóa lại:

1. Một số asset legacy `/public/vendor/adminlte2` và `/public/vendor/font-awesome` bị xóa khỏi repo trong khi Blade/React shell vẫn còn reference các path đó.
2. Dữ liệu seed nằm rải ở nhiều seeder, nên mỗi lần thêm luồng mới rất dễ quên seed hoặc quên test route/backend tương ứng.

## Thay đổi chính

- Thêm `database/seeders/FullBusinessDemoSeeder.php` làm nguồn seed chuẩn cho toàn bộ business ERM Pushsale.
- `DatabaseSeeder` giờ gọi thẳng `FullBusinessDemoSeeder`, nên `php artisan db:seed --force` cũng sinh đầy đủ demo.
- Mở rộng `DemoResetSeeder` và `FlowDataResetSeeder` để xóa sạch các bảng mới: landing connection, customer interaction, Pancake, voucher kho, return receipt, report facts/snapshots, data distribution, v.v.
- `StagingTestService` dùng `FullBusinessDemoSeeder`, endpoint `__erm-test/demo-ui` và `bootstrap` sẽ có dữ liệu đầy đủ hơn.
- Thêm command chuẩn: `php artisan erm:test-all`.
- Thêm script wrapper: `deploy/test-all.sh`.
- Restore các CSS compatibility path tối thiểu dưới `/public/vendor/...` để tránh 404 asset sau khi v103 xóa thư mục vendor cũ. Không bundle font binary.

## Lệnh chạy nhanh

```bash
php artisan erm:test-all
```

Chạy staging full hơn:

```bash
APP_DIR=/var/www/erm-pushsale \
BASE_URL=https://salesloop.vn \
BUILD=1 \
PAGES=1 \
bash deploy/test-all.sh
```

Reset sạch DB staging trước khi seed lại:

```bash
php artisan erm:test-all --fresh --seed --phpunit --audit --landing-flow --flow --pages --all-pages --base-url=https://salesloop.vn
```

Test riêng luồng kho:

```bash
php artisan erm:test-all --seed --phpunit --filter=WarehouseVoucherBusinessLinkTest
```


---

## README_v105_route_smoke_product_status

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


---

## README_v106_seed_metadata_fix

# V106 - Full seed metadata schema fix

## Lỗi đã sửa

Khi chạy `php artisan db:seed`, `FacebookPageMappingSeeder` ghi `integration_connections.metadata`, nhưng DB staging đã chạy migration cleanup cũ nên cột này không còn tồn tại:

```text
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'metadata' in 'field list'
```

## Cách xử lý trong source

- Thêm migration restore:
  - `database/migrations/2026_07_25_001060_restore_integration_connection_metadata_columns.php`
- Migration restore lại:
  - `integration_connections.metadata`
  - `shipping_partner_connections.metadata`
- Seeder `FacebookPageMappingSeeder` được guard bằng `Schema::hasColumn(...)`, nên không chết nếu ai đó chạy seed trước migrate.
- Thêm test schema:
  - `tests/Feature/Pushsale/IntegrationConnectionMetadataSchemaTest.php`

## Lệnh chạy trên server

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed
```

Hoặc dùng command tổng để lần sau không quên migrate trước seed:

```bash
php artisan erm:test-all --seed --route-smoke --base-url=https://salesloop.vn --json
```


---

## README_v107_server_error_sweep

# v107 - Server error sweep: schema repair, QA route smoke, PHPUnit fallback

Bản này xử lý các lỗi xuất hiện khi chạy thật trên server bằng:

- Repair schema non-destructive trước khi seed/test:
  - `users.is_active`
  - `integration_connections.metadata`
  - `shipping_partner_connections.metadata`
- `FullBusinessDemoSeeder` tự gọi schema repair trước khi reset/seed, nên `php artisan db:seed` không còn chết nếu thiếu các cột contract.
- `erm:test-all` chạy thêm bước `schema:contract-repair` trước health/seed.
- Health không còn fail giả vì block data counts không có key `ok`.
- Route smoke không dùng HTTP public unauthenticated nữa. Nó dispatch nội bộ qua Laravel kernel, tự đăng nhập user theo role cho từng nhóm route để bắt lỗi 500 thật thay vì báo hàng trăm 401.
- PHPUnit fallback: nếu server deploy `composer install --no-dev` không có `php artisan test`/`vendor/bin/phpunit`, step phpunit sẽ SKIP có ghi chú; các smoke/flow/audit vẫn chạy.
- `reports:verify-facts` trong `erm:test-all` chạy `--repair` để tự build closure/facts thiếu sau khi fresh seed.

## Lệnh chạy trên server

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:test-all --fresh --seed --phpunit --audit --route-smoke --landing-flow --flow --base-url=https://salesloop.vn --json
```

Nếu chỉ muốn vá schema + seed:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan db:seed
```

Nếu chỉ muốn bắt 500 ở route/view:

```bash
php artisan erm:test-all --seed --route-smoke --base-url=https://salesloop.vn --json
```


---

## README_v108_compact_smoke_output

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


---

## README_v109_seed_route_ui_sweep

# v109 — Seed, route 500, page spacing, menu hover sweep

Bản này sửa các lỗi phát hiện trực tiếp trên staging sau v108:

## Backend/seed
- `SalesPipelineSeeder` không còn chết khi một campaign/source không map được `product_id`.
- Nếu campaign thiếu sản phẩm, seeder tự lấy sản phẩm đang kinh doanh đầu tiên và gắn lại vào campaign trước khi tạo order demo.
- Giá dòng order/upsell được ép an toàn để không đọc property trên null.

## Route/view 500
- `PushsalePageService` dùng đúng cột `teams.leader_user_id` thay vì `leader_id`.
- `BasePushsalePageController` bổ sung `activeMenuCodeFromRequest()` để các page controller kế thừa không 500, gồm `/admin/catalog/combos`.
- Route smoke nội bộ tạm bật `app.debug=true` trong đúng request smoke để `failed_top` in ra exception/SQL thật thay vì trang lỗi chung “Liên hệ …”.

## UI
- Gỡ double top offset của `.content-wrapper`, bỏ khoảng trắng đầu trang dưới header xanh trên toàn bộ admin pages.
- Chuẩn hóa filter trang quản lý sản phẩm: title/search gọn, filter sort/status/category/marketing ở hàng 1, sale/care ở hàng 2.
- Menu con cấp 2 không có submenu cấp 3, ví dụ 2.1/2.2/2.3, hover/active nền xanh giống các dòng có flyout.

## Lệnh test đề xuất

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:test-all --seed --audit --route-smoke --smoke-limit=50 --base-url=https://salesloop.vn --json
```

Chỉ test route/view:

```bash
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```


---

## README_v110_product_permissions

# v110 - Product permission dialog uses real team/user data

## Scope
- Form "Thêm mới / chỉnh sửa sản phẩm" now loads real Marketing teams, Marketing users, Sale teams, Sale users from the current company.
- CSKH currently uses the same Sale team/user pool because the project has not introduced a separate `cskh` role yet. The data is still stored independently in `care_team_ids` and `care_user_ids`, so switching to a real CSKH role later only requires changing the option source.

## Backend contract
Products now have these nullable JSON columns:
- `marketing_team_ids`, `marketing_user_ids`
- `sale_team_ids`, `sale_user_ids`
- `care_team_ids`, `care_user_ids`

Meaning:
- `available_* = true` and `*_user_ids = []`: all active users in that group have access.
- `available_* = true` and `*_user_ids` has values: only selected users have access.
- `available_* = false`: no user in that group may use that product for new operational flows.

## Business flow guard
`DataDistributionService` now checks product `sale_user_ids` before allocating leads. If the selected sales are not allowed for the product, allocation is rejected with a validation error.

## Commands after deploy
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan db:seed
php artisan erm:test-all --seed --audit --route-smoke --smoke-limit=80 --base-url=https://salesloop.vn --json
```


---

## README_v113_docs_menu_select_upload

# V113 — Docs/menu/select/upload fixes

- `/docs` dùng shell riêng có sidebar trái như docs site, có scroll độc lập khỏi AdminLTE fixed layout.
- Bổ sung phần hướng dẫn theo role và theo menu lớn để training khách hàng.
- Fix hover menu cấp 2 không có submenu cấp 3: hover nền xanh, chữ trắng đồng bộ.
- Chuẩn hóa select native/common select: caret nhỏ màu xám, cách mép phải, dùng chung cho các trang.
- Fix khung chọn file import để nút upload không lòi khỏi cell.


---

## README_v114_warehouse_operations_filter_layout

# v114 - Warehouse operations filter layout

Base: v113 `erm-pushsale-v113-docs-menu-select-upload-fix.zip`.

## Fixes
- Align `/admin/warehouse/operations` top search/header row: title remains left, hide-zero checkbox + search cluster move to the right with a consistent right gutter.
- Normalize warehouse filter grid gutters and columns so rows are neat and aligned.
- Align delivery-status quick filters with the filter grid above and spread items evenly across the row.
- Keep table gutter aligned with filter/status sections.

## Deploy/test
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Check: `/admin/warehouse/operations`.


---

## README_v115_warehouse_521_parity

# v115 - Menu 5.2.1 Danh sách kho parity

## Scope
- Rebuild `/admin/warehouses` / menu 5.2.1 to match the provided Pushsale warehouse-list HTML sample:
  - One clean top search row with title + Province/District/Manager/Search.
  - Only the `Thêm` button is visible in the toolbar.
  - Table action column uses exactly 3 icons: edit, shipping account config, delete.
  - Action column width is fixed so it no longer looks smaller than other cells.
- Rebuild Add/Edit warehouse modal:
  - Title is `Thêm mới kho` for create and `Cập nhật kho` for edit.
  - Form layout follows the sample: left/right two-column fields, two-level address checkbox, default delivery provinces links.
- Add real warehouse-level shipping account configuration:
  - New dialog opened by the bank icon, titled `CẤU HÌNH TÀI KHOẢN GIAO HÀNG CỦA KHO [...]`.
  - Provider tabs and default provider/service config are populated from `config/shipping_partners.php`.
  - Data is saved through `PUT /admin/warehouses/{warehouse}/shipping-account`.
- Keep menu 1.6 pointing to unit feature settings, not system settings.

## Run
```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```


---

## README_v116_warehouse_521_alignment_icons_dialog

# v116 — Warehouse 5.2.1 alignment, action icons, shipping dialog

Base: v115.

Changes:
- Align `/admin/warehouses` table start with the page title/add button.
- Normalize action icons with a reusable `.ps-action-icon-row` / `.ps-action-icon` CSS contract.
- Keep 3 warehouse row actions on one line: edit, shipping-account config, delete.
- Widen and rebalance the shipping account dialog so provider tabs + form fields fit without broken horizontal overflow on common desktop widths.

Deploy:
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Quick QA:
- /admin/warehouses
- open row action: Cấu hình tài khoản giao hàng
- verify action icons are same baseline and dialog form fields fit.


---

## README_v117_address_selects

# v117 — Warehouse address filters + searchable Pushsale selects

Base: v116.

## Fixed
- Warehouse 5.2.1 province/district filters now use searchable Pushsale-style select controls.
- Controller passes a full Vietnam address catalog:
  - legacy 63-province dataset with province -> district -> ward;
  - 2025 two-level dataset with province -> ward.
- Warehouse list filter: selecting province dynamically populates district/ward-style search options.
- Warehouse add/edit dialog:
  - normal mode uses legacy Province -> District -> Ward;
  - "Sử dụng địa chỉ 2 cấp" mode uses 2025 Province -> Ward and disables District.
- Search box inside select now has the magnifier icon and select2-like dropdown styling.

## Test
```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

Manual checks:
- /admin/warehouses
- open province filter and type a province name
- select province and verify district options change
- open add/edit warehouse dialog, toggle "Sử dụng địa chỉ 2 cấp", verify 2025 province/ward mode


---

## README_v118_landing_connections_parity

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


---

## README_v119_landing_header_css_contract

# v119 – Landing 2.4.1 + header/filter/menu CSS contract

Bản này tiếp tục từ v118 và tập trung ổn định các phần giao diện Pushsale đang bị lệch cascade.

## Phạm vi sửa

1. **Menu cấp 2 không có submenu cấp 3**
   - Các dòng như `1.4 Kết nối giao hàng`, `1.5 Phân bổ data`, `1.6 Cấu hình chức năng`, `2.1`, `2.2`, `2.3` dùng chung hover xanh/trắng với các dòng có submenu cấp 3.
   - Rule cuối nằm trong `resources/css/pushsale-unified-page-shell-contract.css`, block `v119`.

2. **Header/filter chuẩn dùng chung**
   - Thêm contract `ps-page-header-v119` gồm:
     - `ps-page-header-main`: chia 2 vùng title + filter chính.
     - `ps-page-primary-filters`: các filter chính ở bên phải.
     - `ps-page-advanced-filters`: filter phụ mở bằng nút mũi tên.
   - Trang 2.4.1 đã dùng contract này; các trang tiếp theo nên chuyển dần sang cùng class thay vì tự viết header riêng.

3. **Trang 2.4.1 Kết nối landing / nguồn dữ liệu**
   - Tab đúng thứ tự Pushsale: Kết nối Facebook → Kết nối nguồn dữ liệu → Kết nối Website → Tất cả.
   - Nút xóa tự động tách về phía phải, không dính liền cụm tab.
   - Cột cuối có nút `+ Thêm` rõ màu trắng trên nền xanh table header.
   - Header filter dùng `PushsaleSelect` đồng bộ, có ô search trong dropdown.

4. **Dialog thêm/sửa nguồn dữ liệu**
   - Sửa checkbox `Nhập thủ công` và `Duyệt` không lệch hàng.
   - Sản phẩm chuyển sang `PushsaleSelect` thống nhất với các filter khác.
   - Giữ thêm field `Upsale URL` không bắt buộc.

5. **Seed dữ liệu thật cho menu 2.4.1**
   - Thêm `LandingConnectionDemoSeeder`.
   - Seeder tạo qua `LandingConnectionManager`, nên có đủ: `marketing_sources`, `landing_connections`, `landing_connection_sources`, `landing_connection_products`, `landing_connection_sales`.

## Lưu ý khi copy source

Không xóa các thư mục asset legacy sau vì nhiều trang Pushsale vẫn đang reference:

- `public/vendor/adminlte2`
- `public/vendor/font-awesome`
- `public/build` chỉ được thay bằng build mới sau khi chạy `pnpm build`

Nếu cần reset repo bằng zip, copy toàn bộ zip vào working tree rồi chạy lại build, không tự xóa riêng vendor CSS/icon.


---

## README_v120_landing_approval_flow

# v120 - Landing connection approval flow + CSS contract fix

## Nội dung thay đổi

- Menu 2.4.1 `Kết nối landing` chỉ tạo kết nối nguồn dữ liệu, không bắt buộc chọn sản phẩm ở dialog thêm/sửa.
- Menu 2.4.3 `Duyệt kết nối dữ liệu` là bước duyệt riêng cho Admin/Marketing leader:
  - Chọn sản phẩm/gói sản phẩm thật từ catalog.
  - Nhập ngân sách tổng hoặc ngân sách/ngày.
  - Duyệt kết nối để bật luồng nhận data thật.
- Backend route `/admin/marketing/landing-connections/records` không còn validate bắt buộc `products` ở bước tạo kết nối.
- Seeder `LandingConnectionDemoSeeder` tạo cả bản ghi đã duyệt và bản ghi chờ duyệt chưa gắn sản phẩm để test đúng luồng mới.
- Fix CSS hover menu cấp 2 không có submenu cấp 3: hover phải nền xanh, chữ trắng, không còn border xanh mờ cạnh trên.

## Lệnh chạy sau khi copy source

```bash
cd /var/www/erm-pushsale
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
php artisan db:seed
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

## URL cần test

```text
/admin/marketing/landing-connections
/admin/marketing/landing-approvals
/ld/unit-admin/ket-noi-landing-website?tid=2
```

## Lưu ý asset

Không xóa các thư mục asset legacy này khi copy source vào git/server:

```text
public/vendor/adminlte2
public/vendor/font-awesome
public/vendor/bootstrap
```

Các icon action và giao diện Pushsale cũ vẫn đang phụ thuộc FontAwesome/AdminLTE legacy.


---

## README_v121_landing_save_500_guard

# v121 — Landing connection save 500 guard

Mục tiêu: sửa lỗi POST `/admin/marketing/landing-connections/records` rơi ra trang 500 khi tạo nguồn landing theo luồng mới.

## Thay đổi chính

- Form tạo/sửa nguồn landing không duyệt trực tiếp nữa. Dữ liệu được tạo ở trạng thái chờ duyệt.
- Sản phẩm/gói sản phẩm và ngân sách được gắn ở menu duyệt `/admin/marketing/landing-approvals`.
- Controller `LandingConnectionsController` đã bọc lỗi DB/runtime để không còn đẩy người dùng ra trang 500; lỗi được ghi log Laravel và trả về form bằng flash error.
- Migration + `erm:repair-schema-contract` bổ sung/repair các cột legacy của `marketing_sources` và `landing_connections`, đặc biệt cho phép `marketing_sources.product_id` nullable để lưu nguồn chờ duyệt.
- Vá CSS hover cuối cùng cho menu cấp 2 không có submenu cấp 3.

## Chạy sau khi deploy

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=80 --json
```

## Test tay

1. Vào `/admin/marketing/landing-connections`.
2. Bấm `+ Thêm`.
3. Điền tên nguồn, URL landing, kênh quảng cáo, sale ưu tiên nếu cần.
4. Lưu: phải quay lại danh sách, không được rơi ra `/records` 500.
5. Vào `/admin/marketing/landing-approvals` để gắn sản phẩm/gói và ngân sách rồi duyệt.


---

## README_v122_landing_backend_reset

# v122 - Landing connection backend reset

## Mục tiêu

Luồng cũ tạo `marketing_sources` ngay khi Marketing tạo kết nối landing. Điều này sai với business mới vì thời điểm tạo landing chưa có sản phẩm/gói sản phẩm và ngân sách. Trên staging nó gây lỗi khi `marketing_sources.product_id` hoặc các cột legacy chưa khớp schema.

v122 tách rõ 2 bước:

1. **Menu 2.4.1 - Kết nối landing / nguồn dữ liệu**
   - Chỉ tạo `landing_connections`, `landing_connection_sources`, `landing_connection_sales`.
   - Không tạo campaign legacy `marketing_sources` khi chưa duyệt.
   - Không yêu cầu sản phẩm/gói sản phẩm.
   - URL API là trường read-only, tự sinh sau khi lưu.

2. **Menu 2.4.3 - Duyệt kết nối dữ liệu**
   - Người duyệt chọn sản phẩm/gói sản phẩm.
   - Nhập ngân sách tổng hoặc ngân sách/ngày nếu cần.
   - Khi duyệt mới đồng bộ sang `marketing_sources` để các luồng báo cáo/lead cũ vẫn dùng được.

## Các file chính đã sửa

- `app/Services/Marketing/LandingConnectionManager.php`
- `app/Http/Controllers/Admin/Marketing/LandingConnectionsController.php`
- `app/Http/Controllers/Admin/LandingApprovalController.php`
- `routes/web.php`
- `routes/pushsale_pages.php`
- `resources/js/pages/Pushsale/Pages/Marketing/LandingConnectionsPage.jsx`
- `resources/css/pushsale-landing-connections.css`
- `app/Support/RuntimeSchemaContract.php`
- `database/migrations/2026_07_25_122000_repair_landing_pending_source_contract.php`

## Test sau deploy

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

Test browser:

- `/admin/marketing/landing-connections`
- tạo mới landing, chỉ điền tên + URL nguồn dữ liệu + kênh quảng cáo + sale nếu cần
- `/admin/marketing/landing-approvals`
- duyệt landing, chọn sản phẩm/gói và ngân sách

## Smoke test v122

Route smoke chỉ fail command khi có lỗi nghiêm trọng: exception/PHP error/HTTP 5xx. Các 403/404 được coi là warning để không làm nhiễu khi mục tiêu là quét lỗi 500 của route view.


---

## README_v125_manual_distribution_landing_fix

# v125 — Manual data distribution + landing pending flow fix

## Business contract

### 1. Manual data distribution `/admin/leads`
- The page is not a static UI. The submit button posts to `/admin/leads/distribute`.
- Selected product quantities are distributed round-robin across selected sales.
- `operation_policy` is applied after order creation:
  - `keep`: keep default operation stage from lead/order factory.
  - `new_customer`: force `operation_stage = new_customer`.
  - `follow_up`: force `operation_stage = call_2`.
- Product sale permissions are enforced through `products.sale_user_ids` / `available_sale`.

### 2. Landing connection `/admin/marketing/landing-connections`
- Creating a connection does **not** require product/package selection.
- The create/update form only creates pending landing connection records:
  - `landing_connections`
  - `landing_connection_sources`
  - `landing_connection_sales`
- It must not create/sync `marketing_sources` until approval.
- Approval and product/package/budget binding live in `/admin/marketing/landing-approvals`.

## Technical fixes
- Replaced `Collection::filter('is_array')` with explicit closures because Laravel passes value and key to callbacks, causing `ArgumentCountError: is_array() expects exactly 1 argument, 2 given`.
- Manual distribution frontend now uses `router.post` with explicit payload and loading state instead of relying on persistent `useForm.transform` side effects.
- Sidebar second-level leaf hover now has a React hover state + inline style fallback, not only CSS cascade, to bypass legacy AdminLTE overrides.
- Added generic route transition overlay to reduce perceived CSS/layout flicker during Inertia navigation.

## Deploy smoke
```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```


---

## README_v126_landing_approval_ui_money

# v126 — Landing connections table + approval money UI

## Scope

- Polish menu 2.4.1 landing connection table to match Pushsale source layout more closely.
- Keep the new business flow: source creation first, product/package and budget approval later.
- Polish menu 2.4.3 approval table and dialog.

## Business contract

1. `/admin/marketing/landing-connections` creates or edits landing/website/facebook source records.
2. Source creation does not require product/package.
3. Source creation does not publish or sync `marketing_sources`.
4. `/admin/marketing/landing-approvals` attaches product/package and budget.
5. Approval syncs to legacy `marketing_sources` for existing lead/report compatibility.
6. The table checkboxes `Nhập TC` and `Duyệt` are display values. `Duyệt` in source creation means "request approval"; final approval remains on menu 2.4.3.

## UI contract

- Marketing column is wider; source column is tighter.
- Secondary source/channel text uses Pushsale purple tone.
- URL connection V2 field is borderless until focus/double-click.
- `Nhập TC` and `Duyệt` columns are narrow content columns.
- Approval budget input is a VNĐ text field and submits a sanitized integer.
- Approval page filter has explicit search button.

## Deployment

Run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan erm:repair-schema-contract
pnpm build
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```


---

## README_v127_interaction_css_dialog_fix

# v127 — Interaction, CSS hover and dialog contract fix

## Scope
- Manual data distribution `/admin/leads` now submits via an explicit `router.post()` action from the button click, with loading/success/error toast feedback. This avoids the old symptom where selecting quantities and pressing "Phân bổ data" appeared to do nothing.
- Landing approval `/admin/marketing/landing-approvals` now submits the approval dialog via an explicit `router.post()` payload instead of relying on `useForm.transform().post()` chaining. It validates selected products client-side, shows toast errors, and keeps the dialog state stable.
- Approval filter header is aligned in one compact row with the page title and consistent top/bottom spacing.
- Product taxonomy popups now have a visible close action and are forced to a full-window Pushsale popup layout instead of collapsing to a narrow left column.
- Sidebar second-level menu hover is fixed at both React inline-style level and the final canonical CSS layer. Leaf menu items no longer keep the white background / faint blue top border inherited from legacy AdminLTE focus rules.

## Contract
- New React dialogs must use `PushsaleDialog`.
- New select controls should use `PushsaleSelect` / `PushsaleMultiSelect`.
- Page-level Pushsale CSS must be scoped by page/root class. Cross-page fixes belong in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Do not create new root README version files. Keep version notes under `docs/context-history/`.


---

## README_v128_landing_webhook_mapping_audit

# v128 — Landing webhook mapping audit & upsell fallback

## Mục tiêu

Flow tạo kết nối landing vẫn đơn giản: Marketing khai báo URL landing chính và URL upsale/thank-you. Sản phẩm/gói sản phẩm có thể được cấu hình khi duyệt, nhưng webhook không được rơi dữ liệu nếu payload LadiPage không map được vào catalog.

## Contract nghiệp vụ

1. Webhook là request độc lập, không có session server-side giữa trang landing và trang cám ơn.
2. Ưu tiên match bằng `ps_flow`, `saleops_session`, `session_id`, `saleops_client_ref`.
3. Nếu trang upsale không gửi flow token nhưng có `phone`, `landing_phone` hoặc field điện thoại lấy từ URL, hệ thống fallback tìm session/order gần nhất trong `LEAD_PHONE_MERGE_WINDOW_MINUTES`.
4. Fallback theo SĐT chỉ auto-append khi order còn cửa sổ upsell hold; ngoài cửa sổ hoặc không đủ mapping sẽ đưa vào review, không tạo đơn rác.
5. Payload nào nhận được cũng phải lưu đủ audit map:
   - field khách hàng,
   - field match phiên,
   - field nghi là sản phẩm/combo/upsale,
   - item đã map vào catalog,
   - field sản phẩm chưa map.
6. Nếu không map được sản phẩm/gói, hệ thống tạo `lead_ingestions.status=needs_review`, giữ raw payload và `_landing_webhook_mapping`, không trả hard 422/500.

## File chính

- `app/Services/Marketing/LandingConnectionPayloadMapper.php`
- `app/Http/Controllers/Api/V1/LandingConnectionSubmissionController.php`
- `app/Http/Controllers/Admin/LeadsLogController.php`
- `config/saleops.php`
- `tests/Feature/Leads/LandingConnectionFlowTest.php`

## Test liên quan

- `test_upsell_without_flow_token_can_fallback_to_recent_phone_session_and_merge`
- `test_unmapped_landing_payload_is_kept_for_review_with_full_field_mapping_report`


---

## README_v129_sidebar_runtime_error_fix

# v129 Sidebar runtime error fix

## Mục tiêu

Fix lỗi runtime `childActive is not defined` khi mở menu/sidebar trên các trang AdminLTE/Inertia.

## Nguyên nhân

Trong `ThirdLevelFlyout` của `resources/js/components/layout/AppSidebar.jsx`, JSX của menu cấp 3 vô tình dùng biến `childActive`, `hoverSecondKey`, `key` vốn chỉ tồn tại trong scope render menu cấp 2. JavaScript không bắt lỗi ở build vì đây là free variable runtime, nên khi mở menu mới văng lỗi ứng dụng.

## Sửa đổi

- Thay đoạn render title trong flyout cấp 3 về scope-local, chỉ dùng `active` đã được khai báo trong callback map.
- Không đổi contract CSS/menu khác ở bản này để tránh tạo thêm override không cần thiết.

## Test cần chạy

```bash
pnpm build
php artisan optimize:clear
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```

## Test tay

- Vào `/admin/products/import`.
- Click hamburger mở menu.
- Hover/click các menu có submenu cấp 3 và menu cấp 2 không có submenu cấp 3.
- Không được còn lỗi `childActive is not defined`.


---

## README_v130_landing_manual_flags_contract

# v130 – Landing source manual/approval flags contract

## Mục tiêu

Ổn định lại trang 2.4.1 `Kết nối dữ liệu` theo flow mới:

1. Trang 2.4.1 chỉ tạo/sửa nguồn landing và nguồn upsell.
2. Không gắn sản phẩm/gói ở dialog tạo nguồn.
3. Nguồn landing luôn ở chế độ `Nhập thủ công`.
4. Nguồn landing luôn phải qua menu duyệt trước khi chạy thật.
5. Menu duyệt mới gắn sản phẩm/gói, ngân sách và sync legacy `marketing_sources`.

## Fix chính

- Dialog tạo/sửa không gửi `products` nữa, tránh validate flow cũ kiểu `sources.1.name` khi có upsell URL.
- Backend force `manual_import=true` và `metadata.request_approval=true`.
- Thêm endpoint:

```http
PATCH /admin/marketing/landing-connections/records/{record}/flags
```

Endpoint này dùng cho checkbox trong bảng và luôn bật lại 2 cờ bắt buộc: nhập thủ công + yêu cầu duyệt.

## Test liên quan

- `test_landing_connection_source_update_does_not_require_product_mapping_for_upsell_source`
- `test_landing_connection_flags_endpoint_forces_manual_import_and_approval_request`


---

## README_v131_header_sidebar_contract_fix

# v131 — Header alignment + sidebar hover runtime contract

## Scope
- Fix `2.4.3 Duyệt kết nối dữ liệu` header: title and filters share one row, search button no longer drops to a second row.
- Fix `2.1 Marketing dashboard` top title spacing: title starts near the left page gutter; filters stay compact and two-row.
- Fix AdminLTE sidebar second-level hover once at runtime and canonical CSS level.

## Contract
- `resources/css/pushsale-adminlte-canonical-contract.css` remains the last global CSS file.
- Sidebar second-level hover is now enforced by both:
  - canonical CSS selectors, and
  - imperative runtime hover guard in `resources/js/components/layout/AppSidebar.jsx` using `style.setProperty(..., 'important')`.
- Do not add new page-specific files to fix sidebar hover. Change `AppSidebar.jsx` or the canonical contract only.

## Test points
- `/admin/marketing/landing-approvals`
- `/admin/marketing/dashboard`
- any page with sidebar open, hover second-level menu items that have no third-level flyout.


---

## README_v132_marketing_leader_filter_sidebar_fix

# v132 Marketing leader report filter + sidebar hover fix

- Rebuilt `/ld/marketing/thong-ke-truong-nhom` header/filter into one stable Pushsale-style header: title left, primary filters right, advanced filters on one aligned row.
- Removed redundant info/help icon and header collapse icon from this report.
- Added final sidebar hover contract in `pushsale-adminlte-canonical-contract.css` and DOM pointerover guard in `AppSidebar.jsx` so second-level items without third-level submenu do not show white hover overlays or thin blue top borders.

Run:

```bash
php artisan optimize:clear
pnpm build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```


---

## README_v133_global_filter_sidebar_header_contract

# v133 — Global filter/sidebar/header contract

## Scope

This version consolidates recurring UI fixes into the final canonical CSS layer instead of adding page-specific override files.

## Changes

- Restored visible/clickable native date inputs for date range filters.
- Standardized select/filter arrows across native `<select>` and React `PushsaleSelect`.
- Re-aligned rebuilt AdminLTE page titles close to the left edge.
- Re-aligned combo/team-style page headers through the canonical contract.
- Strengthened sidebar second-level hover rules for menu items with and without third-level submenu.
- Removed the `active` early-return in `AppSidebar.forceSecondLevelHover()` so runtime hover styling can still neutralize legacy white hover layers.

## Contract

- New global UI fixes go in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Do not add another one-off sidebar hover or generic filter CSS file.
- Page-specific CSS may still exist, but must not override the canonical sidebar/select/date/header rules globally.


---

## README_v134_combo_operation_teams_contract

# v134 — combo dialog fit, operation category backend, team title alignment

## Scope

- Fix combo create/edit dialog table overflow inside the modal frame.
- Convert menu 1.8.1 operation results from read-only fake inputs into persisted business settings.
- Add backend storage for operation result labels and close-order flags.
- Make `/admin/teams` and PageFrame titles start near the left page gutter instead of floating deep in the header.

## Business contract

- `operation_categories` still controls operation stages such as gọi lần 1, kho số, duration.
- `operation_workflows` still controls automatic stage transitions after a result.
- New `operation_result_settings` stores result labels and whether a result triggers the real order-closing flow.
- If the table is missing, backend falls back to the hard-coded enum: only `closed_success` closes the order.

## CSS contract

- Final global corrections remain in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Do not create new ad-hoc page CSS for combo dialog fit, PageFrame title alignment, or sidebar hover unless the canonical contract cannot express the rule.


---

## README_v135_taxonomy_modal_contract

# v135 — Product taxonomy modal contract fix

Scope:
- `/admin/products` product taxonomy dialogs: categories, attributes, attribute values.

Fix:
- The taxonomy modal is forced to a full viewport Pushsale popup from the canonical-last CSS layer.
- The dialog body uses a two-pane grid: list table on the left and update form on the right.
- The search/filter row is constrained to the popup width and no longer collapses to a narrow left column.
- The malformed duplicate selector in `pushsale-product-taxonomy-dialog-contract.css` was corrected.

Contract:
- Future taxonomy modal layout changes should be made in `resources/css/pushsale-adminlte-canonical-contract.css` and `resources/js/pages/Admin/Products/Index.jsx` only.


---

## README_v136_dialog_sidebar_product_pancake_contract

# v136 — Dialog/sidebar/product/Pancake cleanup

## Mục tiêu
- Chốt lại lỗi hover menu cấp 2 bằng runtime style + canonical CSS, không phụ thuộc legacy cascade.
- Fit lại cột sản phẩm/số lượng/đơn giá dùng chung cho Sale/Kho/Kế toán/Hồ sơ khách hàng.
- Đồng bộ dialog tin nhắn nội bộ/Pancake theo một shell chung, tránh duplicate close button và UI lệch.
- Giảm spam toast realtime/chia số, chỉ giữ toast mới nhất trong một khoảng ngắn.
- Thêm `php artisan pancake:doctor` để kiểm tra setup Pancake trước khi test live.

## File chính
- `resources/js/components/layout/AppSidebar.jsx`
- `resources/css/pushsale-adminlte-canonical-contract.css`
- `resources/js/components/customers/pushsale/PushsaleCustomerDialogs.jsx`
- `resources/js/hooks/useRealtimeNotifications.js`
- `resources/js/pages/Admin/DataDistribution/Index.jsx`
- `app/Console/Commands/PancakeDoctorCommand.php`

## Test gợi ý
```bash
php artisan optimize:clear
pnpm build
php artisan pancake:doctor --json
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
```


---

## README_v141_canonical_header_table_landing_delete_contract

# V141 – Canonical header/filter/table + landing/delete contract

## Why
Multiple pages were still rendering their own page headers independently, so title/filter/action spacing drifted between menus. This version introduces a last-loaded canonical CSS contract and fixes the concrete broken screens reported from staging.

## Header/filter contract
- Canonicalized compact header spacing in `resources/css/pushsale-adminlte-canonical-contract.css`.
- Pattern: title on the left, filters/actions on the right, compact second-row filters only when the page needs advanced filters.
- Removed double-border/heavy-shadow look from landing connection filters and shared controls.
- Applied targeted fixes for:
  - `/admin/sales/rankings`
  - `/admin/customers/care-campaigns`
  - `/admin/customers/reports/multidimensional`
  - `/admin/customers/reports/spending`
  - `/admin/marketing/landing-connections`
  - `/admin/customer-management`
  - `/ld/marketing/thong-ke-truong-nhom`

## Sales ranking fixes
- Hide redundant info/question button.
- Advanced filter toggle now works for the original Pushsale `btnToggleSummary` button.
- Leader/team filters get compact spacing.
- Table gutter is reduced.
- Staging fallback rows now use real sales users even for platform admin contexts where TenantScope would otherwise make the ranking empty.

## Landing connection fixes
- Saving an existing landing source without product edits no longer drops approved product mappings and no longer attempts to publish a legacy marketing source without product mapping.
- Table checkboxes now support both checking and unchecking `manual_import` and `request_approval`.
- Filter select style is normalized to the same border/shadow contract.

## Table cell fixes
- Product/quantity/price rows are constrained so upsell labels/icons do not visually spill into the result column.
- Money columns are right-aligned with reduced right padding for a tighter Pushsale-like numeric column.

## Delete confirmation
- Added `resources/js/components/ui/ConfirmActionDialog.jsx` as the shared confirmation dialog.
- Replaced browser `window.confirm` for dynamic Pushsale business pages, products, and landing connections.
- Added EN/VN translation keys for delete confirmation messages.

## Files touched
- `resources/css/pushsale-adminlte-canonical-contract.css`
- `resources/js/components/ui/ConfirmActionDialog.jsx`
- `resources/js/pages/Pushsale/BusinessPage.jsx`
- `resources/js/pages/Admin/Products/Index.jsx`
- `resources/js/pages/Pushsale/Pages/Marketing/LandingConnectionsPage.jsx`
- `app/Http/Controllers/Admin/Marketing/LandingConnectionsController.php`
- `app/Services/Marketing/LandingConnectionManager.php`
- `app/Services/Pushsale/PushsalePageService.php`
- `resources/js/i18n/locales/vi.js`
- `resources/js/i18n/locales/en.js`


---

## README_v142_operation_icon_spacing

# V142 – Operation table icon spacing

## Scope
- Sale operation / customer-operation style tables where the sale column shows a delete-data icon and the result column shows a history/refresh icon.

## Fix
- Reserve a right-side action area inside the sale column so the trash icon no longer overlaps the assignee name/date.
- Reserve a right-side action area inside the result column so the select box no longer runs under the action icon.
- Keep current layout logic but make the action placement visually cleaner and more consistent.

## Files touched
- `resources/css/pushsale-operation-columns-contract.css`
- `resources/css/pushsale-adminlte-canonical-contract.css`
- `resources/css/pushsale.css`


---

## V143 – Sale operation dialog/table consolidation

### Scope
- Sale operation table / customer profile operation table
- `Nhập đơn mới` / chốt đơn dialog
- Customer internal/Pancake message dialog
- Product breakdown + money/order cells

### Changes
- Enlarged sale order dialog and mapped current `ps-sale-dialog` classes to the final modal CSS contract.
- Rebalanced order dialog left/right panels and widened product-fee table so content no longer collapses.
- Added explanatory text to `TN cần`; saved notes are visible in operation history / customer profile history.
- Aligned order-code history icon below code/empty state.
- Added `ps-order-products-cell` to the sale operation product column and normalized product/qty/price grid.
- Simplified customer messages dialog spacing and removed the heavy nested-border look.
- Consolidated context docs into this release note file; future version notes should be appended here instead of creating a new README per change.


---

## V144 – Canonical mã đơn flags + product split display

### Scope
- Sale operation table
- Customer profile table
- Shared operation tables used by warehouse/accounting/customer-operation views

### Changes
- Standardized `Mã đơn` cells into one shared vertical stack: order code, history/action icon, then compact flags.
- Added compact flags for returning customer (heart), duplicate phone (copy/clone), and upsale/pending upsale.
- Moved the textual `UPSALE` badge out of the product column and represented upsale in the code-cell flags instead.
- Reworked `OrderProductsBreakdown` so product rows can be split into `Đơn chính` and `Upsale` sections when an order has upsell lines.
- If an entire row is a supplemental/upsale order, the product block is marked as an `Upsale` section.
- Widened and normalized the shared product/quantity/unit-price column so prices no longer collapse into only the currency symbol.

### Files touched
- `resources/js/components/operations/OrderLineBreakdown.jsx`
- `resources/js/components/operations/pushsale/SaleWorkspaceTable.jsx`
- `resources/js/components/operations/OperationOrderTable.jsx`
- `resources/js/components/operations/WarehouseOrderTable.jsx`
- `resources/js/components/operations/AccountingReconTable.jsx`
- `resources/js/pages/Sales/CustomerProfile.jsx`
- `resources/css/pushsale-adminlte-canonical-contract.css`
- `resources/css/pushsale-sale-workspace.css`
- `resources/css/pushsale-customer-operation-money-contract.css`
- `resources/css/pushsale.css`


## V145 – Facebook menu/pages, i18n sweep, and header/filter cleanup

### Scope
- Menu group `2.5 Kết nối facebook`
- `/admin/marketing/landing-connections`
- `/admin/marketing/website-connections`
- `/admin/customer-management`
- Shared Pushsale select component

### Changes
- Menu `2.5.1` now opens the same Website connection page as `2.4.2`, with the Website tab/query selected.
- Added real routes/backend/controller/model/migration for:
  - `2.5.2` Facebook Fanpage sync: `/admin/marketing/facebook/connect`
  - `2.5.3` Facebook post list: `/admin/marketing/facebook/posts`
- Added UI pages matching the provided Pushsale Facebook sync and post-list references.
- Added backend-safe demo sync actions so the pages have usable data before a real Meta OAuth app is wired.
- Added `facebook_post_mappings` table for post/source mapping.
- Expanded EN/VI translations for landing connections, Customer 360, Facebook connection/post pages, and shared select empty/search labels.
- Normalized Landing/Website connection filter layout: compact right-side filter group, shorter keyword input, no thick shadow/border on select controls.
- Re-strengthened Customer 360 single-line header layout.
- Shared `PushsaleSelect` now uses the runtime translation dictionary for its search/empty text.

### Files touched
- `app/Http/Controllers/Admin/Marketing/FacebookConnectController.php`
- `app/Models/Pushsale/FacebookPostMapping.php`
- `database/migrations/2026_07_26_090000_create_facebook_post_mappings_table.php`
- `routes/pushsale_pages.php`
- `config/pushsale_navigation.php`
- `config/pushsale_routes.php`
- `app/Services/NavigationService.php`
- `resources/js/pages/Pushsale/Pages/Marketing/FacebookConnectPage.jsx`
- `resources/js/pages/Pushsale/Pages/Marketing/FacebookPostsPage.jsx`
- `resources/js/pages/Pushsale/Pages/Marketing/LandingConnectionsPage.jsx`
- `resources/js/pages/Customers/Management.jsx`
- `resources/js/components/pushsale/PushsaleSelect.jsx`
- `resources/js/i18n/locales/vi/pages.js`
- `resources/js/i18n/locales/en/pages.js`
- `resources/css/pushsale-adminlte-canonical-contract.css`
- `resources/css/pushsale.css`
- `lang/en/pushsale_navigation.php`
- `lang/en/facebook.php`
- `lang/vi/facebook.php`


## V146 – Wider order update dialog + Sales ranking rebuilt

- Widened the warehouse/order update dialog (`ps-wh-dialog wide`) so the product editor fits desktop width without horizontal scrolling.
- Removed the fixed `min-width: 800px` behavior inside the update-order product table for the wide modal and assigned stable column widths.
- Rebuilt menu `4.3` `/admin/sales/rankings` to use the same Pushsale ranking chrome/table/podium as marketing rankings.
- Added `SalesLeaderboardService` so the Sales ranking page is calculated from real sales users, sale-assigned contacts, closed orders, product quantity, discounts, COD, fees and final revenue.
- Kept rows visible even when demo orders have low/zero revenue, because the ranking is generated from real sales users first, then sorted by revenue.
