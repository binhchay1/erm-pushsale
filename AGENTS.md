# AGENTS.md — ERM Pushsale conventions

Đọc file này trước khi sửa UI, CSS, route, hoặc thêm docs. Nguồn sự thật sống: `docs/PROJECT_CONTRACT.md` + file này. Không tạo `CONTEXT_HANDOFF_V*` / `UI_*_V*` mới.

## 1. Nguồn sự thật (đừng nhân bản)

| Việc | File |
| --- | --- |
| Menu sidebar / mã menu | `config/pushsale_navigation.php` |
| Schema trang template | `config/pushsale_pages.php` |
| Route menu admin Pushsale | `routes/admin/{domain}.php` (require từ `web.php`) |
| Route workspace theo role | `routes/roles/{role}.php` |
| Redirect URL cũ | `routes/legacy.php` |
| Extra reports | `config/pushsale_report_routes.php` + `routes/admin/reports.php` |
| CSS cascade runtime | `resources/js/lib/pushsaleStyleRegistry.js` |
| Header trang (dùng chung) | `components/layout/PageHeader.jsx` + `pushsale-page-header-contract.css` |
| Shell trang | `PushsalePageShell.jsx` + `pushsale-page-frame-contract.css` |
| Sidebar / L3 flyout | `AppSidebar.jsx` + `usePushsaleSidebarMenu.js` + `pushsale-sidebar-canonical-contract.css` (load **cuối**) |
| Contract nghiệp vụ dài | `docs/PROJECT_CONTRACT.md` |
| Route/menu naming | `docs/PUSHSALE_ROUTE_CONTRACT.md` |
| Handoff lịch sử | `docs/archive/handoffs/` (chỉ đọc khi cần context cũ) |
| CSS orphan | `resources/css/_archive/` (không import lại) |

## 2. Đặt tên theo menu

Menu Pushsale dùng mã dạng `1.2.1`, `4.6.2`, `8.5.9`.

- **Route name / path**: theo nghiệp vụ tiếng Anh, không theo số prompt.  
  Ví dụ: `/admin/hr/work-shifts`, name `admin.hr.work-shifts`.
- **Controller page**: tên nghiệp vụ trong `App\Http\Controllers\Admin\{Domain}\`.  
  Ví dụ menu `1.2.3` → `Admin\Hr\WorkShiftController`. Mã menu chỉ là **dữ liệu**: `protected $pageCode = '1.2.3'`.  
  Cấm `Page1_2_3Controller`.
- **React page**: theo domain folder, không theo version hay mã menu.  
  `resources/js/pages/Admin/{Domain}/...` hoặc `Sales/`, `Warehouse/`. Cấm `Page_1_2_3.jsx`.
- **`data-page-code`**: gắn mã menu trên shell khi có (vd. `2.1`) — `PageHeader` nhận qua prop `pageCode`.
- **CSS page**: `pushsale-{feature}-contract.css` hoặc `pushsale-{feature}-page.css`.  
  Cấm: `pushsale-v101-...`, `pushsale-parity-v67.css`.
- **Legacy URL**: chỉ 301 trong `routes/legacy.php` → canonical.

## 3. CSS — chống đè nhau

1. Chỉ load CSS app qua `pushsaleStyleRegistry.js` (hoặc Vite entry: `app.css` / `public.css` / `pushsale.css`).
2. Thứ tự cuối registry (không đảo):  
   `unified-page-shell` → `adminlte-canonical` → `page-frame` → `page-header` → **`sidebar-canonical` (absolute last)**.
3. Sidebar/header: chỉ sửa canonical + React shell. Không thêm file `pushsale-sidebar-*` mới để “vá hover”.  
   Style header trang chỉ nằm ở `pushsale-page-header-contract.css`; page CSS không tự set `border-bottom` / `box-shadow` cho `.m-header-wrap`.
4. Page CSS phải scope class trang (`.ps-page-...` / page root). Không đụng `.main-sidebar`, `.navbar`, `.ul2`.
5. File không nằm trong registry → đưa `_archive/`, không để lẫn root.
6. Một bug layout chung → sửa contract chung, không tạo contract mới mỗi prompt.

## 4. UI shell + header dùng chung

Mọi trang admin: `AppLayout` → header qua `PageHeader`, body qua `PushsalePageShell` (hoặc wrapper đã dùng shell).

`PageHeader` đẩy nội dung lên `PageHeaderOutlet` trong `AppLayout` nên **mỗi trang chỉ có đúng một header**, không thể double khi component lồng nhau. DOM xuất ra đúng mẫu Pushsale:

```
.m-header-wrap.ps-page-header
  > .m-header.ps-page-header__row   (title | filters | actions)
  > .ps-page-header__advanced.box-body   (filter nâng cao, NGOÀI .m-header)
