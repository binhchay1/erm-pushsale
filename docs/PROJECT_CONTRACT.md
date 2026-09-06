# ERM Pushsale Project Contract

Living contract. Agent conventions: root `AGENTS.md`. Docs index: `docs/README.md`.

## 0. Sources of truth

| Concern | Location |
| --- | --- |
| Menu tree / codes | `config/pushsale_navigation.php` |
| Page schemas | `config/pushsale_pages.php` |
| Admin menu routes | `routes/admin/{domain}.php` (required from `web.php`) |
| Role workspace routes | `routes/roles/{role}.php` |
| Legacy 301 | `routes/legacy.php` |
| Extra reports | `config/pushsale_report_routes.php` + `routes/admin/reports.php` |
| CSS load order | `resources/js/lib/pushsaleStyleRegistry.js` |
| Page header | `components/layout/PageHeader.jsx` + `pushsale-page-header-contract.css` |
| Page frame | `PushsalePageShell.jsx` + `pushsale-page-frame-contract.css` |
| Sidebar + L3 | `AppSidebar.jsx` + `usePushsaleSidebarMenu.js` + `pushsale-sidebar-canonical-contract.css` |
| Ops table cells | `components/operations/cells/OpsTableCells.jsx` + `OrderLineBreakdown.jsx` |
| Orphan CSS | `resources/css/_archive/` (not loaded) |
| Multi-shop | `shops` + `BelongsToShop` / `ShopScope` + `SetCurrentShop` + `ShopSwitcher` |

Do **not** create `CONTEXT_HANDOFF_V*` / versioned UI docs / HTML templates under `docs/`. Update this file, `AGENTS.md`, or the slim set in `docs/README.md`.

## 0a. Multi-shop (Company → Shop)

- **Company** = tenant (`company_id`, `TenantManager`, `BelongsToTenant`).
- **Shop** = đơn vị vận hành trong company (`shops`, `shop_id` trên orders/leads/warehouses/products/teams/marketing_sources/landing_connections).
- Middleware stack: `auth` → `tenant` → `shop` → `permissions`. Session key `current_shop_id`.
- `ShopSwitcher` trong `AppHeader`; đổi shop → `POST /shop/current`.
- Trang so sánh: `/admin/shops/overview` (bypass `ShopScope`). CRUD: `/admin/shops` (menu 1.1.3 / 1.1.4).
- **TLC** = closed contacts / contacts. **TLH** = appointment orders (`next_operation_at`) / contacts.
- Khi `current_shop` đang set: báo cáo/dashboard **ép live** (`ReportFactReader::supports` = false) vì `report_daily_*` chưa có `shop_id`.
- Tác nghiệp sale / kho / KT / hồ sơ KH: query `Order`/`Lead` qua ShopScope → chỉ data shop đang chọn (đúng form 2B).
- **NetShip ↔ Shop:** NetShip không nhận `shops.id`. Luồng đúng: Shop → Order/Warehouse (`shop_id`) → `ShippingAddressHelper::pickupForOrder` lấy địa chỉ kho → NetShip proxy. `warehouse.shipping_account_settings.shop_id` là ID shop của **hãng** (GHN…), không phải org Shop.
- Không shop-scope: shipping partners, work shifts, phone blacklist, AppSetting, users org chart.
- `EcommerceShopConnection.shop_id` / Pancake = ID sàn ngoài, **không** phải org Shop.

## 0b. Route & menu naming

- URL / route name / controller / React page: business English (`/admin/hr/work-shifts`), never menu number or prompt version.
- Menu code is **data only**: `protected $pageCode = '1.2.3'`, `activeMenuCode`, `data-page-code`.
- Legacy `/ld/...` → 301 in `routes/legacy.php` only.

## 1. Kiến trúc giao diện

Tất cả trang admin phải đi qua `AppLayout`. Trang nghiệp vụ dùng:

