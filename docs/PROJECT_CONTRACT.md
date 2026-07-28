# ERM Pushsale Project Contract

Living contract. Agent conventions: root `AGENTS.md`. Historical prompt notes: `docs/archive/handoffs/`.

## 0. Sources of truth

| Concern | Location |
| --- | --- |
| Menu tree / codes | `config/pushsale_navigation.php` |
| Page schemas | `config/pushsale_pages.php` |
| Admin menu routes | `routes/admin/{domain}.php` (required from `web.php`) |
| Role workspace routes | `routes/roles/{role}.php` |
| Legacy 301 | `routes/legacy.php` |
| CSS load order | `resources/js/lib/pushsaleStyleRegistry.js` |
| Page header | `components/layout/PageHeader.jsx` + `pushsale-page-header-contract.css` |
| Page frame | `PushsalePageShell.jsx` + `pushsale-page-frame-contract.css` |
| Sidebar + L3 | `AppSidebar.jsx` + `usePushsaleSidebarMenu.js` + `pushsale-sidebar-canonical-contract.css` |
| Orphan CSS | `resources/css/_archive/` (not loaded) |

Do **not** create new `CONTEXT_HANDOFF_V*` / versioned UI docs per prompt. Update this file or `AGENTS.md`.

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
- Thủ kho tác nghiệp (5.1): `pushsale-warehouse-operations-contract.css` (level flags, `nha-mang`, `txt-mof`, `ttgh*`, FAB `fam-*`). HTML gốc tham chiếu: `docs/reference/pushsale-warehouse-operations.html`.
- Sale tác nghiệp (4.1): `pushsale-sale-operations-contract.css` (ô vuông `level-*` Gọi lần/Chăm sóc, cột Tin nhắn plain `Địa chỉ=…`, TN cần `txt-mof`, trash góc cột Sale). HTML: `docs/reference/pushsale-sale-operations.html`.
  - **Tin nhắn** = `customer_note` từ landing (thường dạng `Địa chỉ=… | …`) — chỉ hiển thị, không textarea.
  - **TN cần** = `sale_operation_note` — ô nhập tay `textarea.txt-mof` + nhãn đỏ giai đoạn (Gọi lần 1…).
- Hồ sơ khách hàng (4.2 / route role khác nhau, cùng trang): `pushsale-customer-profile-contract.css` — filter 4 hàng theo Pushsale; cột họ tên/SP dùng `OrderStatusFlags` + `OrderProductsBreakdown`; dialog lịch sử mua hàng table-layout cố định.
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

- `php artisan demo:workspace-ui` — seed dữ liệu gắn nhãn `UXDEMO` cho sale / thủ kho / hồ sơ KH.
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
- Filter nâng cao thuộc `.ps-page-header__advanced.box-body`, không nằm trong `.m-header` — đúng mẫu Pushsale.
- Trang mẫu đã áp: `Sales/CustomerProfile.jsx` (4.2 — kèm tách `Đơn chính`/`Upsale` và cờ upsale ở ô mã đơn) và `Admin/Marketing/LandingConnectionsPage.jsx` (2.4.1 — 6 select hàng 2 + chọn số dòng, tabs kết nối).
- Filter ngày dùng `components/filters/DateRangeFilter.jsx`; biến thể `boxed` gói `[ngày][00:00] – [ngày][23:59]` trong một control có viền.
