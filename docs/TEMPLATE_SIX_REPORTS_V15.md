# V15 — Template Six reports, upsale và menu theo vai trò

## 1. Phạm vi

V15 tích hợp toàn bộ nhóm `template-six` vào hệ thống báo cáo thật. HTML/CSS/JS và ảnh PNG trong `public/pushsale-templates` cùng `docs/references/template-six` chỉ là tài liệu đối chiếu giao diện; frontend không đọc dữ liệu mẫu nằm trong HTML.

| Mã menu gốc | Trang nghiệp vụ | Backend/route dùng chung |
|---|---|---|
| 4.5.1 | Sale KPI 2 | `sale-4` |
| 4.5.2 | Bảng tổng hợp chốt đơn | `sale-2` |
| 4.5.3 | Báo cáo công việc sale | `sale-1` |
| 4.5.4 | Báo cáo doanh số chi tiết sale | `sale-3` |
| 4.5.5 | Báo cáo doanh số theo kho | `warehouse-sales-summary` |
| 4.5.6 | Báo cáo doanh số V2 | `warehouse-sales-v2` |
| 4.5.7 | CEO dashboard V2 | `/admin/reports/ceo` |
| 4.5.8 | Báo cáo lịch hẹn telesales | `sale-5` |
| 6.3.9 | Ma trận tỉ lệ chốt đơn sản phẩm/user | `product-conversion` |

Cùng một report key được tái sử dụng ở nhiều menu role. Route, phạm vi dữ liệu và mã menu active được đổi theo vai trò thay vì copy lại công thức báo cáo.

## 2. Nguồn sự thật nghiệp vụ

### Contact

Một contact chỉ được tính khi packet gốc trong `lead_ingestions` có `counts_as_lead = true`. Packet upsale, late-upsell hoặc supplemental không làm tăng contact. Một khách gửi landing chính rồi gửi nhiều form upsale vẫn chỉ là một contact trong mẫu số tỷ lệ chốt.

### Đơn hàng và upsale

- Sản phẩm gốc: `order_items.item_type != 'upsell'`.
- Sản phẩm upsale: `order_items.item_type = 'upsell'`.
- Đơn bổ sung hợp lệ được cộng vào đơn hàng, sản lượng và doanh số nhưng không tạo thêm contact.
- Tỷ lệ đơn có upsale = số đơn chốt có ít nhất một dòng upsale / tổng đơn chốt.
- Tỷ trọng doanh số upsale = doanh số dòng upsale / tổng doanh số tương ứng.
- Ma trận sản phẩm cộng doanh thu theo từng `order_item`, không nhân toàn bộ doanh thu đơn cho mỗi sản phẩm.

### Doanh số VND

Các báo cáo dùng `Order::netRevenue()` làm công thức doanh số chung:

```text
Doanh số thuần = doanh số hiệu lực của đơn - chi phí vận chuyển thuộc đơn
```

Chiết khấu được tách riêng. Backend giữ tiền dưới dạng số nguyên VND; frontend chỉ định dạng khi hiển thị và export.

### Trạng thái giao một phần

`partial_delivery` là trạng thái nghiệp vụ chính thức. Các alias cũ `partial`, `delivered_partial`, `partially_delivered` vẫn được đọc để không làm mất dữ liệu lịch sử. Đơn giao một phần:

- được tính trong nhóm giao thành công của báo cáo;
- được tách riêng ở nhóm doanh số giao một phần;
- vào nhóm thực tế hoặc chờ đối soát tùy `reconciliation_status`;
- được tính trong doanh thu tài chính và COD chờ đối soát.

## 3. Mười hai nhóm doanh số Pushsale

`OrderRevenueClassifier` là nguồn sự thật chung cho báo cáo 4.5.5, 4.5.6 và chế độ doanh số của Marketing Dashboard.

| STT | Nhóm | Quy tắc chính |
|---:|---|---|
| 1 | Tổng | Tất cả đơn trong phạm vi báo cáo |
| 2 | Xác nhận | Đã qua bước chờ/hủy/hoãn trước khi giao |
| 3 | Tạm tính | Loại đơn đã hoàn, hủy vận đơn và hủy đăng đơn |
| 4 | Chiết khấu của tạm tính | Nhóm tạm tính có `discount > 0` |
| 5 | Hủy | `cancel_waybill`, `cancel_closing` |
| 6 | Đang hoàn | `returning`, `cannot_deliver` |
| 7 | Đã hoàn | `returned`, `refund` |
| 8 | Đang vận chuyển | Đã đăng/lấy/giao/đang hoàn nhưng chưa kết thúc |
| 9 | Giao thành công | `delivered`, `delivery_complete`, `paid`, giao một phần |
| 10 | Thực tế | Giao thành công và đã đối soát |
| 11 | Chờ đối soát | Giao thành công nhưng chưa đối soát |
| 12 | Giao một phần | `partial_delivery` và các alias lịch sử |

Mỗi nhóm ở 4.5.5 có: doanh số, số đơn, trung bình/đơn, số sản phẩm, sản phẩm/đơn. Mỗi nhóm ở 4.5.6 có: số đơn, số sản phẩm, trung bình/đơn và doanh số. Giao diện mặc định mở nhóm 1–4 để bảng không quá rối; người dùng có thể bật đủ 12 nhóm. Export luôn giữ đủ toàn bộ cột.