- `resources/js/components/layout/PageHeader.jsx`: header duy nhất của trang (title + filters/actions + advanced). Nội dung được portal lên `PageHeaderOutlet` trong `AppLayout` nên không thể có 2 header cùng lúc.
- `resources/js/components/layout/PushsalePageShell.jsx`: bọc `PageHeader` + notice + toolbar + body.
- DOM header cố định theo mẫu Pushsale: `.m-header-wrap.ps-page-header > .m-header.ps-page-header__row`, filter nâng cao nằm ở `.ps-page-header__advanced.box-body` **ngoài** `.m-header`.
- `data-page-code` = mã menu Pushsale khi có (vd. `1.2.1`, `2.1`), truyền qua prop `pageCode`.
- Button submit đặt trong `actions`/`filters` phải trỏ `form="<id form>"` vì nó bị portal ra khỏi `<form>`.

Không viết mỗi trang một bộ header/filter riêng. Thiếu use case → mở rộng `PageHeader`/shell.

## 2. CSS contract

Thứ tự CSS runtime nằm ở `resources/js/lib/pushsaleStyleRegistry.js`.

- Vendor legacy: `/public/vendor/adminlte2`, `/public/vendor/font-awesome`.
- CSS page scoped: `resources/css/pushsale-*.css` **chỉ khi đã đăng ký registry**.
- Thủ kho tác nghiệp (5.1): `pushsale-warehouse-operations-contract.css` + `WarehouseOrderTable` (`variant=warehouse`).
- Kế toán tác nghiệp (6.1): cùng bảng với `variant="accounting"` (icon retweet, không clipboard tách đơn).
- Sale tác nghiệp (4.1): `pushsale-sale-operations-contract.css` + `SaleWorkspaceTable` + shared `OpsTableCells`.
  - **Tin nhắn** = `customer_note` từ landing — chỉ hiển thị.
  - **TN cần** = `sale_operation_note` — `textarea.txt-mof`; chat float trái + save phải phía trên textarea; focus overlay như Pushsale.
  - Sidebar tokens: Arial, `#6C7D8B` / `#007BFF` / L3 `#0057B4`; slide `.3s ease-in-out`, accordion `.5s`, flyout `.5s`.
  - Webhook landing: **không** lưu URL trang/tracking vào `order_items.product_name`.
  - Date range: `DateRangeFilter` single-button (`ps-date-range-control`).
  - **Sale visibility (mặc định, chưa đụng permission matrix cấu hình):** NV chỉ thấy/thao tác đơn `sale_user_id = mình`; team lead / Supervisor theo team + `manager_user_id` (`ReportScopeResolver::allowedSaleIds` + `SalesVisibilityScope`); Head = toàn bộ sale trong **company tenant**. List workspace + mutate dùng cùng scope.
  - **Order interaction lock:** soft-lease theo `order_id` (TTL 90s, heartbeat ~25s) xuyên Sale / Thủ kho / Kế toán. Acquire khi mở dialog mutate; mutate gửi `interaction_lock_token`; 423 nếu bị giữ bởi user khác. Live badge qua Echo channel `company.{id}.order-locks` + poll fallback. WH/KT listing vẫn tenant-wide (không phân cấp NV/lead).
- **i18n**: chuỗi UI qua `useT()` / `__()` + locale files.
- Hồ sơ khách hàng: `pushsale-customer-profile-contract.css` + shared product/money/flag cells.
  - SĐT / tim đỏ / trùng mở `DuplicatePhoneOrdersDialog` (tim = đơn đã chốt). Admin xóa trùng bằng thùng rác; sale thường không xóa.
  - Phân bổ lại: dialog chọn sale + lọc khóa tài khoản / tắt nhận data.
  - Vùng bảng ~5 dòng cố định, 20 khách/trang, cuộn chuột trong bảng.
