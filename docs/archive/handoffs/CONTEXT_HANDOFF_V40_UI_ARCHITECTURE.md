# V40 – UI architecture normalization

## Mục tiêu
Không vá giao diện theo từng ảnh riêng lẻ nữa. Các phần dùng chung phải đi qua cùng một bộ khung:

- `PushsalePageChrome` cho title/filter/action ở đầu trang.
- `PageHeader` dùng lại `PushsalePageChrome`.
- `PageInfoButton` nằm ở header, cạnh ngôn ngữ, luôn có fallback guide nếu page chưa có nội dung hướng dẫn riêng.
- CSS cuối `resources/css/pushsale.css` có block `V40 Pushsale UI architecture normalization` làm layer chung cho page chrome, table, nested product/money cells và floating action.

## Sửa chính

1. Chuẩn hóa header/title/filter
   - Gom layout về grid 2 hoặc 3 cột: title trái, filter giữa/phải, action phải.
   - Áp dụng chung cho `ps-report-topbar`, `ps-extra-toolbar`, `ps-sale-title-row`, `psm-topbar`, `psr-topbar`, `m-header-wrap`, `psfd-toolbar`.

2. Icon hướng dẫn
   - Source: `PageInfoButton` luôn render ở header và có fallback guide.
   - Built asset cũ vẫn được vá để icon header không click vào nút content bị ẩn; nếu page chưa có modal riêng sẽ fallback bằng alert ngắn.
   - Các nút help trong content bị ẩn để UI không lệch tông.

3. Nút chọn doanh số hiển thị
   - Source `RevenueGroupSelector` đổi từ `<details><summary>` sang button + popover, dùng FontAwesome icon thật.
   - CSS fallback vá built JS cũ vẫn dùng `<summary>` để không còn ký tự lỗi font/encoding.

4. Bảng và ô dữ liệu
   - Tăng width các cột quan trọng bằng hệ thống chung.
   - Cột sản phẩm/tiền trong customer profile/sale workspace không còn bị ép thành các hộp nhỏ.
   - Nested `tb-in-sp`, `ps-money-stack`, `ps-money-cell` được coi là content layout, không phải mini table đóng khung.

5. Floating action
   - `tao-don-fixed`, `pushsale-create-order-fab`, `ps-floating-add`, `legacy-floating-add`, `ps-action-fab` dùng chung một rule fixed viewport.
   - Kiểu rounded-square giống Pushsale, không nhảy khi mở F12.

## Files chính

- `resources/js/components/layout/PushsalePageChrome.jsx`
- `resources/js/components/layout/PageHeader.jsx`
- `resources/js/components/layout/PageInfoButton.jsx`
- `resources/js/pages/Reports/ExtraReport.jsx`
- `resources/js/components/reports/ceo/CeoReportFilterBar.jsx`
- `resources/js/i18n/locales/vi/reports.js`
- `resources/js/i18n/locales/en/reports.js`
- `resources/css/pushsale.css`
- `public/build/assets/pushsale-VZglJWi2.css`
- `public/build/assets/AppLayout-C6T4YjHa.js`

## Lưu ý sau này
Các fix UI giống nhau không viết theo từng page nữa. Nếu có lỗi ở table/action/header/filter, sửa trong block V40 hoặc nâng cấp component chung thay vì thêm selector rời rạc ở từng màn.
