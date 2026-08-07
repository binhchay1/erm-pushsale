import enDictionary from '@/i18n/locales/en/index';
import viDictionary from '@/i18n/locales/vi/index';

/**
 * Transitional i18n bridge for legacy Pushsale templates.
 *
 * A lot of old pages still pass static Vietnamese labels directly to native
 * selects, placeholders, titles, aria-labels, and toast text. This helper keeps
 * the translation deterministic: only known UI phrases / placeholder patterns
 * are translated; business data such as product names, source names, campaign
 * names, user names, etc. pass through untouched.
 */

const PAIRS = [
    ['--Chọn--', '--Select--'],
    ['-- Chọn --', '-- Select --'],
    ['--Không chọn--', '-- None --'],
    ['-- Không chọn --', '-- None --'],
    ['--Toàn bộ--', '-- All --'],
    ['-- Tất cả --', '-- All --'],
    ['--Tất cả--', '-- All --'],
    ['--Hiển thị tất--', '-- Show all --'],
    ['--Chọn đơn vị--', '-- Select unit --'],
    ['-- Chọn đơn vị --', '-- Select unit --'],
    ['--Quyền--', '-- Role --'],
    ['-- Quyền --', '-- Role --'],
    ['-- User --', '-- User --'],
    ['--Chọn tài khoản--', '-- Select account --'],
    ['--Trạng thái đăng nhập--', '-- Login status --'],
    ['-- Trạng thái đăng nhập --', '-- Login status --'],
    ['--Trạng thái--', '-- Status --'],
    ['-- Trạng thái --', '-- Status --'],
    ['--Trạng thái áp dụng--', '-- Apply status --'],
    ['--Trạng thái kinh doanh--', '-- Business status --'],
    ['--Trạng thái giao hàng--', '-- Delivery status --'],
    ['-- Trạng thái giao hàng --', '-- Delivery status --'],
    ['--Chọn trạng thái giao hàng--', '-- Select delivery status --'],
    ['-- Chọn trạng thái giao hàng --', '-- Select delivery status --'],
    ['--TTGH--', '-- Delivery status --'],
    ['--Chọn TTGH--', '-- Select delivery status --'],
    ['--Đối soát--', '-- Reconciliation --'],
    ['-- Đối soát --', '-- Reconciliation --'],
    ['--Chọn đối soát nội bộ--', '-- Select internal reconciliation --'],
    ['--Đặt cọc--', '-- Deposit --'],
    ['--Xuất HĐĐT--', '-- E-invoice --'],
    ['--Kiểu ngày--', '-- Date type --'],
    ['--Kiểu nhóm--', '-- Team type --'],
    ['--Chọn kiểu khởi tạo--', '-- Select creation type --'],
    ['--Chọn loại nhập--', '-- Select import type --'],
    ['--Chọn trùng số--', '-- Select duplicate status --'],
    ['--TT nhận dữ liệu--', '-- Data receiving status --'],
    ['--Chọn TT nhận dữ liệu--', '-- Select data receiving status --'],
    ['--Chọn TT sử dụng--', '-- Select usage status --'],
    ['--Chuẩn Pushsale--', '-- SaleOps standard --'],
    ['--Sắp xếp--', '-- Sort --'],
    ['--Chọn kho--', '-- Select warehouse --'],
    ['-- Chọn kho --', '-- Select warehouse --'],
    ['--Kho--', '-- Warehouse --'],
    ['--Quản kho--', '-- Warehouse manager --'],
    ['--Chọn sản phẩm--', '-- Select product --'],
    ['-- Chọn sản phẩm --', '-- Select product --'],
    ['--Select product--', '-- Select product --'],
    ['--Sản phẩm--', '-- Product --'],
    ['-- Sản phẩm --', '-- Product --'],
    ['--Sản phẩm cha--', '-- Parent product --'],
    ['-- Sản phẩm cha --', '-- Parent product --'],
    ['--Sản phẩm / gói sản phẩm--', '-- Product / package --'],
    ['--Chọn sản phẩm / gói--', '-- Select product / package --'],
    ['--Chọn sản phẩm thêm vào combo--', '-- Select product to add to combo --'],
    ['--Chọn combo--', '-- Select combo --'],
    ['--Chọn phân loại--', '-- Select category --'],
    ['--Chọn thuộc tính--', '-- Select attribute --'],
    ['--Chọn giá trị--', '-- Select value --'],
    ['--Marketing--', '-- Marketing --'],
    ['--Chọn marketing--', '-- Select marketing --'],
    ['-- Chọn marketing --', '-- Select marketing --'],
    ['--Nhóm marketing--', '-- Marketing team --'],
    ['--Chọn nhóm marketing--', '-- Select marketing team --'],
    ['--Chọn nhóm Marketing--', '-- Select marketing team --'],
    ['--Chọn trưởng nhóm marketing--', '-- Select marketing leader --'],
    ['--Sale--', '-- Sales --'],
    ['--Chọn sale--', '-- Select sales --'],
    ['-- Chọn sale --', '-- Select sales --'],
    ['--Tất cả sale--', '-- All sales --'],
    ['--Nhóm Sales--', '-- Sales team --'],
    ['--Nhóm sale--', '-- Sales team --'],
    ['--Chọn nhóm sale--', '-- Select sales team --'],
    ['-- Chọn nhóm sale --', '-- Select sales team --'],
    ['--Chọn trưởng nhóm sale--', '-- Select sales leader --'],
    ['--Trưởng nhóm--', '-- Team leader --'],
    ['-- Trưởng nhóm --', '-- Team leader --'],
    ['--Trưởng nhóm Sales--', '-- Sales leader --'],
    ['--Chọn trưởng nhóm--', '-- Select team leader --'],
    ['-- Chọn trưởng nhóm --', '-- Select team leader --'],
    ['--Chọn nhóm--', '-- Select team --'],
    ['-- Chọn nhóm --', '-- Select team --'],
    ['--Nhóm--', '-- Team --'],
    ['--Tất cả nhóm--', '-- All teams --'],
    ['--Chọn đội nhóm--', '-- Select team --'],
    ['--Care đơn--', '-- Order care --'],
    ['--Chọn care đơn--', '-- Select order care --'],
    ['--Trạng thái care--', '-- Care status --'],
    ['--Chọn CSKH--', '-- Select customer care --'],
    ['--Chọn nhóm CSKH--', '-- Select customer care team --'],
    ['--TK care đơn--', '-- Order care account --'],
    ['--Chọn nguồn dữ liệu--', '-- Select data source --'],
    ['--Chọn đơn vị giao hàng--', '-- Select carrier --'],
    ['--PTGH--', '-- Shipping method --'],
    ['--Chọn PTGH--', '-- Select shipping method --'],
    ['--Phương thức giao hàng--', '-- Shipping method --'],
    ['--TK vận đơn--', '-- Shipping account --'],
    ['--Nhóm Vận đơn--', '-- Shipping group --'],
    ['--Theo dõi đơn--', '-- Order tracking --'],
    ['--In đơn--', '-- Print order --'],
    ['--Tỉnh/TP--', '-- Province/City --'],
    ['--Chọn Tỉnh/TP--', '-- Select province/city --'],
    ['-- Chọn Tỉnh/TP --', '-- Select province/city --'],
    ['--Chọn Quận/Huyện--', '-- Select district --'],
    ['-- Chọn Quận/Huyện --', '-- Select district --'],
    ['--Chọn Phường/Xã--', '-- Select ward --'],
    ['-- Chọn Phường/Xã --', '-- Select ward --'],
    ['--Chọn Xã/Phường--', '-- Select ward --'],
    ['--Tác nghiệp--', '-- Operation --'],
    ['--Chọn tác nghiệp--', '-- Select operation --'],
    ['--Chọn tác nghiệp cần--', '-- Select required operation --'],
    ['--Chọn tác nghiệp tiếp--', '-- Select next operation --'],
    ['--Chọn kết quả tác nghiệp--', '-- Select operation result --'],
    ['--Chọn trạng thái tác nghiệp--', '-- Select operation status --'],
    ['--Giữ nguyên tác nghiệp--', '-- Keep operation --'],
    ['--Kết quả--', '-- Result --'],
    ['--Kết quả XL--', '-- Processing result --'],
    ['--Chọn kết quả--', '-- Select result --'],
    ['--Trạng thái XL--', '-- Processing status --'],
    ['--Nghiệp vụ kho--', '-- Warehouse operation --'],
    ['--Biến động--', '-- Movement --'],
    ['--Toàn bộ số lượng--', '-- All quantities --'],
    ['--Trạng thái chốt đơn--', '-- Closing status --'],
    ['--Chọn khách cũ--', '-- Select returning customer --'],
    ['--Khách cũ/mới--', '-- New/returning customer --'],
    ['--Nhóm khách hàng--', '-- Customer group --'],
    ['--Tình trạng khách hàng--', '-- Customer status --'],
    ['--Số lần mua lại--', '-- Repurchase count --'],
    ['--Tháng--', '-- Month --'],
    ['--Chọn Tháng--', '-- Select month --'],
    ['--Năm--', '-- Year --'],
    ['--Tháng sinh--', '-- Birth month --'],
    ['--Giới tính--', '-- Gender --'],
    ['--Tình trạng hôn nhân--', '-- Marital status --'],
    ['--Nghề nghiệp--', '-- Occupation --'],
    ['--Tôn giáo--', '-- Religion --'],
    ['--Chức vụ--', '-- Position --'],
    ['--Loại hợp đồng--', '-- Contract type --'],
    ['--Ngôn ngữ--', '-- Language --'],
    ['--Danh mục chi phí--', '-- Expense category --'],
    ['--Danh mục nhóm chi phí--', '-- Expense group --'],
    ['--Ca làm việc--', '-- Work shift --'],
    ['-- Chọn ca làm việc --', '-- Select work shift --'],
    ['--Mẫu ghi chú--', '-- Note template --'],
    ['--Hiệu quả sử dụng--', '-- Usage effectiveness --'],
    ['--Nhóm liên kết--', '-- Link group --'],
    ['--Chọn cấu hình chia số--', '-- Select allocation rule --'],
    ['--Chọn phân bổ--', '-- Select allocation --'],

    ['Lọc', 'Filter'],
    ['Tìm', 'Search'],
    ['Tìm nhanh', 'Quick search'],
    ['Tìm nâng cao', 'Advanced search'],
    ['Lưu', 'Save'],
    ['Lưu thay đổi', 'Save changes'],
    ['Huỷ', 'Cancel'],
    ['Hủy', 'Cancel'],
    ['Đồng ý', 'Confirm'],
    ['Xác nhận', 'Confirm'],
    ['Áp dụng', 'Apply'],
    ['Quay lại', 'Back'],
    ['Tiếp tục', 'Continue'],
    ['Tạo mới', 'Create new'],
    ['Thêm mới', 'Add new'],
    ['Thêm nhiều', 'Bulk add'],
    ['Tạo TK', 'Create accounts'],
    ['Chỉnh sửa', 'Edit'],
    ['Cấu hình', 'Configuration'],
    ['Cấu hình hệ thống', 'System settings'],
    ['Đang tải…', 'Loading…'],
    ['Đang tải...', 'Loading...'],
    ['Đang lưu', 'Saving'],
    ['Đang lưu…', 'Saving…'],
    ['Đang xử lý…', 'Processing…'],
    ['Đang cập nhật', 'Updating'],
    ['Đang đồng bộ', 'Syncing'],
    ['Không tải được màn hình', 'Could not load screen'],
    ['chưa tồn tại trong bundle hiện tại. Hãy chạy', 'does not exist in the current bundle. Please run'],
    ['và refresh trang.', 'and refresh the page.'],

    ['Tên', 'Name'],
    ['Mã', 'Code'],
    ['STT', 'No.'],
    ['ID', 'ID'],
    ['Mô tả', 'Description'],
    ['Ghi chú', 'Note'],
    ['Ghi chú:', 'Note:'],
    ['Chỉ dẫn:', 'Hint:'],
    ['Trạng thái', 'Status'],
    ['Tình trạng', 'Status'],
    ['Thao tác', 'Actions'],
    ['Hành động', 'Action'],
    ['Chức năng', 'Actions'],
    ['Chức vụ', 'Position'],
    ['Vai trò', 'Role'],
    ['Tài khoản', 'Account'],
    ['Tài khoản:', 'Account:'],
    ['Mật khẩu', 'Password'],
    ['Nhập lại mật khẩu', 'Confirm password'],
    ['Để trống nếu không đổi', 'Leave blank if unchanged'],
    ['Số ĐT', 'Phone'],
    ['Số điện thoại', 'Phone number'],
    ['Điện thoại', 'Phone'],
    ['Địa chỉ', 'Address'],
    ['Địa chỉ giao', 'Delivery address'],
    ['Địa chỉ lấy hàng', 'Pickup address'],
    ['Người nhận', 'Recipient'],
    ['Người nhận:', 'Recipient:'],
    ['Người thực hiện', 'Actor'],
    ['Đối tượng', 'Object'],
    ['Thời gian', 'Time'],
    ['Từ ngày', 'From date'],
    ['Đến ngày', 'To date'],
    ['Ngày lọc', 'Filter date'],
    ['Khoảng ngày', 'Date range'],
    ['Chọn khoảng ngày', 'Select date range'],
    ['7 ngày vừa qua', 'Last 7 days'],
    ['Hôm qua', 'Yesterday'],
    ['Tùy chỉnh', 'Custom'],
    ['Tháng trước', 'Previous month'],
    ['Tháng sau', 'Next month'],

    ['Báo cáo', 'Report'],
    ['Báo cáo kho', 'Warehouse report'],
    ['Báo cáo làm việc', 'Work report'],
    ['Báo cáo công việc', 'Work report'],
    ['Bảng tổng hợp chốt đơn', 'Order closing summary'],
    ['Bảng tổng hợp chờ xuất theo ngày', 'Daily pending export summary'],
    ['Bảng tổng hợp kết quả chia data trong ngày', 'Daily data allocation result summary'],
    ['Báo cáo kinh doanh hệ thống', 'System business report'],
    ['Báo cáo lịch hẹn telesales', 'Telesales appointment report'],
    ['Biểu đồ thống kê theo khung giờ', 'Hourly statistics chart'],
    ['Dữ liệu', 'Data'],
    ['Nguồn dữ liệu', 'Data source'],
    ['Tên nguồn dữ liệu', 'Data source name'],
    ['Url nguồn dữ liệu', 'Data source URL'],
    ['Tên nguồn kết nối', 'Connection source name'],
    ['Ngày data về', 'Data received date'],
    ['Ngày data về hệ thống', 'Data received date'],
    ['Nhận data', 'Receive data'],
    ['Nhận dữ liệu', 'Receive data'],
    ['Đang sử dụng', 'In use'],
    ['Đang áp dụng', 'Applied'],
    ['Đang bật', 'Enabled'],
    ['Đang tắt', 'Disabled'],
    ['Bật', 'On'],
    ['Tắt', 'Off'],
    ['Chỉ xem', 'View only'],
    ['Toàn quyền', 'Full access'],

    ['Sản phẩm', 'Product'],
    ['Tên sản phẩm', 'Product name'],
    ['Sản phẩm - Số lượng - Đơn giá', 'Product - Quantity - Unit price'],
    ['Sản phẩm (chưa map)', 'Product (unmapped)'],
    ['Gói sản phẩm', 'Product package'],
    ['Import sản phẩm', 'Import products'],
    ['Danh sách sản phẩm kho', 'Warehouse product list'],
    ['Đơn vị', 'Unit'],
    ['Đơn vị tính', 'Unit'],
    ['Đơn giá', 'Unit price'],
    ['Đơn giá (VND)', 'Unit price (VND)'],
    ['Thành tiền', 'Amount'],
    ['Tổng giá trị', 'Total value'],
    ['Tổng giá trị đơn thành công từ', 'Successful order value from'],
    ['Tổng tiền đơn', 'Order total'],
    ['Tổng tiền đơn:', 'Order total:'],
    ['Tổng tiền đơn hàng', 'Order total'],
    ['Thành tiền đơn hàng', 'Order amount'],
    ['Tổng cộng:', 'Total:'],
    ['Phải thu của khách:', 'Amount to collect:'],
    ['Khách đặt cọc', 'Customer deposit'],
    ['Khách đã đặt cọc:', 'Customer deposit:'],
    ['Tiền cọc', 'Deposit'],
    ['Tiền đã thu (giao một phần/COD)', 'Collected amount (partial delivery/COD)'],
    ['Phí ship thu khách', 'Shipping fee charged to customer'],
    ['Phí VC/Tổng tiền', 'Shipping fee/Total amount'],
    ['Phí VC thu của khách (v):', 'Shipping fee charged to customer (v):'],
    ['Chiết khấu sản phẩm (v):', 'Product discount (v):'],
    ['Tính phí VC', 'Calculate shipping fee'],
    ['Tính CK', 'Calculate discount'],
    ['Thêm SP', 'Add product'],
    ['Lưu đơn', 'Save order'],
    ['Tạo đơn', 'Create order'],
    ['Đóng đơn', 'Close order'],
    ['Chốt đơn', 'Close order'],
    ['Đã chốt', 'Closed'],
    ['Chờ xuất', 'Pending export'],
    ['Đã giao', 'Delivered'],
    ['Đang giao', 'Delivering'],
    ['Thành công', 'Success'],
    ['Tạm dừng', 'Paused'],
    ['Đang chạy', 'Running'],
    ['Chờ duyệt', 'Pending approval'],
    ['Đã duyệt', 'Approved'],
    ['Duyệt', 'Approve'],
    ['Từ chối', 'Reject'],
    ['Nháp', 'Draft'],
    ['Hoàn thành', 'Completed'],
    ['Không hợp lệ', 'Invalid'],
    ['Cần rà soát', 'Needs review'],
    ['Đã xử lý', 'Processed'],
    ['Đã xử lý hợp lệ', 'Processed valid'],
    ['Gửi trùng', 'Duplicate submissions'],
    ['SĐT duy nhất', 'Unique phones'],
    ['Không hợp lệ', 'Invalid'],
    ['Lỗi xử lý', 'Processing errors'],

    ['Kho', 'Warehouse'],
    ['Tên kho', 'Warehouse name'],
    ['TÊN KHO', 'WAREHOUSE NAME'],
    ['Danh sách kho', 'Warehouse list'],
    ['Kho tác nghiệp', 'Warehouse operations'],
    ['Tồn kho', 'Inventory'],
    ['Nhập kho', 'Stock in'],
    ['Xuất kho', 'Stock out'],
    ['Mã lô', 'Batch code'],
    ['Vị trí', 'Location'],
    ['Mã vị trí', 'Location code'],
    ['Đầu kỳ', 'Opening'],
    ['Cuối kỳ', 'Closing'],
    ['Có biến động', 'Has movement'],
    ['Nghiệp vụ kho', 'Warehouse operation'],
    ['Biến động', 'Movement'],
    ['Ngày hết hạn', 'Expiry date'],
    ['Báo cáo kho', 'Warehouse report'],
    ['Mã biên bản', 'Record code'],
    ['Tên biên bản', 'Record name'],
    ['Ngày biên bản', 'Record date'],
    ['Ghi chú biên bản', 'Record note'],
    ['SL dự kiến', 'Expected qty'],
    ['SL nhận', 'Received qty'],
    ['SL Tổng', 'Total qty'],
    ['Số lượng trong đơn', 'Order quantity'],
    ['Số lượng tách', 'Split quantity'],
    ['Còn bán được', 'Sellable'],
    ['Hư hỏng', 'Damaged'],
    ['Hỏng', 'Damaged'],
    ['Thiếu', 'Missing'],
    ['Thiếu/mất', 'Missing/lost'],
    ['Chờ kiểm tra', 'Pending check'],
    ['Nhập lại kho', 'Return to stock'],
    ['Chưa có sự kiện từ đơn vị vận chuyển.', 'No carrier events yet.'],

    ['Sale', 'Sales'],
    ['Telesale', 'Telesales'],
    ['Sales', 'Sales'],
    ['Nhóm sale', 'Sales team'],
    ['Nhóm Sales', 'Sales team'],
    ['Trưởng nhóm', 'Team leader'],
    ['Trưởng nhóm/QL trực tiếp', 'Team leader/direct manager'],
    ['Đội nhóm', 'Team'],
    ['Tác nghiệp sale', 'Sales operation'],
    ['Sale tác nghiệp', 'Sales workspace'],
    ['Tác nghiệp', 'Operation'],
    ['Tác nghiệp / Kết quả', 'Operation / Result'],
    ['Tác nghiệp cần', 'Required operation'],
    ['Kết quả', 'Result'],
    ['Lịch sử tác nghiệp', 'Operation history'],
    ['Chưa tác nghiệp', 'Not operated'],
    ['Thu gọn bộ lọc', 'Collapse filters'],
    ['Mở bộ lọc nâng cao', 'Open advanced filters'],
    ['Thu gọn bộ lọc nâng cao', 'Collapse advanced filters'],
    ['Tỉ lệ', 'Rate'],
    ['Tỷ lệ', 'Rate'],
    ['Tỉ lệ (%)', 'Rate (%)'],
    ['Tỷ lệ (%)', 'Rate (%)'],
    ['Tỷ trọng', 'Share'],
    ['Tỷ lệ chốt', 'Close rate'],
    ['Tỷ lệ chốt (%)', 'Close rate (%)'],
    ['% chốt đơn', 'Close rate'],
    ['Đơn chốt', 'Closed orders'],
    ['Số đơn chốt', 'Closed orders'],
    ['Tổng đơn chốt', 'Total closed orders'],
    ['Tổng đơn', 'Total orders'],
    ['Đơn đã chốt', 'Closed orders'],
    ['Số đơn', 'Orders'],
    ['Mã đơn', 'Order code'],
    ['Mã đơn mới', 'New order code'],
    ['Mã đơn đối tác', 'Partner order code'],
    ['Mã vận đơn', 'Waybill code'],
    ['Mã giao vận', 'Delivery code'],
    ['Đơn vị vận chuyển', 'Carrier'],
    ['Đơn vị giao hàng', 'Carrier'],
    ['Đơn vị giao vận', 'Carrier'],
    ['Đơn vị giao vận', 'Carrier'],
    ['Giao vận', 'Shipping'],
    ['Kho / Giao vận', 'Warehouse / Shipping'],
    ['Phương thức giao hàng', 'Shipping method'],
    ['Giao hàng bằng', 'Ship by'],
    ['Giao hàng ghi chú', 'Shipping note'],
    ['Ghi chú giao hàng', 'Shipping note'],
    ['Ngày muốn nhận hàng', 'Desired delivery date'],
    ['Ngày giao hàng mong muốn', 'Desired delivery date'],
    ['Đăng đơn', 'Create shipment'],
    ['Đăng vận đơn', 'Create waybill'],
    ['Đã đăng vận đơn', 'Waybill created'],
    ['Cập nhật trạng thái giao hàng', 'Update delivery status'],
    ['Cập nhật trạng thái giao hàng theo mã đơn', 'Update delivery status by order code'],
    ['Cập nhật trạng thái giao hàng Excel', 'Update delivery status by Excel'],
    ['Cập nhật nhiều đơn theo mã Pushsale', 'Bulk update by Pushsale order code'],
    ['Thêm đơn vào biên bản', 'Add order to handover record'],
    ['Xuất hóa đơn điện tử theo mã đơn', 'Issue e-invoice by order code'],
    ['Mở chức năng', 'Open actions'],
    ['Đóng chức năng', 'Close actions'],
    ['In đơn', 'Print order'],
    ['In đơn mẫu Shopee', 'Print Shopee template'],
    ['In đơn mẫu TikTok', 'Print TikTok template'],
    ['In đơn mẫu GHTK', 'Print GHTK template'],
    ['In đơn mẫu J&T', 'Print J&T template'],
    ['In đơn mẫu SPX', 'Print SPX template'],

    ['Khách hàng', 'Customer'],
    ['Hồ sơ khách hàng', 'Customer profile'],
    ['Họ tên', 'Full name'],
    ['Họ tên khách hàng', 'Customer full name'],
    ['Họ tên / Số điện thoại', 'Name / Phone'],
    ['Họ tên, số điện thoại', 'Name, phone'],
    ['Khách:', 'Customer:'],
    ['Khách cũ', 'Returning customer'],
    ['Khách mới', 'New customer'],
    ['Khách hàng cũ', 'Returning customer'],
    ['Nhóm khách hàng', 'Customer group'],
    ['Tình trạng khách hàng', 'Customer status'],
    ['Tin nhắn', 'Message'],
    ['Tin nhắn nội bộ', 'Internal message'],
    ['Nội dung tin nhắn nội bộ', 'Internal message content'],
    ['Lịch sử mua hàng', 'Purchase history'],
    ['Không tải được lịch sử mua hàng.', 'Could not load purchase history.'],
    ['Không tải được lịch sử xem data.', 'Could not load data view history.'],
    ['Chỉ hiển thị 200 lịch sử gần nhất.', 'Only the latest 200 history records are shown.'],
    ['Chỉ xem lịch sử của đơn hiện tại', 'View only current order history'],
    ['Xem danh sách lịch sử các đơn cùng số điện thoại', 'View history of orders with the same phone number'],
    ['Hành động xem', 'View action'],
    ['* Hệ thống chỉ lưu và hiển thị lịch sử xem data trong 30 ngày gần nhất.', '* The system only stores and displays data view history for the latest 30 days.'],

    ['Marketing', 'Marketing'],
    ['Kênh quảng cáo', 'Ad channel'],
    ['Kênh khác', 'Other channel'],
    ['Ngân sách', 'Budget'],
    ['Tên chiến dịch', 'Campaign name'],
    ['Tên chiến dịch (*)', 'Campaign name (*)'],
    ['Trạng thái chiến dịch', 'Campaign status'],
    ['Ngày bắt đầu (*)', 'Start date (*)'],
    ['Ngày kết thúc (*)', 'End date (*)'],
    ['Danh sách gói tin landing', 'Landing packet list'],
    ['Tổng gói tin', 'Total packets'],
    ['Tổng gói tin nhận được', 'Total received packets'],
    ['Gói tin nhận được', 'Received packets'],
    ['Gói tin chính', 'Main packets'],
    ['Gói tin upsale', 'Upsale packets'],
    ['Landing / nguồn gửi', 'Landing / source'],
    ['Đơn liên kết', 'Linked order'],
    ['Ưu tiên sale', 'Priority sales'],
    ['Không tự chia số', 'Do not auto allocate'],
    ['Chưa chọn sản phẩm', 'No product selected'],
    ['Chọn sản phẩm / gói sản phẩm', 'Select product / package'],
    ['Chọn sản phẩm / gói sản phẩm', 'Select product / package'],
    ['Nguồn hiện trong form nhập data thủ công', 'Source shown in manual data form'],
    ['Đã duyệt — Tích = auto duyệt ngay.', 'Approved — checked means auto approve immediately.'],
    ['Bỏ tích = chưa duyệt.', 'Unchecked means not approved.'],

    ['Nhân sự', 'HR'],
    ['Mã nhân viên', 'Employee code'],
    ['Ca làm việc', 'Work shift'],
    ['Lương cứng', 'Base salary'],
    ['Tổng thu nhập', 'Total income'],
    ['Chức vụ', 'Position'],
    ['Tính toán phân loại khách hàng', 'Calculate customer segments'],
    ['Thiết lập phân loại khách hàng', 'Customer segment settings'],
    ['Chưa có tác vụ tính toán phân loại', 'No segment calculation task yet'],
    ['Phân loại mới', 'New segment'],
    ['Tên phân loại', 'Segment name'],
    ['THÔNG TIN ĐIỀU KIỆN KHÁCH HÀNG THUỘC CHIẾN DỊCH', 'CUSTOMER CONDITIONS FOR THE CAMPAIGN'],

    ['Sàn thương mại điện tử', 'E-commerce platform'],
    ['Loại sàn', 'Platform type'],
    ['Tên shop', 'Shop name'],
    ['Danh sách kết nối sàn thương mại điện tử', 'E-commerce shop connections'],
    ['Danh sách đơn hàng lỗi', 'Failed order list'],
    ['Kết nối', 'Connect'],
    ['Đồng bộ', 'Sync'],
    ['Hướng dẫn import contact', 'Contact import guide'],
    ['Kiểm trùng hệ thống', 'Check duplicates in system'],
    ['Tải file mẫu', 'Download template'],
    ['Chọn file...', 'Choose file...'],
    ['Chọn file Excel trước.', 'Choose an Excel file first.'],

    ['Tìm kiếm', 'Search'],
    ['Tải lại', 'Reload'],
    ['Xóa lọc', 'Clear filters'],
    ['Đặt lại', 'Reset'],
    ['Cấu hình hiển thị', 'Display settings'],
    ['Xuất dữ liệu', 'Export data'],
    ['Xuất Excel', 'Export Excel'],
    ['Không có dữ liệu.', 'No data.'],
    ['Không có dữ liệu', 'No data'],
    ['No matching data.', 'No matching data.'],
    ['Chọn dòng', 'Select row'],
    ['Cập nhật', 'Update'],
    ['Cập nhật & duyệt', 'Update & approve'],
    ['Sửa', 'Edit'],
    ['Xóa', 'Delete'],
    ['Sao chép', 'Copy'],
    ['Bỏ chọn', 'Clear selection'],
    ['Đóng', 'Close'],
    ['Chọn file', 'Choose file'],
    ['Chi tiết', 'Details'],
    ['Biểu đồ', 'Chart'],
    ['Chú thích', 'Note'],
    ['Chức năng', 'Actions'],
    ['Mở rộng UTM', 'Expand UTM'],
    ['Cũ nhất', 'Oldest'],
    ['Mới nhất', 'Newest'],
    ['Giá tăng dần', 'Price ascending'],
    ['Giá giảm dần', 'Price descending'],
    ['Sau chiết khấu', 'After discount'],
    ['Trước chiết khấu', 'Before discount'],
    ['Ngày', 'Day'],
    ['Tuần', 'Week'],
    ['Tháng', 'Month'],
    ['Quý', 'Quarter'],
    ['Năm', 'Year'],
    ['Có', 'Yes'],
    ['Không', 'No'],
    ['Có nhận data', 'Receive data'],
    ['Không nhận data', 'Do not receive data'],
    ['Có nhận dữ liệu', 'Receive data'],
    ['Không nhận dữ liệu', 'Do not receive data'],
    ['Đang kinh doanh', 'Active for sale'],
    ['Ngừng kinh doanh', 'Inactive for sale'],
    ['Được sử dụng', 'Allowed'],
    ['Không sử dụng', 'Not allowed'],
    ['Không áp dụng', 'Not applied'],
    ['Không đổi', 'No change'],
    ['Chờ', 'Waiting'],
    ['Chưa xử lý', 'Unprocessed'],
    ['Lỗi', 'Error'],
    ['Chưa liên kết', 'Not linked'],
    ['Khách mới', 'New customer'],
    ['Khách hàng cũ', 'Returning customer'],
    ['Khách hàng khiếu nại', 'Complaint customer'],
    ['Khách cũ / trùng cần xử lý', 'Returning/duplicate customer to process'],
    ['Họ tên, số điện thoại', 'Name, phone'],
    ['Họ tên, số điện thoại, mã đơn...', 'Name, phone, order code...'],
    ['Tên tài khoản/Mã truy cập', 'Account name/access code'],
    ['IPAddress/Mã truy cập/Tài khoản', 'IP address/access code/account'],
    ['Tên nhóm, mã nhóm, trưởng nhóm', 'Team name, team code, leader'],
    ['Từ khóa', 'Keyword'],
    ['Nhập từ khóa', 'Enter keyword'],
    ['Nhập từ khóa tìm kiếm', 'Enter search keyword'],
    ['Nhập từ khóa để tìm kiếm', 'Enter keyword to search'],
    ['Tên source / Tài khoản marketing', 'Source name / marketing account'],
    ['Tên nguồn dữ liệu', 'Data source name'],
    ['Tìm theo tên / mã', 'Search by name / code'],
    ['Tìm theo tên, mã SKU hoặc loại...', 'Search by name, SKU, or type...'],
    ['Không tìm thấy sản phẩm/gói phù hợp', 'No matching product/package found'],
    ['Tìm sản phẩm/gói theo tên hoặc mã...', 'Search product/package by name or code...'],
    ['Thêm', 'Add'],
    ['Tìm kiếm (Tối đa 200 ký tự)', 'Search (max 200 characters)'],
    ['Cho xem hàng, không được thử, không bóc seal...', 'Allow inspection, no trial, do not open seal...'],
    ['Số nhà/đường/ngõ/ngách', 'House number/street/alley'],
    ['Tỉnh/TP', 'Province/City'],
    ['Quận/Huyện', 'District'],
    ['Phường/Xã', 'Ward'],
    ['Thủ công', 'Manual'],
    ['Nhập thủ công', 'Manual input'],
    ['Giao hàng thủ công', 'Manual delivery'],
    ['Giao hàng tiêu chuẩn', 'Standard delivery'],
    ['Giao hàng nhanh', 'Express delivery'],
    ['Đơn vị vận chuyển', 'Carrier'],
    ['Giao ngay', 'Deliver now'],
    ['Chờ vận đơn', 'Waiting for waybill'],
    ['Hoãn giao hàng', 'Postponed delivery'],
    ['Hủy vận đơn', 'Cancel waybill'],
    ['Hủy đăng đơn', 'Cancel posting'],
    ['Đã hoàn', 'Returned'],
    ['Đang hoàn', 'Returning'],
    ['Đã giao hàng', 'Delivered'],
    ['Đã thanh toán', 'Paid'],
    ['Giao hàng 1 phần', 'Partially delivered'],
    ['Không lấy được hàng', 'Pickup failed'],
    ['Đã xuất HĐĐT', 'E-invoice issued'],
    ['Chưa xuất HĐĐT', 'E-invoice not issued'],
    ['Tổng số lượng sản phẩm không giới hạn', 'Unlimited product quantity'],
    ['Tổng số lượng sản phẩm từ', 'Product quantity from'],
    ['Tổng số lượng sản phẩm đến', 'Product quantity to'],
    ['Sản phẩm', 'Product'],
    ['Gói sản phẩm', 'Product package'],
    ['Tất cả', 'All'],
    ['Tất cả sản phẩm', 'All products'],
    ['Tất cả nhân sự đều có quyền', 'All staff are allowed'],
    ['Không cho nhân sự sử dụng sản phẩm này', 'Do not allow staff to use this product'],
    ['Không cho nhân sự sử dụng', 'Do not allow staff'],
    ['Tất cả sale đều có quyền', 'All sales are allowed'],
    ['Không cho sale sử dụng sản phẩm này', 'Do not allow sales to use this product'],
    ['Tất cả marketing đều có quyền', 'All marketing users are allowed'],
    ['Không cho marketing sử dụng sản phẩm này', 'Do not allow marketing to use this product'],

    ['Đã copy.', 'Copied.'],
    ['Không copy được.', 'Could not copy.'],
    ['Đã cập nhật.', 'Updated.'],
    ['Không cập nhật được.', 'Could not update.'],
    ['Đã gỡ nguồn.', 'Source removed.'],
    ['Chọn ít nhất một nguồn dữ liệu.', 'Select at least one data source.'],
    ['Vui lòng chọn nguồn dữ liệu.', 'Please select a data source.'],
    ['Vui lòng nhập họ tên khách hàng.', 'Please enter customer name.'],
    ['Vui lòng nhập số điện thoại.', 'Please enter phone number.'],
    ['Vui lòng chọn ít nhất một sản phẩm hoặc combo.', 'Please select at least one product or combo.'],
    ['Vui lòng kiểm tra các trường bắt buộc.', 'Please check the required fields.'],
    ['Vui lòng kiểm tra lại các trường bắt buộc.', 'Please recheck the required fields.'],
    ['Vui lòng nhập các trường bắt buộc.', 'Please fill required fields.'],
    ['Điền đủ các trường bắt buộc.', 'Fill in all required fields.'],
    ['Chọn file Excel trước.', 'Choose an Excel file first.'],
    ['Vui lòng chọn file Excel cần import.', 'Please choose the Excel file to import.'],
    ['Upload file trước khi cập nhật.', 'Upload a file before updating.'],
    ['Đã gửi file import.', 'Import file uploaded.'],
    ['Đã xuất file.', 'File exported.'],
    ['Đang xuất Excel, vui lòng đợi xong trước khi bấm tiếp.', 'Exporting Excel, please wait before clicking again.'],
    ['Đã xóa data.', 'Data deleted.'],
    ['Đã xóa dữ liệu upload.', 'Uploaded data deleted.'],
    ['Đã xóa cấu hình đã chọn.', 'Selected configuration deleted.'],
    ['Không xóa được cấu hình.', 'Could not delete configuration.'],
    ['Không xóa được cấu hình hóa đơn.', 'Could not delete invoice configuration.'],
    ['Không xóa được số blacklist.', 'Could not delete blacklisted number.'],
    ['Vui lòng kiểm tra lại thông tin blacklist.', 'Please recheck blacklist information.'],
    ['Nhập nội dung tin nhắn nội bộ.', 'Enter the internal message content.'],
    ['Đã lưu tin nhắn nội bộ.', 'Internal message saved.'],
    ['Chọn ít nhất một Sale nhận data.', 'Select at least one sales user to receive data.'],
    ['Nhập số lượng data cần phân bổ cho ít nhất một sản phẩm.', 'Enter the data quantity to allocate for at least one product.'],
    ['Đã phân bổ data cho Sale.', 'Data allocated to sales.'],
    ['Không có đơn để thêm vào biên bản.', 'No orders to add to the handover record.'],
    ['Không có đơn để in.', 'No orders to print.'],
    ['Không có đơn để xuất.', 'No orders to export.'],
    ['Không có đơn để hủy. Chọn đơn hoặc đảm bảo trang hiện tại có dữ liệu.', 'No orders to cancel. Select orders or make sure the current page has data.'],
    ['Không có đơn để in. Chọn đơn hoặc đảm bảo trang hiện tại có dữ liệu theo bộ lọc.', 'No orders to print. Select orders or make sure the current page has data for the filter.'],
    ['Không có đơn để xuất. Chọn đơn hoặc đảm bảo trang hiện tại có dữ liệu.', 'No orders to export. Select orders or make sure the current page has data.'],
    ['Không có đơn để xuất HĐĐT. Chọn đơn hoặc lọc có dữ liệu trên trang.', 'No orders to issue e-invoices. Select orders or filter to a page with data.'],
    ['Không có đơn đủ điều kiện tạo vận đơn trên trang / lựa chọn hiện tại.', 'No eligible orders to create waybills for the current page/selection.'],
    ['Chọn trạng thái giao hàng.', 'Select a delivery status.'],
    ['Chọn kho và địa chỉ giao hàng trước khi tính phí VC.', 'Select warehouse and delivery address before calculating shipping fee.'],
    ['Vui lòng lưu đơn trước khi tính phí vận chuyển.', 'Please save the order before calculating shipping fee.'],
    ['Phí VC tạm tính: nhập thủ công hoặc chốt đơn qua kho để tính theo đơn vị vận chuyển.', 'Estimated shipping fee: enter manually or close via warehouse to calculate by carrier.'],
    ['Đã tiếp nhận đơn mới.', 'New order received.'],
    ['Đã cập nhật đơn.', 'Order updated.'],
    ['Đã chốt đơn và sinh mã đơn.', 'Order closed and order code generated.'],
    ['Đã tính chiết khấu theo sản phẩm.', 'Product discount calculated.'],
    ['Đang bật tự nhập chiết khấu đơn.', 'Manual order discount input is enabled.'],
    ['Đã làm mới danh sách sản phẩm.', 'Product list refreshed.'],
    ['Đã cập nhật ngày muốn nhận hàng.', 'Desired delivery date updated.'],
    ['Đã xử lý chốt các đơn được chọn.', 'Selected orders processed for closing.'],
    ['Đã làm mới bản in.', 'Print preview refreshed.'],
    ['Giá trị đơn hàng không hợp lệ.', 'Invalid order value.'],
    ['Giá trị cấu hình không hợp lệ.', 'Invalid configuration value.'],
    ['Nhập tên chiến dịch', 'Enter campaign name'],
    ['Đã tạo chiến dịch', 'Campaign created'],
    ['Đã cập nhật chiến dịch', 'Campaign updated'],
    ['Đã xóa chiến dịch', 'Campaign deleted'],
    ['Chọn tài khoản Marketing phụ trách Fanpage.', 'Select the marketing account responsible for the Fanpage.'],
    ['Nhập PageID và tên Fanpage.', 'Enter PageID and Fanpage name.'],
    ['Select', 'Chọn'],
    ['Search', 'Tìm kiếm'],
    ['After discount', 'Sau chiết khấu'],
    ['Before discount', 'Trước chiết khấu'],
    ['Day', 'Ngày'],
    ['Week', 'Tuần'],
    ['Month', 'Tháng'],
    ['Year', 'Năm'],
    ['Manual', 'Thủ công'],
    ['Standard delivery', 'Giao hàng tiêu chuẩn'],
    ['Express delivery', 'Giao hàng nhanh'],
    ['Carrier', 'Đơn vị vận chuyển'],
];