- Cuối cascade (cố định):
  1. `pushsale-unified-page-shell-contract.css`
  2. `pushsale-adminlte-canonical-contract.css`
  3. `pushsale-page-frame-contract.css`
  4. `pushsale-page-header-contract.css`
  5. `pushsale-sidebar-canonical-contract.css` (**absolute last**)

Quy tắc:

- Selector global sidebar chỉ ở `pushsale-sidebar-canonical-contract.css` (+ phần chrome liên quan trong adminlte-canonical nếu cần).
- Style header trang (`.m-header-wrap` / `.ps-page-header`) chỉ ở `pushsale-page-header-contract.css`: có `box-shadow`, **không** `border-bottom`, spacing title ↔ filter thống nhất, sticky khi scroll. Page CSS không được set lại 2 thuộc tính này.
- Page CSS scope bằng class trang; không đụng `.main-sidebar` / `.ul2` / navbar.
- Không thêm file `pushsale-sidebar-*` mới để vá hover.
- File không load → `resources/css/_archive/`.

## 3. Luồng Landing Connection mới

Menu 2.4.1 chỉ tạo nguồn landing:

- loại kết nối,
- cấu hình chia số,
- tên nguồn,
- URL landing,
- kênh quảng cáo,
- upsale URL,
- sale ưu tiên,
- nhập thủ công.

Không chọn sản phẩm ở bước tạo. Bước duyệt ở `/admin/marketing/landing-approvals` mới gắn sản phẩm/gói và ngân sách rồi sync sang bảng legacy `marketing_sources` để báo cáo/lead flow cũ vẫn đọc được.

## 4. Backend rules

- Controller chỉ validate request và gọi service.
- Service chịu trách nhiệm transaction/business flow.
- Model không chứa logic validate business phức tạp.
- Runtime repair chỉ vá schema không phá dữ liệu.
- Không để route view trả 500; lỗi business phải quay lại form với validation/flash.

## 5. Test tối thiểu sau mỗi lần sửa

```bash
php artisan erm:test-all --route-smoke --smoke-limit=30 --json
php artisan erm:test-all --landing-flow --flow --json
```

Route smoke chỉ fail khi có lỗi nặng như 500/exception/PHP error. 403/404 được tính warning để log không nhiễu.

## Deploy permission contract

- `public/build` is generated output. It must stay writable by the deploy user before `pnpm build`.
- Use `deploy/fix-build-permissions.sh` before Vite build in every deploy path.
- Do not run a manual root-owned build without restoring owner to `deploy:www-data`.

## 6. Manual data distribution contract

Menu 1.5 `/admin/leads` is a real manual allocation workflow, not a demo table:

- UI posts explicit payload to `/admin/leads/distribute`.
- Backend service `DataDistributionService` chooses pending `lead_ingestions` by product/filter.
- `ManualLeadAllocationService` creates sale orders and attaches phone locks to avoid two sales calling the same customer.
- Product permission is enforced before allocation.
- Any new filter must be added to both `normalizeFilters()` and the frontend payload; do not create visual-only filters.

## 7. Sidebar/menu hover contract

Source of truth (only):

- `resources/js/hooks/usePushsaleSidebarMenu.js` — accordion, active key, L2 hover, L3 flyout, timers.
- `resources/js/components/layout/AppSidebar.jsx` — markup only; **no** runtime `<style>`, `style.setProperty`, or `data-ps-second-hover` hacks.
- `resources/css/pushsale-sidebar-canonical-contract.css` — visual contract, loaded last.

Visual rules:

- active: blue background, white text;
- hover: blue background, white text;
- no top/bottom blue border artifacts;
- L3 flyout uses `.pushsale-third-menu` + `.is-visible`.

Do **not** add another sidebar override CSS file. Page CSS must not override `.pushsale-main-sidebar .ul2 > li.li2:hover`.

## v126 landing approval UI contract

