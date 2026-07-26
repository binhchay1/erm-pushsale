# V44 — Kết nối giao hàng / Cấu hình giao vận

## Quy ước dự án cần giữ cho các màn sau

- Ảnh và HTML Pushsale được dùng làm mẫu cho **UI/UX và chức năng nhìn thấy được**, không phải để copy nguyên raw HTML/DNN/WebForms vào Laravel/React.
- Route của Pushsale chỉ là dấu hiệu nhận diện màn hình gốc. Dự án ERM vẫn dùng route, controller, policy/permission và service theo business hiện tại.
- Mọi phần UI có pattern giống nhau phải đi qua component/CSS chung trong `resources/css/pushsale.css` hoặc component dùng lại. Không tạo CSS rời cho từng ảnh/từng ô.
- Các màn cấu hình phải lưu dữ liệu thật qua backend, không render dữ liệu cứng.
- Các trang mới vẫn phải bám shell AdminLTE2/Pushsale: `m-header-wrap`, `box-body`, `box-toggle`, `pu-caption`, bảng/filter/action theo cùng token style.

## Màn 1.4 Kết nối giao hàng

Trang được rebuild theo cấu trúc Pushsale:

1. Header: `Cấu hình giao vận`.
2. Box `Đơn vị giao hàng mặc định`:
   - Phương thức giao hàng mặc định.
   - Giao hàng bằng mặc định.
   - Nút Lưu.
   - Lưu vào `companies.default_shipping_provider` và `companies.default_shipping_method`.
3. Box `Cấu hình giao hàng`:
   - Menu dọc bên trái theo các hãng: VN Post, Viettel Post, GHTK, GHN, J&T, EMS, SuperShip, Best, BoxMe, Chim Cắt, Ship60, HolaShip, AhaMove, NinjaVan, SPX Express.
   - Panel phải render form cấu hình theo hãng.
   - Dữ liệu lưu vào `shipping_partner_connections.credentials/settings` qua `ShippingPartnerConfigService`.
4. Alias legacy `/ld/unit-admin/cau-hinh-giao-hang` được thêm để tiện đối chiếu ảnh Pushsale, nhưng route chính vẫn là `/admin/shipping-partners`.

## Files chính

- `resources/js/pages/Admin/ShippingPartners/Index.jsx`
- `resources/js/components/shipping/ShippingPartnerCard.jsx`
- `app/Services/Shipping/ShippingPartnerConfigService.php`
- `config/shipping_partners.php`
- `routes/web.php`
- `resources/css/pushsale.css`