```

Props: `title`, `subtitle`, `icon`, `filters` (= `primaryFilters`), `actions`, `advanced` (= `advancedFilters`), `pageCode`, `className`, `collapsible`, `defaultCollapsed`.

Lưu ý: nội dung `actions`/`filters` được portal ra khỏi cây DOM của trang. Nút submit nằm trong đó phải dùng `form="<id của form>"`, không dựa vào việc nằm trong `<form>`.

`PushsalePageShell` chỉ còn `notice` → `toolbar` → `body`; phần header của shell chuyển tiếp sang `PageHeader`.

Thiếu use case → mở rộng `PageHeader`/shell, không copy header từng trang.

## 5. Routes: `routes/admin/*` · `routes/roles/*` · `web.php`

- `routes/admin/{domain}.php` = route menu 1.x–8.x, chia theo nhóm nghiệp vụ: `company`, `hr`, `catalog`, `security`, `operations-config`, `integrations`, `marketing`, `customers`, `sales`, `warehouse`, `accounting`, `ceo`, `reports`.
- `routes/roles/{role}.php` = workspace theo role: `sales`, `marketing`, `warehouse`, `accounting`, `allocator`, `platform`.
- `routes/legacy.php` = toàn bộ 301 từ URL cũ (`/ld/...`) về canonical. Không rải redirect trong file domain.
- `web.php` = public, auth, profile, shared + các dòng `require`. Không nhét route module vào đây.
- Thêm trang menu mới: thêm block trong đúng `routes/admin/{domain}.php` + cập nhật `config/pushsale_navigation.php` nếu cần.

## 6. Backend SOLID (thực dụng)

- **S**: Controller mỏng — validate request, gọi service, trả Inertia/JSON.
- **O**: Mở rộng bằng service/policy mới; tránh if-else khổng lồ trong controller.
- **L/I**: Interface chỉ khi có ≥2 implementation thật.
- **D**: Service inject repo/query; Model không chứa validation nghiệp vụ phức tạp.
- Transaction / phân bổ lead / duyệt landing → service layer (`app/Services/...`).
- Không sửa schema “repair runtime” nếu phá dữ liệu.

## 7. Docs — đừng spam

- Cập nhật `PROJECT_CONTRACT.md` / `AGENTS.md` / rule Cursor khi thay đổi contract sống.
- Không tạo `CONTEXT_HANDOFF_V{n}.md`, `RELEASE_VALIDATION_V{n}.md` cho mỗi prompt.
- Changelog ngắn → `docs/CHANGELOG.md` nếu cần.
- Archive cũ nằm `docs/archive/handoffs/`.

## 8. Checklist trước khi xong task UI

- [ ] Không thêm CSS ngoài registry (trừ khi đã đăng ký có chủ đích).
- [ ] Không override sidebar/header từ page CSS.
- [ ] Trang mới dùng `PageHeader` + `PushsalePageShell`, không tự dựng `.m-header-wrap`.
- [ ] Route nằm đúng `routes/admin/{domain}.php` hoặc `routes/roles/{role}.php` (CRUD core trong `web.php`).
- [ ] Controller/JSX không đặt tên theo mã menu.
- [ ] Không tạo doc handoff version mới.
- [ ] `node scripts/audit-pushsale-contract.mjs` sạch, `php artisan route:list` không trùng tên route.
- [ ] Test liên quan (ít nhất unit contract CSS/shell nếu đụng registry/shell).