- Menu 2.4.1 only creates/edits landing source information. Product/package and budget are not part of this form.
- `request_approval` is a UI/business flag for the source table `Duyệt` checkbox. It does not replace final approval.
- Menu 2.4.3 approves landing source, optionally attaches product/package + budget, and syncs legacy `marketing_sources`.
- **Product/package is optional at approval** — missing product must not block approve or cause 500. Webhook still accepts leads (mapping review if unmapped).
- Money inputs in approval UI must display VNĐ format and submit sanitized integer values to Laravel.
- Pushsale table URL fields should be borderless by default; focus/double-click may show border/shadow for copy.
- Product column on 2.4.1 must reflect connection status (`Chờ duyệt` / `Đã từ chối` / `Chưa gắn sản phẩm` / product names) — never keep showing “Chờ duyệt gắn sản phẩm” after reject/approve.


## v127 interaction contract

- Manual data distribution must always show visible feedback: pending toast, success toast, or validation/error toast.
- Landing approval may include product IDs (optional). Do not require product before approve; use a stable explicit payload to backend.
- Taxonomy dialogs are full-window Pushsale-style popups with visible close action.
- Sidebar second-level leaf hover is controlled in canonical CSS + menu hook only; no page CSS may override `.pushsale-main-sidebar .ul2 > li.li2:hover` back to white.


## v128 Landing webhook mapping contract

- Trang tạo kết nối landing không bắt buộc sản phẩm/gói. Duyệt có thể gắn sản phẩm/gói + ngân sách, nhưng webhook vẫn phải nhận và lưu payload dù chưa map được.
- Webhook match theo thứ tự: `ps_flow/saleops_session/session_id/saleops_client_ref` → fallback SĐT trong `LEAD_PHONE_MERGE_WINDOW_MINUTES`.
- Fallback SĐT không được nối nhầm đơn cũ: chỉ auto append khi order còn cửa sổ hold; nếu không đủ điều kiện thì ghi packet review.
- Mọi payload landing phải lưu `_landing_webhook_mapping` trong `lead_ingestions.payload` để thấy đủ field đã nhận, item đã map, và field sản phẩm chưa map.
- Không trả 500/422 chỉ vì chưa map sản phẩm. Trường hợp này trả `202 Accepted`, `mapping_review=true`, lưu `needs_review`.

## v129 Sidebar runtime guard

- `AppSidebar.jsx` không được dùng biến của scope menu cấp 2 trong `ThirdLevelFlyout`.
- Logic hover/flyout nằm ở `usePushsaleSidebarMenu`.
- Khi sửa menu, phải kiểm tra runtime bằng thao tác mở hamburger + hover/click menu cấp 2/cấp 3 trên ít nhất một trang như `/admin/products/import`.

## v130 Landing source flags

- Trang 2.4.1 không được validate/gắn sản phẩm ở form tạo/sửa nguồn dữ liệu.
- `manual_import` (Nhập TC) **không** mặc định bật: tick trong dialog/list mới cho phép nhập data tay vào nguồn đó.
- Form tạo nguồn: checkbox Nhập TC editable, mặc định tắt (giống Pushsale).
- Dropdown nguồn ở nhập data thủ công (`2.6.2`, dialog leads) chỉ liệt kê nguồn eligible: có landing connection + `manual_import=true`, hoặc nguồn legacy không gắn landing.
- `metadata.request_approval` luôn bật; muốn chạy live phải duyệt ở menu duyệt kết nối.
- Checkbox `Duyệt` trong bảng (Admin) = duyệt/hủy duyệt nhanh; bỏ tích phải xóa `rejected_at` để nguồn về lại **Chờ duyệt** trên trang duyệt.
- Xóa kết nối landing: soft-delete LC + tắt `marketing_sources.is_active` (đổi tên `[Đã xóa] …`). Dashboard/báo cáo chỉ lấy `MarketingSource::visibleInReports()`.

### Demo UI workspace

- `php artisan demo:workspace-ui` — seed dữ liệu gắn nhãn `UXDEMO` cho sale / thủ kho / hồ sơ KH / báo cáo Leader (4.6.x).
- `php artisan demo:workspace-ui delete --force` — chỉ xóa bản ghi UXDEMO, không đụng dữ liệu khác.

