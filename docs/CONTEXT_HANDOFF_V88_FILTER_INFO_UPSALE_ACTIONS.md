# V88 - Filter overlap, information contract, upsale setup and action buttons

## Mục tiêu

Bản V88 tiếp tục sau V87, xử lý các lỗi QA mới trên giao diện thật:

1. Header/filter trang **Danh sách nhân viên** bị đè ở cụm `--Chọn TT sử dụng--` + ô tìm kiếm + nút tìm kiếm.
2. Dialog **Thêm nhiều tài khoản** có textarea tài khoản quá hẹp, chưa hướng dẫn format nhập.
3. Quy hoạch lại icon information: giữ icon information ở thanh topbar màu xanh, ẩn các icon help/info trùng trong header/filter của từng trang.
4. Bỏ mô hình nguồn riêng “trang cảm ơn” khỏi dialog kết nối landing. Luồng hiện tại chỉ còn `Landing chính` và `Trang upsale`; URL kết thúc luồng chỉ là redirect đích, không phải source nhận form.
5. Bổ sung hướng dẫn rất chi tiết trong PageInfo cho trang kết nối landing: từ tạo nhân viên → tạo sản phẩm → marketing tạo kết nối → cấu hình Ladipage / website / Facebook → lead → upsale → sale chốt → kho xử lý.
6. Kiểm tra hiển thị upsale ở các màn tác nghiệp: hồ sơ khách hàng, sale tác nghiệp, thủ kho tác nghiệp, kế toán đối soát.
7. Admin dashboard: đưa badge LIVE ra slot bên phải của toolbar.
8. Floating action buttons ở thủ kho/đăng đơn: tách contract riêng, ép tròn bằng class scoped + `clip-path`, giảm trạng thái mờ của nút disabled.

## File thay đổi chính

- `resources/css/pushsale-filter-info-upsale-actions.css`
- `resources/js/lib/pushsaleStyleRegistry.js`
- `resources/js/pages/Admin/Users/Index.jsx`
- `resources/js/pages/Admin/Dashboard.jsx`
- `resources/js/pages/Pushsale/Pages/Marketing/LandingConnectionsPage.jsx`
- `app/Http/Controllers/Admin/Marketing/LandingConnectionsController.php`
- `app/Services/Testing/StagingTestService.php`
- `resources/js/components/operations/WarehouseOrderTable.jsx`
- `resources/js/components/operations/pushsale/SaleWorkspaceTable.jsx`
- `resources/js/components/operations/AccountingReconTable.jsx`
- `resources/js/i18n/guides/vi.js`
- `resources/js/i18n/guides/en.js`

## Chi tiết quyết định kỹ thuật

### 1. Danh sách nhân viên: fix đè filter/search

Legacy CSS trước đó có rule riêng cho `.ps-users-page` dùng width cứng: filter 4 cột × 290px và search 440px. Khi viewport nhỏ hơn, tổng width vượt phần header nên select cuối và search overlap. V88 không sửa từng input mà ép lại contract của page shell:

- Title col: `minmax(260px, 340px)`.
- Filter col: `minmax(0, 1fr)` để được shrink.
- Search col: `minmax(380px, 455px)`.
- Filter row đổi sang `repeat(4, minmax(135px, 1fr))`.
- Search form đổi sang grid `input + button`, không dùng gap 30px legacy.

### 2. Dialog thêm nhiều tài khoản

Textarea tài khoản được mở rộng theo grid 190px + 1fr, tăng rows, thêm placeholder rõ format:

```text
Mỗi dòng một tài khoản. Ví dụ:
sale01
sale02
marketing01
```

Ghi chú trong dialog cũng đổi rõ: không nhập đuôi email, hệ thống tự sinh email theo đơn vị hiện tại, tạo user thật và hồ sơ vận hành để tham gia phân bổ data.

### 3. Information icon

`AppHeader` đã có `PageInfoButton` ở `.pushsale-header-tools`. V88 chỉ giữ icon này và hide các icon duplicate trong header/filter page:

- `.psm-help-button`
- `.psr-help`
- `.btn-help`
- `.ps-help-button`
- button có title `Hướng dẫn` / `Trợ giúp` trong `m-header` hoặc `ps-page-shell__actions`

Không hide help inline trong form setting / help text nghiệp vụ vì các icon đó giải thích field cụ thể, không phải page-level duplicate.

### 4. Landing / Upsale source model

UI dialog kết nối landing chỉ còn hai source type:

- `main`: Landing chính
- `upsell`: Trang upsale

Controller validation cũng chỉ accept `main` và `upsell`. Legacy `thank_you` constant trong model vẫn để lại để tránh phá dữ liệu cũ / migration cũ, nhưng controller serialize loại source đó khỏi payload edit. Khi mở record cũ, source không thuộc `main|upsell` bị lọc khỏi form và product mapping trỏ vào source cũ sẽ reset `source_key` về rỗng.

### 5. Cách setup Ladipage / website / Facebook

Page guide tiếng Việt/Anh cho `/admin/marketing/landing-connections` đã bổ sung chi tiết:

- Copy URL nhận dữ liệu của Landing chính, cấu hình form POST tới URL đó.
- Field bắt buộc là số điện thoại: `phone`, `customer_phone`, hoặc `tel`.
- Nếu có upsale: Redirect của Landing chính là URL trang upsale. Backend tự append `ps_flow` và `saleops_session`.
- Form upsale POST tới URL nhận dữ liệu của nguồn upsale, gửi `ps_flow` từ query string hoặc lặp lại số điện thoại để gộp đơn trong cửa sổ 90 giây.
- Facebook Ads dùng cùng cấu hình ngân sách/sản phẩm/marketer; lead Facebook đi qua webhook Facebook riêng, không tạo thêm source đích cuối.

### 6. Upsale display ở các màn tác nghiệp

- Hồ sơ khách hàng: V87 đã có `ps-product-line`, divider và icon upsale.
- Thủ kho tác nghiệp: V88 thêm divider cho dòng upsale trong `ps-wh-product-table`, tooltip `Upsale`, tag `UP`.
- Sale tác nghiệp: V88 cập nhật `SaleWorkspaceTable.ProductLines` để nhận `isUpsell`, `itemType=upsell`, hoặc `origin` chứa upsell/upsale; có divider + icon.
- Kế toán đối soát: V88 cập nhật `AccountingReconTable.ProductTable` tương tự, thêm divider + tag.

### 7. Admin dashboard LIVE

Toolbar dashboard đổi thành:

- `.psfd-toolbar-copy`
- `.psfd-toolbar-controls`
- `.psfd-filter-controls`
- `.psfd-live-slot`

CSS V88 ép `.psfd-live-slot` justify right để badge LIVE nằm về phía phải toolbar thay vì dính cạnh nút Tải dữ liệu.

### 8. Floating action buttons thủ kho

V88 thêm class riêng:

- `.ps-wh-action-button`
- `.ps-wh-main-action`

CSS chỉ target `.ps-wh-floating-actions`, không target broad `.btn`. Nút được ép:

- `border-radius: 9999px`
- `aspect-ratio: 1 / 1`
- `clip-path: circle(50% at 50% 50%)`
- `opacity: 1` mặc định
- disabled còn `.72` thay vì mờ quá nhiều

## Kiểm tra đã chạy

```bash
php -l app/Http/Controllers/Admin/Marketing/LandingConnectionsController.php
php -l app/Services/Testing/StagingTestService.php
php -l app/Services/Customers/CustomerProfileService.php
php -l app/Services/Operations/OrderOperationPresenter.php
node ./scripts/audit-pushsale-contract.mjs
```

Kết quả audit:

```text
33 pass, 12 warn, 0 fail
```

Không chạy được `pnpm build` trong sandbox vì không có `node_modules` / vendor runtime.

## Lệnh cần chạy sau khi apply

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