const TERM_PAIRS = [
    ['đơn vị', 'unit'],
    ['quyền', 'role'],
    ['user', 'user'],
    ['tên', 'name'],
    ['mã', 'code'],
    ['ghi chú', 'note'],
    ['mô tả', 'description'],
    ['thao tác', 'actions'],
    ['hành động', 'action'],
    ['chức năng', 'actions'],
    ['số điện thoại', 'phone number'],
    ['sđt', 'phone'],
    ['điện thoại', 'phone'],
    ['địa chỉ', 'address'],
    ['người nhận', 'recipient'],
    ['người thực hiện', 'actor'],
    ['đối tượng', 'object'],
    ['thời gian', 'time'],
    ['từ ngày', 'from date'],
    ['đến ngày', 'to date'],
    ['khoảng ngày', 'date range'],
    ['dữ liệu', 'data'],
    ['data', 'data'],
    ['kênh quảng cáo', 'ad channel'],
    ['ngân sách', 'budget'],
    ['chiến dịch', 'campaign'],
    ['tên chiến dịch', 'campaign name'],
    ['đơn hàng', 'order'],
    ['mã đơn', 'order code'],
    ['mã vận đơn', 'waybill code'],
    ['mã giao vận', 'delivery code'],
    ['đơn giá', 'unit price'],
    ['thành tiền', 'amount'],
    ['tổng tiền', 'total amount'],
    ['chiết khấu', 'discount'],
    ['phí vận chuyển', 'shipping fee'],
    ['phí vc', 'shipping fee'],
    ['tồn kho', 'inventory'],
    ['nhập kho', 'stock in'],
    ['xuất kho', 'stock out'],
    ['mã lô', 'batch code'],
    ['vị trí', 'location'],
    ['đầu kỳ', 'opening'],
    ['cuối kỳ', 'closing'],
    ['tình trạng', 'status'],
    ['vai trò', 'role'],
    ['nhân sự', 'HR'],
    ['mã nhân viên', 'employee code'],
    ['hồ sơ khách hàng', 'customer profile'],
    ['tin nhắn', 'message'],
    ['tin nhắn nội bộ', 'internal message'],
    ['lịch sử tác nghiệp', 'operation history'],
    ['lịch sử mua hàng', 'purchase history'],
    ['báo cáo', 'report'],
    ['bảng tổng hợp', 'summary table'],
    ['xem hàng', 'inspection'],
    ['bảo hiểm', 'insurance'],
    ['hóa đơn điện tử', 'e-invoice'],
    ['hđđt', 'e-invoice'],
    ['tài khoản', 'account'],
    ['trạng thái đăng nhập', 'login status'],
    ['trạng thái giao hàng', 'delivery status'],
    ['trạng thái kinh doanh', 'business status'],
    ['trạng thái tác nghiệp', 'operation status'],
    ['trạng thái', 'status'],
    ['đối soát nội bộ', 'internal reconciliation'],
    ['đối soát', 'reconciliation'],
    ['đặt cọc', 'deposit'],
    ['xuất hđđt', 'e-invoice'],
    ['kiểu ngày', 'date type'],
    ['kiểu nhóm', 'team type'],
    ['nguồn dữ liệu', 'data source'],
    ['sản phẩm / gói sản phẩm', 'product / package'],
    ['sản phẩm / gói', 'product / package'],
    ['sản phẩm cha', 'parent product'],
    ['sản phẩm thêm vào combo', 'product to add to combo'],
    ['sản phẩm', 'product'],
    ['combo', 'combo'],
    ['phân loại', 'category'],
    ['thuộc tính', 'attribute'],
    ['giá trị', 'value'],
    ['marketing', 'marketing'],
    ['nhóm marketing', 'marketing team'],
    ['trưởng nhóm marketing', 'marketing leader'],
    ['sale', 'sales'],
    ['nhóm sale', 'sales team'],
    ['nhóm sales', 'sales team'],
    ['trưởng nhóm sale', 'sales leader'],
    ['trưởng nhóm sales', 'sales leader'],
    ['trưởng nhóm', 'team leader'],
    ['nhóm', 'team'],
    ['đội nhóm', 'team'],
    ['cskh', 'customer care'],
    ['nhóm cskh', 'customer care team'],
    ['care đơn', 'order care'],
    ['trạng thái care', 'care status'],
    ['kho', 'warehouse'],
    ['quản kho', 'warehouse manager'],
    ['đơn vị giao hàng', 'carrier'],
    ['ptgh', 'shipping method'],
    ['phương thức giao hàng', 'shipping method'],
    ['theo dõi đơn', 'order tracking'],
    ['tỉnh/tp', 'province/city'],
    ['quận/huyện', 'district'],
    ['phường/xã', 'ward'],
    ['xã/phường', 'ward'],
    ['tác nghiệp cần', 'required operation'],
    ['tác nghiệp tiếp', 'next operation'],
    ['tác nghiệp', 'operation'],
    ['kết quả tác nghiệp', 'operation result'],
    ['kết quả', 'result'],
    ['kết quả xl', 'processing result'],
    ['trạng thái xl', 'processing status'],
    ['nghiệp vụ kho', 'warehouse operation'],
    ['biến động', 'movement'],
    ['khách cũ', 'returning customer'],
    ['khách cũ/mới', 'new/returning customer'],
    ['nhóm khách hàng', 'customer group'],
    ['tình trạng khách hàng', 'customer status'],
    ['số lần mua lại', 'repurchase count'],
    ['tháng sinh', 'birth month'],
    ['tháng', 'month'],
    ['năm', 'year'],
    ['giới tính', 'gender'],
    ['tình trạng hôn nhân', 'marital status'],
    ['nghề nghiệp', 'occupation'],
    ['tôn giáo', 'religion'],
    ['chức vụ', 'position'],
    ['loại hợp đồng', 'contract type'],
    ['ngôn ngữ', 'language'],
    ['danh mục chi phí', 'expense category'],
    ['danh mục nhóm chi phí', 'expense group'],
    ['ca làm việc', 'work shift'],
    ['mẫu ghi chú', 'note template'],
    ['hiệu quả sử dụng', 'usage effectiveness'],
    ['nhóm liên kết', 'link group'],
    ['cấu hình chia số', 'allocation rule'],
    ['phân bổ', 'allocation'],
    ['loại nhập', 'import type'],
    ['kiểu khởi tạo', 'creation type'],
    ['tt nhận dữ liệu', 'data receiving status'],
    ['tt sử dụng', 'usage status'],
    ['toàn bộ số lượng', 'all quantities'],
];

