# V49 - Role-scoped workspace routing

## Quy ước chính

Không mở bypass kiểu `admin` được đi thẳng vào mọi route `/sales/*`, `/warehouse/*`, `/marketing/*`.
Mỗi vai trò phải có route entrypoint riêng để tránh trồng chéo permission, action URL và menu active:

- Admin xem/tác nghiệp sale qua `/admin/sales/workspace`.
- Sale tác nghiệp qua `/sales/workspace`.
- Admin không truy cập `/sales/workspace`; route đó là của role sales.

## Link từ Hồ sơ khách hàng

Các link trong bảng Hồ sơ khách hàng không hardcode `/sales/workspace` nữa.
Controller truyền `saleWorkspaceUrl` theo role hiện tại:

- admin -> `/admin/sales/workspace`
- sales -> `/sales/workspace`
- các role khác -> không render link tác nghiệp sale

Khi click một order, URL được build thành `{saleWorkspaceUrl}?order_id={id}`.

## Direct order open

`ReportFilterData` có thêm `orderId` để `/admin/sales/workspace?order_id=...` và `/sales/workspace?order_id=...` mở đúng đơn.
Khi có `order_id`, query bỏ date range để không bị default 7 ngày làm mất đơn, nhưng vẫn giữ scope role như `sale_id` tự ép cho sale.

## Không sửa lẫn role middleware

`EnsureUserHasRole` quay lại đúng nhiệm vụ: chỉ role được khai báo mới vào được route group đó.
Admin muốn xem màn của bộ phận nào thì phải đi qua route `/admin/...` tương ứng.