## v131 UI Contract Addendum
- Page header/frame rhythm: `PushsalePageShell` + `pushsale-page-frame-contract.css` (+ adminlte-canonical cho control chrome). *(Phần header đã chuyển sang `PageHeader` — xem mục “Route, naming và header dùng chung” ở cuối file.)*
- Marketing dashboard and landing approval headers must use the shared shell; no page should add another broad header override file.
- Sidebar hover: hook + `pushsale-sidebar-canonical-contract.css` only.

## v133 UI contract update

- Native selects, React `PushsaleSelect`, date range filters: `pushsale-adminlte-canonical-contract.css`.
- Date range filters must remain clickable native inputs. Do not hide them with `opacity: 0` overlays in later page CSS.
- Native selects must keep the canonical right-side caret. Do not remove appearance/caret per page.
- Second-level sidebar hover must use the same blue background as active menu items, including items without a third-level submenu.
- Rebuilt AdminLTE pages: title left + gutter, primary filters/actions right via `PushsalePageShell`.

## v134 operation/category and modal contract

- Menu `1.8.1` is not demo-only. The left table writes `operation_categories`, the bottom table writes `operation_workflows`, and the right table writes `operation_result_settings`.
- A result with `closes_order = true` calls the same real close-order service used by sale operation, so it must be treated as business configuration.
- Combo modal tables must stay inside the dialog frame; use canonical `.ps-combo-dialog` rules rather than per-dialog inline widths.
- PageFrame title alignment must start from the 14px page gutter; do not center title text inside menu group 1/2 pages unless the original Pushsale HTML does so explicitly.
- **Active sale workflow (feedback):** 6 stages — Khách mới + Gọi lần 2…6 (+ Bỏ qua / Chưa TN). `care_1…3` remain in enum/DB for history but are excluded from `SaleOperationConfigurationService::filterOptions()` / `activeDefinitions()` and workspace tabs. Closing an order sets `skipped`, not care.
- **Active operation results:** Chốt đơn, Không nghe máy, Máy bận, Gọi lại sau, Trùng số, Sai số/Nhầm số, Thuê bao, Suy nghĩ thêm, Không có nhu cầu (`OperationResult::selectableOptions()` / `filterOptions()`). Legacy values still resolve via `tryFromStored`.
- **Closing filter:** chỉ Đã chốt đơn / Chưa chốt đơn (`ClosingStatus::options()`).
- **Date presets** (shared `DateRangeFilter`): Hôm nay, Hôm qua, Tuần này, Tuần trước, Tháng này, Tháng trước, Tùy chỉnh.

## v135 taxonomy modal contract

Product taxonomy popups (`Danh sách phân loại`, `Danh sách thuộc tính sản phẩm`, `Danh sách giá trị thuộc tính`) are full-viewport Pushsale-style dialogs. They must not rely on raw Bootstrap columns for body layout. The canonical layout is:

- full viewport dialog surface,
- compact header with close button,
- search/filter row at the top,
- two-pane body: list table left, edit/update form right.

Change only these files for future taxonomy modal work:

- `resources/js/pages/Admin/Products/Index.jsx`
- `resources/css/pushsale-adminlte-canonical-contract.css`

Do not create another product taxonomy CSS override file.

## v136 Contract bổ sung

### Sidebar hover
- Source of truth: `usePushsaleSidebarMenu` + `pushsale-sidebar-canonical-contract.css` (last in registry).
- Không tạo file CSS mới để sửa hover menu. Không dùng `data-ps-second-hover` / inline style hacks.

### Dialog khách hàng / Pancake
- Dialog React mới dùng `PushsaleDialog`; không nhúng lại nút close cũ trong body.
- Tab nội bộ và Pancake dùng chung `PushsaleCustomerMessagesDialog` để tránh lệch UI.
- Kiểm tra cấu hình Pancake bằng `php artisan pancake:doctor --json` trước khi test chat thật.