## 4. Nội dung từng báo cáo

### 4.5.1 — Sale KPI 2

KPI đọc `MonthlyKpiPlan` thật của từng nhân viên: contact mới/cũ, đơn chốt, tỷ lệ chốt, mục tiêu contact/doanh số, mức hoàn thành, doanh số dự kiến/thực nhận, lương cơ bản, thưởng/hoa hồng, sản lượng và doanh số upsale.

### 4.5.2 — Bảng tổng hợp chốt đơn

Tách khách mới, khách cũ và tổng. Doanh số từ đơn bổ sung/upsale được ghi nhận cho Sale phụ trách, còn contact và tỷ lệ chốt chỉ dựa trên packet contact thật.

### 4.5.3 — Báo cáo công việc Sale

Đồng bộ giai đoạn/kết quả tác nghiệp, contact được giao, số lần gọi, lịch hẹn, đơn chốt và phạm vi staff/leader.

### 4.5.4 — Báo cáo doanh số chi tiết Sale

Giữ các nhóm trạng thái Pushsale và bổ sung sản phẩm gốc, upsale, tỷ lệ đơn có upsale, tỷ trọng doanh số upsale, giao một phần và trạng thái đối soát.

### 4.5.5 và 4.5.6 — Báo cáo doanh số theo kho/V2

Hai trang dùng chung nguồn order, warehouse, classifier 12 nhóm và công thức doanh số. Bản V2 bổ sung phễu contact, contact chốt, tỷ lệ chốt và các chỉ số sản phẩm/đơn.

### 4.5.7 — CEO dashboard V2

KPI Sale đọc kế hoạch tháng; ngân sách Marketing lấy thực chi theo ngày và dùng ngân sách Landing phân bổ cho ngày chưa có thực chi; doanh số Marketing gom theo Kết nối Landing/marketer; toàn bộ phần Sale và Marketing tách được upsale.

### 4.5.8 — Lịch hẹn telesales

Hỗ trợ khoảng ngày tối đa 31 ngày, lọc nhóm, giai đoạn và kết quả tác nghiệp. Staff chỉ thấy phạm vi của mình; leader/admin thấy phạm vi được phân quyền.

### 6.3.9 — Ma trận tỉ lệ chốt sản phẩm/user

Tổng hợp theo sản phẩm, Sale và Marketing. Doanh thu lấy từ dòng hàng, tách doanh số upsale và tránh nhân doanh thu khi một đơn có nhiều sản phẩm.

## 5. Menu theo vai trò

Menu nguồn vẫn là một cây chung, sau đó `NavigationService` lọc theo role, `PermissionArea::Reports`, cấp tổ chức và `ExtraReportService::canView()`.

| Vai trò | Nhóm menu chính | Báo cáo dùng chung được gắn vào menu role |
|---|---|---|
| Admin | 1–9 | Toàn bộ báo cáo và CEO V2 |
| Sales | 3, 4, 8 | 4.5.1–4.5.10; báo cáo tổng hợp chỉ hiện cho leader/supervisor |
| Marketing | 2, 3, 8 | 2.7.1–2.7.8; gồm doanh số, công việc, sản phẩm và upsale |
| Warehouse | 3, 5, 8 | 5.5.3, 5.5.9, 5.5.10; staff kho được xem báo cáo đúng khối kho |
| Accounting | 3, 6, 8 | 6.3.2–6.3.12; gồm doanh số kho, Sale, Marketing, upsale và ma trận sản phẩm |
| Allocator | 1, 2, 3, 8 | Chỉ màn chia data/khách hàng và báo cáo được phân quyền |

Một item chỉ hiển thị khi đồng thời thỏa mãn role, quyền khu vực, cấp staff/leader và report registry. Route dùng chung được đổi sang prefix của role: `/sales/reports`, `/marketing/reports`, `/warehouse/reports`, `/accounting/reports`. Không để route `/admin/reports/extra/*` rò sang menu role.

## 6. Thành phần kỹ thuật chính

- `app/Support/OrderRevenueClassifier.php`
- `app/Services/Reports/ExtraReportService.php`
- `app/Services/Reports/CeoReportService.php`
- `app/Data/ReportFilterData.php`
- `app/Services/NavigationService.php`
- `config/pushsale_navigation.php`
- `resources/js/pages/Reports/ExtraReport.jsx`
- `resources/js/pages/Admin/Reports/CeoReport.jsx`
- `resources/css/pushsale-template-six-reports.css`
- `tests/Feature/Reports/TemplateSixReportsTest.php`

## 7. Kiểm tra hồi quy quan trọng

Test V15 bao phủ:

- registry và phân quyền staff/leader theo role;
- route báo cáo dùng chung được gắn đúng menu Sales, Marketing, Warehouse và Accounting;
- menu role không lộ route báo cáo Admin;
- một contact landing + đơn bổ sung upsale vẫn chỉ là một contact;
- doanh số upsale nhất quán ở báo cáo chốt đơn, doanh số Sale, báo cáo kho và ma trận sản phẩm;
- đủ 12 nhóm doanh số, gồm thực tế, chờ đối soát và giao một phần;
- `partial_delivery` thuộc doanh thu hợp lệ;
- leader chọn member trong phạm vi không bị ép về chính leader.