function normalize(value) {
    return String(value ?? '')
        .replace(/\u00a0/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

function localeOf(locale) {
    if (locale === 'en' || locale === 'vi') return locale;
    if (typeof document !== 'undefined') {
        const htmlLocale = document.documentElement.lang?.slice(0, 2);
        if (htmlLocale === 'en' || htmlLocale === 'vi') return htmlLocale;
    }
    return 'vi';
}

const VIETNAMESE_RE = /[ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸĐàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/;

function hasVietnamese(value) {
    return VIETNAMESE_RE.test(String(value ?? ''));
}

function isSafeUiPair(vi, en) {
    if (typeof vi !== 'string' || typeof en !== 'string') return false;
    if (!vi.trim() || !en.trim()) return false;
    if (vi.trim() === en.trim()) return false;
    // Ignore accidental reversed pairs such as ['Search', 'Tìm kiếm'].
    return hasVietnamese(vi) || !hasVietnamese(en);
}

function collectDictionaryPairs(viNode, enNode, pairs = []) {
    if (typeof viNode === 'string' && typeof enNode === 'string') {
        if (isSafeUiPair(viNode, enNode)) pairs.push([viNode, enNode]);
        return pairs;
    }

    if (!viNode || !enNode || typeof viNode !== 'object' || typeof enNode !== 'object') {
        return pairs;
    }

    Object.keys(viNode).forEach((key) => collectDictionaryPairs(viNode[key], enNode[key], pairs));
    return pairs;
}

const EXACT = new Map();
const TERM_TO_EN = new Map();
const TERM_TO_VI = new Map();

function registerExactPair(vi, en) {
    if (!isSafeUiPair(vi, en)) return;
    EXACT.set(normalize(vi), { vi, en });
    EXACT.set(normalize(en), { vi, en });
}

for (const [vi, en] of collectDictionaryPairs(viDictionary, enDictionary)) {
    registerExactPair(vi, en);
}

for (const [vi, en] of PAIRS) {
    registerExactPair(vi, en);
}

for (const [vi, en] of TERM_PAIRS) {
    TERM_TO_EN.set(normalize(vi), en);
    TERM_TO_VI.set(normalize(en), vi);
}

function titleCase(value) {
    return String(value || '').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function translateTerm(term, locale) {
    const normalized = normalize(term);
    if (!normalized) return term;
    if (locale === 'en') {
        return TERM_TO_EN.get(normalized) ?? term;
    }
    return TERM_TO_VI.get(normalized) ?? term;
}

function translateDynamicPlaceholder(value, locale) {
    const raw = String(value ?? '').trim();
    const match = raw.match(/^--\s*(.*?)\s*--$/);
    if (!match) return null;

    const inner = match[1].trim();
    const normalizedInner = normalize(inner);
    if (!inner) return raw;

    if (locale === 'en') {
        if (/^chọn\s+/i.test(normalizedInner)) {
            return `-- Select ${translateTerm(inner.replace(/^chọn\s+/i, ''), 'en')} --`;
        }
        if (/^(tất cả|toàn bộ)\s+/i.test(normalizedInner)) {
            return `-- All ${translateTerm(inner.replace(/^(tất cả|toàn bộ)\s+/i, ''), 'en')} --`;
        }
        const translated = translateTerm(inner, 'en');
        return translated === inner ? raw : `-- ${titleCase(translated)} --`;
    }

    if (/^select\s+/i.test(normalizedInner)) {
        return `-- Chọn ${translateTerm(inner.replace(/^select\s+/i, ''), 'vi')} --`;
    }
    if (/^all\s+/i.test(normalizedInner)) {
        return `-- Tất cả ${translateTerm(inner.replace(/^all\s+/i, ''), 'vi')} --`;
    }
    const translated = translateTerm(inner, 'vi');
    return translated === inner ? raw : `-- ${translated} --`;
}

function translateQuantityRange(value, locale) {
    const raw = String(value ?? '').trim();
    let match = raw.match(/^Tổng số lượng sản phẩm từ\s+(\d+)$/i);
    if (match && locale === 'en') return `Product quantity from ${match[1]}`;
    match = raw.match(/^Tổng số lượng sản phẩm đến\s+(\d+)$/i);
    if (match && locale === 'en') return `Product quantity to ${match[1]}`;
    match = raw.match(/^Product quantity from\s+(\d+)$/i);
    if (match && locale === 'vi') return `Tổng số lượng sản phẩm từ ${match[1]}`;
    match = raw.match(/^Product quantity to\s+(\d+)$/i);
    if (match && locale === 'vi') return `Tổng số lượng sản phẩm đến ${match[1]}`;
    return null;
}

function translateDecoratedText(value, locale) {
    const raw = String(value ?? '').trim();
    if (!raw) return null;

    const required = raw.match(/^(.*?)(\s*\(\*\)|\s*\*)$/);
    if (required) {
        const translated = translateLegacyText(required[1], locale);
        if (typeof translated === 'string' && translated !== required[1]) {
            return `${translated}${required[2]}`;
        }
    }

    const colon = raw.match(/^(.*?)(\s*[:：])$/);
    if (colon) {
        const translated = translateLegacyText(colon[1], locale);
        if (typeof translated === 'string' && translated !== colon[1]) {
            return `${translated}${colon[2]}`;
        }
    }

    const parenthesizedNumber = raw.match(/^(.*?)(\s*\([0-9]+\))$/);
    if (parenthesizedNumber) {
        const translated = translateLegacyText(parenthesizedNumber[1], locale);
        if (typeof translated === 'string' && translated !== parenthesizedNumber[1]) {
            return `${translated}${parenthesizedNumber[2]}`;
        }
    }

    const recordCount = raw.match(/^(\d+)\s+bản ghi$/i);
    if (recordCount && locale === 'en') return `${recordCount[1]} records`;

    const rowCount = raw.match(/^(\d+)\s+dòng$/i);
    if (rowCount && locale === 'en') return `${rowCount[1]} rows`;

    const loading = raw.match(/^Đang tải(.+)$/i);
    if (loading && locale === 'en') return `Loading${loading[1]}`;

    const entering = raw.match(/^Nhập\s+(.+)$/i);
    if (entering && locale === 'en') {
        const term = translateTerm(entering[1], 'en');
        if (term !== entering[1]) return `Enter ${term}`;
    }

    const searching = raw.match(/^Tìm\s+(.+)$/i);
    if (searching && locale === 'en') {
        const term = translateTerm(searching[1], 'en');
        if (term !== searching[1]) return `Search ${term}`;
    }

    const selecting = raw.match(/^Chọn\s+(.+)$/i);
    if (selecting && locale === 'en') {
        const term = translateTerm(selecting[1], 'en');
        if (term !== selecting[1]) return `Select ${term}`;
    }

    return null;
}

export function translateLegacyText(value, locale = null) {
    if (value === null || value === undefined) return value;
    const raw = String(value);
    const current = localeOf(locale);
    const key = normalize(raw);
    const exact = EXACT.get(key);
    if (exact) return exact[current] ?? raw;

    const quantity = translateQuantityRange(raw, current);
    if (quantity) return quantity;

    const dynamic = translateDynamicPlaceholder(raw, current);
    if (dynamic) return dynamic;

    const decorated = translateDecoratedText(raw, current);
    if (decorated) return decorated;

    return value;
}

function setText(node, locale) {
    if (!node) return;
    const text = node.textContent;
    const translated = translateLegacyText(text, locale);
    if (typeof translated === 'string' && translated !== text) {
        node.textContent = translated;
    }
}

function setAttribute(node, attribute, locale) {
    if (!node?.hasAttribute?.(attribute)) return;
    const value = node.getAttribute(attribute);
    const translated = translateLegacyText(value, locale);
    if (typeof translated === 'string' && translated !== value) {
        node.setAttribute(attribute, translated);
    }
}

function shouldSkipNode(node) {
    const parent = node?.parentElement;
    if (!parent) return true;
    if (parent.closest('[data-i18n-ignore], [data-no-translate], .notranslate, code, pre, script, style, textarea')) return true;
    return false;
}

function translateTextNode(node, locale) {
    if (!node || shouldSkipNode(node)) return;
    const text = node.nodeValue;
    if (!text || !text.trim()) return;
    const leading = text.match(/^\s*/)?.[0] ?? '';
    const trailing = text.match(/\s*$/)?.[0] ?? '';
    const core = text.trim();
    const translated = translateLegacyText(core, locale);
    if (typeof translated === 'string' && translated !== core) {
        node.nodeValue = `${leading}${translated}${trailing}`;
    }
}

function translateDocument(locale) {
    if (typeof document === 'undefined' || !document.body) return;

    if (document.title) {
        const title = translateLegacyText(document.title, locale);
        if (typeof title === 'string' && title !== document.title) document.title = title;
    }

    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((node) => setAttribute(node, 'placeholder', locale));
    document.querySelectorAll('[title]').forEach((node) => setAttribute(node, 'title', locale));
    document.querySelectorAll('[aria-label]').forEach((node) => setAttribute(node, 'aria-label', locale));
    document.querySelectorAll('[data-placeholder]').forEach((node) => setAttribute(node, 'data-placeholder', locale));

    document.querySelectorAll([
        'select option',
        'button',
        'label',
        'th',
        'td',
        'caption',
        'legend',
        'summary',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'p',
        'small',
        'strong',
        'span',
        'li',
        '.btn',
        '.dropdown-item',
        '.ps-select__option span',
        '.ps-select__control span',
        '.ps-product-select-label',
        '.ps-product-meta',
        '.ps-chip',
        '.form-control-static',
        '.help-block',
        '.text-muted',
        '.alert',
        '.toast',
        '[data-sonner-toast] [data-title]',
        '[data-sonner-toast] [data-description]',
        '[role="status"]',
        '[role="tooltip"]',
    ].join(',')).forEach((node) => {
        if (node.childNodes.length === 1 && node.firstChild?.nodeType === Node.TEXT_NODE) {
            setText(node, locale);
        }
    });

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            if (!node.nodeValue?.trim()) return NodeFilter.FILTER_REJECT;
            if (shouldSkipNode(node)) return NodeFilter.FILTER_REJECT;
            return NodeFilter.FILTER_ACCEPT;
        },
    });

    const textNodes = [];
    while (walker.nextNode()) textNodes.push(walker.currentNode);
    textNodes.forEach((node) => translateTextNode(node, locale));
}


let browserDialogBridgeInstalled = false;
let browserDialogLocale = 'vi';

function installBrowserDialogBridge(locale) {
    if (typeof window === 'undefined') return;
    browserDialogLocale = localeOf(locale);
    if (browserDialogBridgeInstalled) return;

    browserDialogBridgeInstalled = true;
    const originalAlert = window.alert?.bind(window);
    const originalConfirm = window.confirm?.bind(window);
    const originalPrompt = window.prompt?.bind(window);

    if (originalAlert) {
        window.alert = (message) => originalAlert(translateLegacyText(message, browserDialogLocale));
    }
    if (originalConfirm) {
        window.confirm = (message) => originalConfirm(translateLegacyText(message, browserDialogLocale));
    }
    if (originalPrompt) {
        window.prompt = (message, defaultValue) => originalPrompt(translateLegacyText(message, browserDialogLocale), defaultValue);
    }
}

export function installLegacyI18nDomBridge(locale = null) {
    if (typeof window === 'undefined' || typeof document === 'undefined' || !document.body) {
        return () => {};
    }

    installBrowserDialogBridge(localeOf(locale));

    let scheduled = false;
    const run = () => {
        scheduled = false;
        translateDocument(localeOf(locale));
    };
    const schedule = () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(run);
    };

    schedule();

    const observer = new MutationObserver((records) => {
        if (records.some((record) => record.type === 'childList' || record.type === 'attributes' || record.type === 'characterData')) {
            schedule();
        }
    });

    observer.observe(document.body, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['placeholder', 'title', 'aria-label'],
    });

    return () => observer.disconnect();
}
