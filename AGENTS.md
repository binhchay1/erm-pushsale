# AGENTS.md — ERM Pushsale conventions

Đọc file này trước khi sửa UI, CSS, route, hoặc thêm docs. Nguồn sự thật sống: `docs/PROJECT_CONTRACT.md` + file này. Không tạo `CONTEXT_HANDOFF_V*` / `UI_*_V*` mới.

## 1. Nguồn sự thật (đừng nhân bản)

| Việc | File |
| --- | --- |
| Menu sidebar / mã menu | `config/pushsale_navigation.php` |
| Schema trang template | `config/pushsale_pages.php` |
| Route menu admin Pushsale | `routes/pushsale_pages.php` (require từ `web.php`) |
| Extra reports | `config/pushsale_report_routes.php` + `web.php` |
| CSS cascade runtime | `resources/js/lib/pushsaleStyleRegistry.js` |
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
- **Controller page**: `Page{code với _}` → `Page1_2_3Controller` trong `App\Http\Controllers\Admin\Pushsale\Pages\`.
- **React page**: theo domain folder, không theo version.  
  `resources/js/pages/Admin/{Domain}/...` hoặc `Sales/`, `Warehouse/`.
- **`data-page-code`**: gắn mã menu trên shell khi có (vd. `2.1`).
- **CSS page**: `pushsale-{feature}-contract.css` hoặc `pushsale-{feature}-page.css`.  
  Cấm: `pushsale-v101-...`, `pushsale-parity-v67.css`.
- **Legacy URL**: chỉ 301 trong `pushsale_pages.php` từ `/admin/pages/{code}-slug` → canonical.

## 3. CSS — chống đè nhau

1. Chỉ load CSS app qua `pushsaleStyleRegistry.js` (hoặc Vite entry: `app.css` / `public.css` / `pushsale.css`).
2. Thứ tự cuối registry (không đảo):  
   `unified-page-shell` → `adminlte-canonical` → `page-frame` → **`sidebar-canonical` (absolute last)**.
3. Sidebar/header: chỉ sửa canonical + React shell. Không thêm file `pushsale-sidebar-*` mới để “vá hover”.
4. Page CSS phải scope class trang (`.ps-page-...` / page root). Không đụng `.main-sidebar`, `.navbar`, `.ul2`.
5. File không nằm trong registry → đưa `_archive/`, không để lẫn root.
6. Một bug layout chung → sửa contract chung, không tạo contract mới mỗi prompt.

## 4. UI shell

Mọi trang admin: `AppLayout` → content qua `PushsalePageShell` (hoặc wrapper đã dùng shell).

Slots chuẩn:

- `title` + layout: `is-title-only` | `is-title-actions` | `has-primary-filters`
- `primaryFilters` / `actions` (hàng 1)
- `advancedFilters` (hàng 2)
- `toolbar` (CRUD/export)
- `children` = body

Thiếu use case → mở rộng shell, không copy header từng trang.

## 5. Routes: `pushsale_pages.php` vs `web.php`

- `pushsale_pages.php` = module route theo nhóm menu 1.x–8.x + redirect legacy. **Giữ file riêng** (không nhét ~400 dòng vào `web.php`).
- `web.php` = auth, dashboard, CRUD core (users/products/…), report loop, require module ở trên.
- Thêm trang menu mới: thêm block trong `pushsale_pages.php` đúng section menu + cập nhật navigation config nếu cần.

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
- [ ] Không override sidebar từ page CSS.
- [ ] Trang mới dùng `PushsalePageShell`.
- [ ] Route nằm đúng section menu trong `pushsale_pages.php` (hoặc CRUD core trong `web.php`).
- [ ] Không tạo doc handoff version mới.
- [ ] Test liên quan (ít nhất unit contract CSS/shell nếu đụng registry/shell).