### Product breakdown cells
- Cột `Sản phẩm - Số lượng - Đơn giá` dùng `OrderProductsBreakdown`.
- Không viết lại HTML product breakdown theo từng trang; chỉ sửa CSS shared/canonical.

### Toast realtime / phân bổ data
- Notification realtime được coalesce theo id `pushsale-realtime-notification`.
- Phân bổ data dùng toast id `manual-data-distribution` để không spam liên tục.

## Route, naming và header dùng chung

### Route
- `routes/pushsale_pages.php` đã bị bỏ. Route menu nằm ở `routes/admin/{domain}.php` (13 file: `company`, `hr`, `catalog`, `security`, `operations-config`, `integrations`, `marketing`, `customers`, `sales`, `warehouse`, `accounting`, `ceo`, `reports`).
- Workspace theo role: `routes/roles/{sales,marketing,warehouse,accounting,allocator,platform}.php`.
- Mọi 301 từ URL cũ (`/ld/...`, `/admin/pages/...`) gom về `routes/legacy.php`. Redirect `/admin/pages/{code}-slug` đã xóa hẳn.
- `web.php` chỉ giữ public/auth/profile/shared + `require`.
- Bắt buộc: `php artisan route:list` không được trùng tên route.

### Naming
- Controller theo nghiệp vụ trong `App\Http\Controllers\Admin\{Domain}\`; mã menu chỉ là dữ liệu (`protected $pageCode`). Không còn `PageX_Y_ZController`.
- React page ở `resources/js/pages/Admin/{Domain}/` (hoặc `Sales/`, `Warehouse/`); khóa `component` trong `config/pushsale_pages.php` là đường dẫn Inertia đầy đủ. Không còn `Page_X_Y_Z.jsx`.
- `scripts/audit-pushsale-contract.mjs` fail nếu còn file đặt tên theo mã menu.

### Header dùng chung
- Component: `resources/js/components/layout/PageHeader.jsx` + `PageHeaderProvider`/`PageHeaderOutlet` trong `AppLayout.jsx`. Header được portal lên outlet nên mỗi trang chỉ có một header.
- CSS: `resources/css/pushsale-page-header-contract.css`, load sau `page-frame` và trước `sidebar-canonical`. Có `box-shadow`, không `border-bottom`, sticky khi scroll.
- Filter nâng cao thuộc sibling `.ps-page-extra-filters` (prop `advanced` / `advancedFilters`), không nằm trong `.m-header-wrap`.
- Trang mẫu đã áp: `Sales/CustomerProfile.jsx` (4.2 — kèm tách `Đơn chính`/`Upsale` và cờ upsale ở ô mã đơn) và `Admin/Marketing/LandingConnectionsPage.jsx` (2.4.1 — 6 select hàng 2 + chọn số dòng, tabs kết nối).
- Filter ngày dùng `components/filters/DateRangeFilter.jsx`; biến thể `boxed` gói `[ngày][00:00] – [ngày][23:59]` trong một control có viền.

### Filter stack báo cáo (DRY #15)
- **Primary (admin Pushsale reports):** `PageHeader` / `PushsalePageShell` + `ReportFilterToolbar` + `ReportFilterField` + `useInertiaFilters` + catalog `config/reportFilters.js`. Extra reports dùng wrapper `components/reports/extra/ExtraReportToolbars.jsx`.
- **Secondary (giữ nguyên):** `ReportFilterBar` (shadcn Label/Input/Button + Tailwind) trên các trang Marketing Campaign/Revenue, Sales Performance, Shipping Orders, Allocator Reports — **không** ép sang shell Pushsale trong đợt này để tránh gãy CSS/Tailwind hiện có.
- Báo cáo admin mới: luôn primary stack. Không tạo toolbar filter song song thứ 3.
