# ERM Pushsale V17 — Hệ thống giao diện, bộ lọc và modal dùng chung

## Mục tiêu

V17 không vá riêng từng màn. Bản này thiết lập lại hợp đồng giao diện chung cho toàn bộ phần ERM nội bộ, đồng thời tách hoàn toàn CSS của website/đăng nhập khỏi CSS của hệ thống nghiệp vụ.

Các lỗi được xử lý ở cấp hệ thống:

- hàng filter bị chừa cột rỗng ở đầu hoặc bị xuống hàng bất thường;
- cột thao tác xuất hiện thêm khung viền bên trong từng dòng;
- font menu, bảng, form và modal không đồng nhất;
- CSS từ template này ghi đè template khác;
- modal vượt viewport, header/footer trôi theo nội dung hoặc body không cuộn đúng;
- filter nhân viên chứa dữ liệu chụp từ Pushsale thay vì dữ liệu tenant hiện tại;
- bảng xếp hạng và một số option còn dữ liệu tài khoản mẫu trong HTML capture.

## 1. Kiến trúc CSS mới

### Entry dùng chung

`resources/css/app.css` chỉ giữ Tailwind token, component dùng chung và reset tối thiểu. File này không còn import layout Pushsale hoặc layout public.

### Website và đăng nhập

`resources/css/public.css` chỉ import:

```css
@import "./public-shell.css";
```

Trang public/login không tải Bootstrap, AdminLTE hoặc lớp CSS của ERM.

### ERM nội bộ

`resources/css/pushsale.css` là entry duy nhất cho shell nội bộ. Các lớp cũ được nạp theo thứ tự cố định và `pushsale-system-v17.css` luôn được nạp cuối cùng để làm hợp đồng cuối:

```text
layout
→ common
→ page modules
→ reports / warehouse / shipping
→ pushsale-system-v17.css
```

Hai file vá lịch sử `pushsale-v12-fixes.css` và `pushsale-v13-fixes.css` đã bị loại bỏ khỏi source. Quy tắc cuối không còn phân tán ở nhiều file patch.

### Font

Toàn bộ `body.pushsale-app-body` sử dụng:

```css
Arial, Helvetica, sans-serif
```

Font Awesome được tách ngoại lệ để icon không bị đổi font. Website/login vẫn dùng font public riêng.

## 2. Template HTML được cô lập

79 template trong `public/pushsale-templates` được kiểm tra lại:

- mọi `<style>` đều nằm trong `@scope ([data-template-code="..."])`;
- không còn script thực thi;
- loại generated DOM của Select2/Chosen, chỉ giữ select thật;
- loại option được render từ `ng-repeat` hoặc tài khoản tenant của bản chụp;
- dữ liệu bảng chính trong capture không được dùng làm dữ liệu nghiệp vụ;
- các tên/tài khoản Pushsale mẫu còn sót đã bị loại;
- bảng xếp hạng Sales được render lại bằng dữ liệu backend hiện tại.

Script dựng template `scripts/build_pushsale_templates.py` cũng chứa quy tắc này để lần import template sau không tái tạo lỗi cũ.

## 3. Hợp đồng filter layout

`TemplateHost` nhận diện hàng filter theo cấu trúc Bootstrap của HTML gốc rồi gắn class runtime:

- `pushsale-header-row` cho header/filter đầu trang;
- `pushsale-filter-row` cho hàng filter trong box-body;
- `pushsale-empty-column` cho cột không có text hoặc control.

Các hàng này được chuyển sang grid 12 cột. Cột rỗng bị loại khỏi flow nên control phía sau tự dồn về đầu hàng. Cơ chế áp dụng cả hàng có một control, không chỉ hàng có nhiều control.

Không áp dụng grid này vào:

- bảng dữ liệu;
- modal/dialog;
- hàng pagination;
- layout không dùng class Bootstrap column.

## 4. Dữ liệu filter động

### Lịch sử đăng nhập — menu 1.7.1

Danh sách nhân viên, role và đơn vị lấy từ database theo tenant hiện tại:

- `users`;
- `companies`;
- `roles`;
- `loginUsers` kèm tổng số bản ghi đăng nhập;
- trạng thái đăng nhập;
- kiểu sắp xếp.

Khối tên nhân viên trong ảnh Pushsale được thay bằng portal React `LoginUserQuickFilters`. Không còn danh sách tên viết cứng trong HTML.

Login/logout được ghi vào `activity_logs` bằng các action:

```text
auth.login.success
auth.login.failed
auth.login.blocked
auth.logout
```

Bảng lịch sử dùng IP, công ty, tài khoản, user-agent, thời gian và trạng thái thật.

### Quản lý sản phẩm — menu 1.3.1

Các filter được nối đúng backend:

- phân loại → `product_categories`;
- trạng thái kinh doanh → `products.is_active`;
- quyền dùng cho Marketing/Sale/CSKH → các cột `available_*`;
- sắp xếp ngày tạo, mã sản phẩm, tên sản phẩm;
- sản phẩm cha → sản phẩm backend có `parent_id = null`.

### Đội/nhóm

Filter trưởng nhóm lấy người dùng thật có `is_team_leader = true`. Không dùng option tài khoản của bản chụp.

## 5. Cột thao tác

`pushsale-row-actions` là wrapper duy nhất cho action của bảng. V17 loại toàn bộ:

- border;
- background;
- box-shadow;
- padding dư;
- pseudo-element phát sinh khung.

Chỉ border của cell thuộc grid table được giữ lại. Nút sửa/xóa/xem là icon 22×22, không tạo thêm hộp con ở từng dòng.

## 6. Modal dùng chung

Component chuẩn:

```text
resources/js/components/ui/pushsale-modal.jsx
```

Cấu trúc cố định:

```text
Dialog overlay
└── ps-modal-surface
    ├── ps-modal-header   (không cuộn)
    ├── ps-modal-body     (vùng cuộn duy nhất)
    └── ps-modal-footer   (không cuộn)
```

Chiều rộng được truyền qua `--ps-modal-width`, sau đó luôn clamp vào viewport:

```css
width: min(var(--ps-modal-width), calc(100vw - 24px));
max-height: calc(100dvh - 24px);
```

Các modal hồ sơ khách hàng đã chuyển sang shell này:

- lịch sử tác nghiệp;
- lịch sử mua hàng;
- tin nhắn nội bộ/Pancake;
- editor generic của các trang template.

Các modal Radix hoặc legacy chưa chuyển component vẫn nhận fallback viewport contract để không thể tràn màn hình.

## 7. Bảng xếp hạng Sales

Khối top 10 chụp từ Pushsale đã bị xóa. `SalesRankingCards` render top 10 từ cùng collection backend dùng cho bảng chi tiết:

- tên Sale thật;
- thứ hạng thật;
- doanh thu VND thật;
- không có avatar/tên/tổng doanh số mẫu.

## 8. Quy tắc phát triển tiếp

Khi thêm trang mới:

1. Không import CSS page vào `app.css`.
2. CSS trang nội bộ phải được import từ `pushsale.css` trước `pushsale-system-v17.css`.
3. CSS lấy từ HTML capture phải scope bằng `data-template-code`.
4. Không giữ option hoặc row dữ liệu chụp trong template.
5. Filter nhân viên/sản phẩm/kho/nguồn phải lấy từ `filterOptions()` hoặc service nghiệp vụ tương ứng.
6. Modal mới dùng `PushsaleModal`; chỉ body được cuộn.
7. Không thêm file `*-fixes.css` mới. Lỗi hệ thống phải sửa trong contract chung, lỗi riêng trang sửa trong stylesheet module của trang.
