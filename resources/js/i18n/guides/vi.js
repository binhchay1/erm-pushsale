/**
 * Nội dung giải thích nghiệp vụ cho từng màn hình (tiếng Việt).
 * Khớp theo prefix dài nhất của pathname — thêm trang mới chỉ cần thêm entry.
 */
export default [
    // ─── Admin: Điều hành ────────────────────────────────────────────────
    {
        path: '/admin/dashboard',
        title: 'Tổng quan điều hành',
        intro: 'Bức tranh nhanh toàn công ty trong ngày / kỳ đã chọn dành cho ban điều hành.',
        sections: [
            {
                heading: 'Các thẻ số liệu đầu trang',
                items: [
                    'Lead mới: số lead đổ về từ landing / quảng cáo trong kỳ lọc.',
                    'Đơn đã chốt: số đơn sale chốt thành công, chưa trừ đơn hủy / hoàn.',
                    'Doanh thu ghi nhận: tổng giá trị đơn đã chốt sau chiết khấu trong kỳ.',
                    'Tỷ lệ chốt: đơn chốt / lead về — đánh giá chất lượng lead và đội sale.',
                ],
            },
            {
                heading: 'Biểu đồ & hoạt động',
                items: [
                    'Biểu đồ xu hướng doanh thu theo ngày: phát hiện ngày tăng / giảm bất thường.',
                    'Biểu đồ cơ cấu nguồn lead: nguồn nào đang đổ nhiều lead nhất.',
                    'Dòng hoạt động gần đây: chốt đơn, nhập / xuất kho, duyệt landing… theo thời gian thực.',
                ],
            },
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Mở đầu ngày để nắm tình hình chung, rồi đi sâu vào từng báo cáo ở menu Báo cáo.',
                    'Đổi bộ lọc Tuần này / Tháng này / Quý này để so sánh các kỳ.',
                ],
            },
        ],
    },
    {
        path: '/admin/reports/business',
        title: 'Toàn cảnh vận hành',
        intro: 'Theo dõi luồng đơn hàng từ lúc lead về đến khi giao thành công, phát hiện điểm nghẽn.',
        sections: [
            {
                heading: 'Phễu vận hành (các khâu)',
                items: [
                    'Chờ chia số → Đang gọi → Đã chốt → Chờ vận đơn → Đang giao → Hoàn tất.',
                    'Mỗi khâu hiển thị số đơn đang nằm lại — khâu phình to là điểm tắc.',
                    'Tỷ lệ chuyển đổi giữa hai khâu liền kề: khâu nào rớt nhiều cần xử lý trước.',
                ],
            },
            {
                heading: 'Đọc chỉ số chuyển đổi',
                items: [
                    'Tỷ lệ lead → chốt thấp: chất lượng lead kém hoặc sale chậm gọi.',
                    'Tỷ lệ chốt → giao thành công thấp: nhiều đơn bị hủy / khách bom hàng.',
                    'Ô % có màu: xanh = tốt, vàng = cần chú ý, đỏ = cần xử lý ngay.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Đơn tồn lâu ở "Chờ vận đơn" thường do kho chưa tạo vận đơn — nhắc bộ phận kho.',
                    'Tỷ lệ hoàn cao bất thường nên đối chiếu với báo cáo doanh số chi tiết sale.',
                ],
            },
        ],
    },
    {
        path: '/admin/reports/ceo',
        title: 'Báo cáo điều hành',
        intro: 'Báo cáo tổng hợp cấp cao: doanh thu, chi phí marketing, hiệu quả từng bộ phận.',
        sections: [
            {
                heading: 'Doanh thu theo trạng thái',
                items: [
                    'Đã chốt: đơn sale vừa chốt, chưa giao.',
                    'Đang giao: đơn đã bàn giao hãng vận chuyển, đang trên đường.',
                    'Đã giao: hãng báo giao thành công, chờ thu / đã thu COD.',
                    'Đã thu tiền: tiền COD đã về và đối soát khớp — doanh thu thực nhận.',
                ],
            },
            {
                heading: 'So sánh & cơ cấu',
                items: [
                    'So sánh hiệu quả giữa các team sale và marketing trong cùng kỳ.',
                    'Tỷ trọng doanh thu theo sản phẩm: sản phẩm chủ lực và sản phẩm đuối.',
                    'Tỷ trọng doanh thu theo kho: cân đối hàng hóa và năng lực giao từng vùng.',
                ],
            },
        ],
    },
    {
        path: '/admin/reports/extra',
        title: 'Bộ báo cáo nghiệp vụ',
        intro: 'Tập hợp các báo cáo chi tiết theo từng bộ phận. Dùng thanh thẻ phía trên để chuyển giữa các báo cáo.',
        sections: [
            {
                heading: 'Nhóm Telesale',
                items: [
                    'Công việc tác nghiệp: contact đang ở bước gọi nào, còn bao nhiêu chưa xử lý.',
                    'Tổng hợp chốt đơn: số đơn, doanh số, tỷ lệ chốt theo từng nhân viên.',
                    'Doanh số chi tiết: từng đơn kèm sản phẩm, giá trị, trạng thái.',
                    'Sale KPI & lịch hẹn gọi lại: dự kiến vs thực nhận, khách cần gọi trong 7 ngày tới.',
                ],
            },
            {
                heading: 'Nhóm Marketing & Kho',
                items: [
                    'Marketing: doanh số theo marketer, tỉ lệ chốt đơn theo sản phẩm.',
                    'Kho / hệ thống: doanh số theo kho, kinh doanh hệ thống (khách mới vs khách mua lại).',
                ],
            },
            {
                heading: 'Quy tắc phân quyền & màu sắc',
                items: [
                    'Admin xem tất cả; trưởng bộ phận / trưởng nhóm xem số liệu của đội mình.',
                    'Nhân viên chỉ thấy số liệu của chính mình ở các báo cáo được phép.',
                    'Ô % có màu: xanh = tốt, vàng = cần chú ý, đỏ = cần xử lý.',
                ],
            },
        ],
    },
    {
        path: '/admin/rankings',
        title: 'Xếp hạng doanh thu',
        intro: 'Bảng xếp hạng doanh thu của nhân viên sale và marketing trong kỳ.',
        sections: [
            {
                heading: 'Cách tính',
                items: [
                    'Doanh thu tính theo đơn đã chốt trong khoảng ngày đã chọn, trừ chiết khấu.',
                    'Chọn "Trước/Sau chiết khấu" để đổi cách cộng doanh thu của bảng xếp hạng.',
                    'Lọc theo kỳ nhanh: Tuần này / Tháng này / Quý này hoặc khoảng ngày tùy chọn.',
                ],
            },
            {
                heading: 'Lọc & phạm vi',
                items: [
                    'Lọc theo team hoặc trưởng nhóm để xem nội bộ từng đội.',
                    'Cột thứ hạng phản ánh đúng phạm vi đang lọc — đổi bộ lọc sẽ xếp lại hạng.',
                ],
            },
        ],
    },

    // ─── Admin: Marketing ────────────────────────────────────────────────
    {
        path: '/admin/marketing/dashboard',
        title: 'Tổng quan Marketing',
        intro: 'Hiệu quả các nguồn quảng cáo: lead về, đơn chốt và chi phí.',
        sections: [
            {
                heading: 'Trang này cho biết gì?',
                items: [
                    'Lead về theo từng nguồn / chiến dịch và tỷ lệ chuyển thành đơn.',
                    'Ngân sách đã chi so với doanh thu mang về của từng chiến dịch.',
                    'Chi phí trên mỗi đơn chốt (CPO) — chiến dịch nào đang đắt đỏ.',
                ],
            },
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Dồn ngân sách cho chiến dịch có CPO thấp và tỷ lệ chốt cao.',
                    'Lead giảm đột ngột thường do landing lỗi hoặc hết ngân sách quảng cáo.',
                ],
            },
        ],
    },
    {
        path: '/admin/landing-approvals',
        title: 'Duyệt trang Landing',
        intro: 'Phê duyệt các kết nối landing / chiến dịch do marketing tạo trước khi nhận lead.',
        sections: [
            {
                heading: 'Quy trình duyệt',
                items: [
                    'Marketing tạo chiến dịch + landing → trạng thái "Chờ duyệt".',
                    'Bấm vào dòng (hoặc nút Chi tiết) để mở popup xem đầy đủ thông tin chiến dịch.',
                    'Kiểm tra xong bấm Duyệt — chỉ chiến dịch đã duyệt mới được chia lead cho Sale.',
                ],
            },
            {
                heading: 'Thông tin trong popup chi tiết',
                items: [
                    'Chiến dịch: người tạo, marketer phụ trách, kênh quảng cáo, trạng thái nhận lead, ngân sách.',
                    'Sản phẩm & giá: tên sản phẩm, mã SKU, giá bán đang áp dụng.',
                    'Tracking & webhook: utm_campaign, utm_source, URL nhận lead (copy dán vào Ladipage).',
                    'Bảng map trường Ladipage → trường hệ thống để cấu hình form đúng.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Trước khi duyệt, lead thử chỉ về Admin, chưa chia cho Sale.',
                    'Mở từ thông báo sẽ tự cuộn tới và mở popup chiến dịch cần duyệt.',
                ],
            },
        ],
    },
    {
        path: '/admin/marketing/campaign-report',
        title: 'Báo cáo chiến dịch',
        intro: 'So sánh hiệu quả các chiến dịch marketing: lead, đơn, doanh thu, chi phí.',
        sections: [
            {
                heading: 'Chỉ số chính',
                items: [
                    'Lead về và tỷ lệ chốt của từng chiến dịch.',
                    'Doanh thu mang về so với ngân sách đã chi.',
                    'Chi phí / đơn chốt — chiến dịch nào đắt đỏ sẽ thấy ngay.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Chi phí / đơn vượt giá trị đơn trung bình nghĩa là chiến dịch đang lỗ.',
                    'Đối chiếu với tỷ lệ lead hợp lệ để biết nguồn có nhiều lead rác hay không.',
                ],
            },
        ],
    },

    // ─── Admin: Telesale / Lead ──────────────────────────────────────────
    {
        path: '/admin/sales/performance',
        title: 'Hiệu suất Telesale',
        intro: 'Theo dõi năng suất gọi điện và chất lượng tác nghiệp của đội sale.',
        sections: [
            {
                heading: 'Chỉ số chính',
                items: [
                    'Số cuộc gọi và số contact đã xử lý theo từng nhân viên.',
                    'Kết quả từng cuộc: chốt, hẹn gọi lại, không nghe máy, từ chối, sai số…',
                    'Tỷ lệ chốt = đơn chốt / tổng contact được gán.',
                ],
            },
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Nhân viên gọi nhiều nhưng tỷ lệ chốt thấp → cần huấn luyện kịch bản.',
                    'Nhiều contact chưa tác nghiệp → lead bị bỏ quên, nhắc xử lý ngay.',
                ],
            },
        ],
    },
    {
        path: '/admin/leads',
        title: 'Nhật ký lead về',
        intro: 'Toàn bộ lead đổ về từ các nền tảng và tình trạng phân bổ cho sale.',
        sections: [
            {
                heading: 'Các cột chính',
                items: [
                    'Thời gian: lúc lead đổ về hệ thống.',
                    'Nguồn: nền tảng / chiến dịch sinh ra lead.',
                    'SĐT / Tên: thông tin khách; trùng số sẽ được đánh dấu.',
                    'Trạng thái: đã tạo đơn, trùng số, hay lỗi dữ liệu.',
                    'Sale: nhân viên đã được chia lead (nếu có).',
                ],
            },
            {
                heading: 'Luồng nghiệp vụ',
                items: [
                    'Lead về từ landing / quảng cáo → hệ thống ghi nhận tại đây.',
                    'Lead hợp lệ được chia tự động hoặc chia tay qua "Phân bổ thủ công".',
                    'Lead lỗi (sai SĐT, thiếu thông tin) cần sửa nguồn hoặc bỏ qua.',
                ],
            },
        ],
    },

    // ─── Admin: Kết nối & Đối soát ───────────────────────────────────────
    {
        path: '/admin/integrations',
        title: 'Kết nối nền tảng',
        intro: 'Cấu hình webhook nhận lead từ các nền tảng bên ngoài (landing, form quảng cáo…).',
        sections: [
            {
                heading: 'Cách kết nối',
                items: [
                    'Mỗi nền tảng có một URL webhook riêng — dán vào cấu hình của nền tảng đó.',
                    'Nhập thông tin xác thực (verify token / webhook secret / API key) rồi Lưu.',
                    'Bật công tắc "Nhận webhook" thì hệ thống mới chấp nhận lead đổ về.',
                ],
            },
            {
                heading: 'Kiểm thử & theo dõi',
                items: [
                    'Nút "Gửi thử" tạo một lead mẫu để kiểm tra kết nối hoạt động.',
                    'Xem "Lần nhận gần nhất" để biết nền tảng còn đổ lead hay đã ngưng.',
                    'Các thẻ thống kê: lead hôm nay, đang chờ xử lý, số nền tảng đang bật.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping-partners',
        title: 'Đối tác vận chuyển',
        intro: 'Khai báo tài khoản API của các hãng vận chuyển (GHN, GHTK, VTP, J&T, SPX…) để tạo vận đơn tự động.',
        sections: [
            {
                heading: 'Cách cấu hình',
                items: [
                    'Nhập token / mã shop / khóa bí mật do hãng cấp rồi Lưu.',
                    'Bật "Kích hoạt đối tác" để hệ thống được phép gọi API tạo vận đơn.',
                    'Khai báo webhook secret để nhận callback trạng thái giao hàng từ hãng.',
                ],
            },
            {
                heading: 'Kiểm thử trước khi vận hành',
                items: [
                    'Dùng các nút test (kiểm tra token, danh sách kho, tính phí mẫu…) để xác nhận kết nối.',
                    'Chỉ những hãng đã cấu hình đủ và bật mới hiện ra khi tạo vận đơn ở màn kho.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping/orders',
        title: 'Đơn vận chuyển',
        intro: 'Quản lý vận đơn của các đơn đã chốt: tạo vận đơn, theo dõi trạng thái, in nhãn.',
        sections: [
            {
                heading: 'Nút thao tác hiện theo trạng thái',
                items: [
                    'Chưa có vận đơn → "Tạo vận đơn" và "Tính phí".',
                    'Đã có vận đơn đang chạy → "Đồng bộ trạng thái", "In nhãn", "Hủy vận đơn".',
                    'Đơn đã giao / đã hủy → chỉ xem, không thao tác được nữa.',
                ],
            },
            {
                heading: 'Chi tiết vận đơn (mở từng đơn)',
                items: [
                    'Chọn hãng vận chuyển, xem cước "Tính phí" trước khi tạo vận đơn.',
                    'Lộ trình giao hàng (tracking) cập nhật theo mốc thời gian từ hãng.',
                    'Thông tin khách, COD, tổng tiền hiển thị để in nhãn / đối chiếu.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Trạng thái giao đồng bộ từ hãng qua webhook hoặc khi bấm "Đồng bộ".',
                    'Hàng hoàn về kho cần bấm nhận hàng hoàn để cộng lại tồn kho.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping/reconciliation',
        title: 'Đối soát vận chuyển',
        intro: 'So khớp tiền COD hãng vận chuyển báo về với số tiền hệ thống ghi nhận.',
        sections: [
            {
                heading: 'Các loại vấn đề',
                items: [
                    'Lệch COD: số tiền hãng báo khác số hệ thống — kiểm tra lại giá trị đơn.',
                    'Không khớp đơn: mã vận đơn hãng gửi về không tìm thấy trong hệ thống.',
                    'Khớp đúng: đối soát xong, tiền về đủ — có thể ghi nhận đã thanh toán.',
                ],
            },
            {
                heading: 'Cách đọc bảng',
                items: [
                    'Mỗi dòng là một callback từ hãng kèm mã vận đơn, COD đối tác và COD hệ thống.',
                    'Ưu tiên xử lý các dòng lệch COD và callback không map được order.',
                ],
            },
        ],
    },

    // ─── Admin: Nhân sự & Danh mục ───────────────────────────────────────
    {
        path: '/admin/users',
        title: 'Nhân viên',
        intro: 'Quản lý tài khoản nhân viên: vai trò, cấp bậc, team và người quản lý trực tiếp.',
        sections: [
            {
                heading: 'Vai trò quyết định quyền hạn',
                items: [
                    'Sale chỉ thấy khách / đơn của mình; trưởng nhóm thấy cả team.',
                    'Marketing thấy chiến dịch và doanh số do mình phụ trách.',
                    'Kho, kế toán, chia số có màn hình tác nghiệp riêng theo vai trò.',
                ],
            },
            {
                heading: 'Cấp bậc & quản lý',
                items: [
                    'Cấp bậc (trưởng bộ phận / giám sát / nhân viên) mở rộng phạm vi xem dữ liệu.',
                    'Gán team và người quản lý trực tiếp để sơ đồ nhân sự và phân quyền chính xác.',
                    'Không thể xóa tài khoản đang đăng nhập hoặc quản trị viên cuối cùng.',
                ],
            },
        ],
    },
    {
        path: '/admin/teams',
        title: 'Phòng ban & Team',
        intro: 'Khai báo cơ cấu phòng ban, team và trưởng nhóm — ảnh hưởng trực tiếp tới phân quyền dữ liệu.',
        sections: [
            {
                heading: 'Cấu trúc',
                items: [
                    'Mỗi team có loại (sale / marketing / kho / chia số / kế toán) và một trưởng nhóm.',
                    'Team có thể lồng cấp (phòng ban cha → team con) để phản ánh đúng tổ chức.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Trưởng nhóm xem được dữ liệu của mọi thành viên trong team.',
                    'Sơ đồ nhân sự được vẽ tự động từ cấu trúc khai báo ở đây.',
                    'Không xóa được team còn thành viên hoặc còn team con.',
                ],
            },
        ],
    },
    {
        path: '/admin/products',
        title: 'Sản phẩm',
        intro: 'Danh mục sản phẩm bán: sản phẩm gốc và các biến thể, giá bán, SKU.',
        sections: [
            {
                heading: 'Cấu trúc danh mục',
                items: [
                    'Sản phẩm gốc gom các biến thể con (dung tích, màu, combo…).',
                    'Mỗi biến thể có SKU riêng và giá bán riêng.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Mỗi đơn hàng gắn với một sản phẩm — báo cáo doanh số theo sản phẩm lấy từ đây.',
                    'Tồn kho theo dõi theo từng SKU tại mục Tồn kho sản phẩm.',
                    'Sản phẩm còn biến thể con phải xóa biến thể trước khi xóa sản phẩm gốc.',
                ],
            },
        ],
    },
    {
        path: '/org-chart',
        title: 'Sơ đồ nhân sự',
        intro: 'Sơ đồ tổ chức công ty theo phòng ban, trưởng bộ phận, trưởng nhóm và nhân viên.',
        sections: [
            {
                heading: 'Cách đọc',
                items: [
                    'Mỗi khối là một bộ phận; người đứng đầu hiển thị phía trên các team.',
                    'Mỗi thẻ nhân sự có thể kèm chỉ số tỷ lệ chốt / doanh thu nếu bạn có quyền xem.',
                ],
            },
            {
                heading: 'Phạm vi nhìn thấy',
                items: [
                    'Admin thấy toàn công ty; trưởng bộ phận thấy toàn ngành của mình.',
                    'Trưởng nhóm và nhân viên chỉ thấy team của mình.',
                ],
            },
        ],
    },

    // ─── Admin: Kho & Tài chính ──────────────────────────────────────────
    {
        path: '/admin/accounting',
        title: 'Kế toán',
        intro: 'Theo dõi dòng tiền của đơn: đã giao, đã thu COD, chờ đối soát, hoàn hàng.',
        sections: [
            {
                heading: 'Luồng dòng tiền',
                items: [
                    'Đơn giao thành công → chờ hãng vận chuyển chuyển COD.',
                    'Tiền về và đối soát khớp → ghi nhận "đã thanh toán" (doanh thu thực nhận).',
                    'Đơn hoàn / lỗi cần ghi chú lý do để trừ doanh thu chính xác.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Theo dõi COD chưa về so với đơn đã giao để đòi đối tác kịp thời.',
                    'Đối chiếu với màn Đối soát vận chuyển để xử lý các đơn lệch COD.',
                ],
            },
        ],
    },
    {
        path: '/admin/warehouses',
        title: 'Danh sách kho',
        intro: 'Khai báo các kho hàng vật lý và thủ kho phụ trách.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Mỗi đơn hàng xuất từ một kho — doanh số theo kho lấy từ thông tin này.',
                    'Gán thủ kho / trưởng kho để duyệt phiếu nhập xuất.',
                    'Xóa kho chỉ được phép khi kho không còn tồn kho và đơn liên quan.',
                ],
            },
        ],
    },
    {
        path: '/admin/warehouse/inventory',
        title: 'Tồn kho sản phẩm',
        intro: 'Số lượng tồn thực tế của từng sản phẩm tại từng kho, kèm thao tác nhập / xuất.',
        sections: [
            {
                heading: 'Quy trình nhập xuất',
                items: [
                    'Nhập kho: chọn kho, sản phẩm, số lượng — phiếu cần trưởng kho duyệt mới có hiệu lực.',
                    'Xuất kho thủ công dùng cho điều chuyển / hủy hàng.',
                    'Xuất bán tự trừ tồn khi đơn được tạo vận đơn giao cho hãng.',
                    'Hàng hoàn về cộng lại tồn khi kho bấm nhận hàng hoàn.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Cột tồn hiển thị số thực có; SKU dưới ngưỡng sẽ cảnh báo tồn thấp.',
                    'Mọi biến động đều ghi lại ở Lịch sử nhập xuất kho.',
                ],
            },
        ],
    },
    {
        path: '/admin/warehouse/movements',
        title: 'Lịch sử nhập xuất kho',
        intro: 'Toàn bộ biến động tồn kho: ai thao tác, ai duyệt, lúc nào, số lượng bao nhiêu.',
        sections: [
            {
                heading: 'Cách đọc',
                items: [
                    'Mỗi dòng là một biến động: nhập, xuất bán, xuất khác, hoàn về.',
                    'Cột "Người duyệt" xác nhận phiếu đã được trưởng kho ký duyệt.',
                    'Lọc theo kho / sản phẩm / khoảng ngày để truy vết một SKU cụ thể.',
                ],
            },
        ],
    },
    {
        path: '/admin/orders/failed',
        title: 'Đơn lỗi',
        intro: 'Các đơn / dữ liệu từ đối tác bị lỗi khi đồng bộ — cần xử lý tay.',
        sections: [
            {
                heading: 'Cách xử lý',
                items: [
                    'Xem lý do lỗi ở từng dòng (sai địa chỉ, thiếu SĐT, không khớp sản phẩm…).',
                    'Sửa dữ liệu gốc rồi cho đồng bộ lại.',
                    'Xóa dòng nếu là dữ liệu rác / trùng không cần giữ.',
                ],
            },
        ],
    },

    // ─── Sales ───────────────────────────────────────────────────────────
    {
        path: '/sales/dashboard',
        title: 'Tổng quan của tôi',
        intro: 'Số liệu cá nhân trong ngày: contact được gán, đơn đã chốt, doanh số.',
        sections: [
            {
                heading: 'Trang này cho biết gì?',
                items: [
                    'Contact được gán hôm nay và số đã tác nghiệp.',
                    'Đơn chốt và doanh số cá nhân so với KPI.',
                    'Khách hẹn gọi lại đến hạn — cần ưu tiên xử lý trước.',
                ],
            },
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Theo dõi tiến độ KPI cá nhân và việc cần làm hôm nay.',
                    'Bấm vào khách hẹn gọi lại để vào ngay màn gọi & chốt đơn.',
                ],
            },
        ],
    },
    {
        path: '/sales/workspace',
        title: 'Gọi & chốt đơn',
        intro: 'Màn hình tác nghiệp chính của sale: gọi khách, ghi kết quả, chốt đơn.',
        sections: [
            {
                heading: 'Luồng làm việc',
                items: [
                    'Khách mới được chia hiện ở đầu danh sách — bấm vào để xem chi tiết và gọi.',
                    'Sau mỗi cuộc gọi ghi kết quả: chốt được, hẹn gọi lại, không nghe máy, từ chối…',
                    'Khách hẹn gọi lại tự nhảy bước (Gọi lần 2, lần 3…) đến khi chốt hoặc bỏ qua.',
                    'Chốt đơn: chọn sản phẩm, số lượng, địa chỉ giao — đơn chuyển sang kho xử lý.',
                ],
            },
            {
                heading: 'Mẹo tác nghiệp',
                items: [
                    'Đọc lịch sử gọi / ghi chú trước khi gọi để nắm bối cảnh khách.',
                    'Khách trùng số (mua lại) nên xác nhận đơn cũ để chăm sóc tốt hơn.',
                ],
            },
        ],
    },
    {
        path: '/sales/performance',
        title: 'Báo cáo hiệu suất',
        intro: 'Kết quả làm việc của bạn (trưởng nhóm thấy cả team): cuộc gọi, tỷ lệ chốt, doanh số.',
        sections: [
            {
                heading: 'Chỉ số chính',
                items: [
                    'Số cuộc gọi và contact đã xử lý trong kỳ.',
                    'Tỷ lệ chốt = đơn chốt / tổng contact được gán.',
                    'Doanh số tính theo đơn đã chốt trong khoảng ngày chọn.',
                ],
            },
        ],
    },
    {
        path: '/sales/reports',
        title: 'Báo cáo nghiệp vụ sale',
        intro: 'Bộ báo cáo phục vụ công việc hằng ngày. Nhân viên chỉ thấy số liệu của chính mình; trưởng nhóm thấy cả team.',
        sections: [
            {
                heading: 'Các báo cáo',
                items: [
                    'Công việc sale: contact đang nằm ở bước gọi nào, còn bao nhiêu chưa tác nghiệp.',
                    'Tổng hợp chốt đơn & doanh số chi tiết: kết quả bán hàng của bạn / team.',
                    'Sale KPI: tách khách mới và khách cũ, doanh số dự kiến vs thực nhận.',
                    'Lịch hẹn telesales: số khách hẹn gọi lại trong 7 ngày tới.',
                ],
            },
        ],
    },
    {
        path: '/sales/customers',
        title: 'Hồ sơ khách hàng',
        intro: 'Danh sách khách bạn phụ trách kèm lịch sử mua hàng và ghi chú chăm sóc.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Khách có nhiều đơn được đánh dấu "khách mua lại" — ưu tiên chăm sóc.',
                    'Lịch sử gọi và ghi chú giúp người nhận bàn giao nắm bối cảnh nhanh.',
                    'Mở hồ sơ khách để xem toàn bộ đơn và trạng thái giao của từng đơn.',
                ],
            },
        ],
    },

    // ─── Marketing ───────────────────────────────────────────────────────
    {
        path: '/marketing/dashboard',
        title: 'Tổng quan Marketing',
        intro: 'Hiệu quả các chiến dịch bạn phụ trách: lead về, đơn chốt, ngân sách.',
        sections: [
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'So sánh lead và doanh thu giữa các chiến dịch để phân bổ lại ngân sách.',
                    'Theo dõi chi phí / đơn chốt để loại bỏ chiến dịch kém hiệu quả.',
                    'Lead về giảm đột ngột thường do landing lỗi hoặc hết ngân sách quảng cáo.',
                ],
            },
        ],
    },
    {
        path: '/marketing/workspace',
        title: 'Báo cáo nguồn quảng cáo',
        intro: 'Chi tiết lead về theo từng nguồn / nền tảng quảng cáo.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Theo dõi tỷ lệ lead hợp lệ — lead trùng số / sai SĐT nhiều là tín hiệu nguồn kém.',
                    'So sánh nguồn nào cho lead chốt được nhiều nhất để tối ưu ngân sách.',
                ],
            },
        ],
    },
    {
        path: '/marketing/campaigns',
        title: 'Trang Landing',
        intro: 'Tạo và quản lý chiến dịch + trang landing nhận lead.',
        sections: [
            {
                heading: 'Quy trình',
                items: [
                    'Tạo chiến dịch, gắn sản phẩm và ngân sách → hệ thống cấp URL nhận lead.',
                    'Dán URL nhận lead vào cấu hình API của Ladipage / landing.',
                    'Gửi admin duyệt — sau khi duyệt, landing bắt đầu nhận lead và đổ về hệ thống.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Tên chiến dịch sinh ra utm_campaign — Ladipage phải gửi đúng field này.',
                    'Bảng map trường giúp khớp dữ liệu form với hệ thống.',
                ],
            },
        ],
    },
    {
        path: '/marketing/campaign-report',
        title: 'Báo cáo chiến dịch',
        intro: 'Hiệu quả từng chiến dịch của bạn: lead, đơn chốt, chi phí / đơn.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Chi phí / đơn vượt giá trị đơn trung bình nghĩa là chiến dịch đang lỗ.',
                    'Tỷ lệ chốt thấp dù lead nhiều → kiểm tra chất lượng nguồn / nội dung landing.',
                ],
            },
        ],
    },
    {
        path: '/marketing/revenue',
        title: 'Báo cáo doanh số',
        intro: 'Doanh số sinh ra từ lead của các chiến dịch bạn phụ trách.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Doanh số tính khi sale chốt đơn từ lead của chiến dịch.',
                    'Doanh số theo trạng thái giao giúp biết bao nhiêu đã thực nhận, bao nhiêu còn rủi ro hoàn.',
                ],
            },
        ],
    },
    {
        path: '/marketing/reports',
        title: 'Báo cáo nghiệp vụ marketing',
        intro: 'Bộ báo cáo chi tiết. Nhân viên thấy số liệu của mình; trưởng nhóm thấy cả team và báo cáo theo sản phẩm.',
        sections: [
            {
                heading: 'Các báo cáo',
                items: [
                    'Doanh số marketing: đơn và doanh số theo từng trạng thái giao hàng.',
                    'Tỉ lệ chốt đơn sản phẩm (trưởng nhóm): sản phẩm nào dễ chốt, giá trị trung bình bao nhiêu.',
                ],
            },
        ],
    },

    // ─── Warehouse ───────────────────────────────────────────────────────
    {
        path: '/warehouse/dashboard',
        title: 'Tổng quan kho',
        intro: 'Việc kho cần xử lý hôm nay: đơn chờ vận đơn, hàng hoàn chờ nhận, tồn kho thấp.',
        sections: [
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Ưu tiên xử lý đơn chờ vận đơn để hàng đi sớm.',
                    'Nhận hàng hoàn về để cộng lại tồn và đóng đơn.',
                    'Cảnh báo tồn kho thấp giúp lên kế hoạch nhập thêm.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/workspace',
        title: 'Xuất kho & vận đơn',
        intro: 'Màn hình tác nghiệp của kho: tạo vận đơn cho đơn đã chốt và theo dõi giao hàng.',
        sections: [
            {
                heading: 'Luồng làm việc',
                items: [
                    'Đơn sale chốt xong chuyển vào đây với trạng thái "Chờ vận đơn".',
                    'Mở chi tiết đơn → chọn hãng vận chuyển → "Tính phí" để xem trước cước → "Tạo vận đơn".',
                    'Tạo vận đơn thành công sẽ tự trừ tồn kho và chuyển đơn sang "Đang lấy hàng / Đang giao".',
                    'Hàng hoàn về bấm "Nhận hàng hoàn" để cộng lại tồn.',
                ],
            },
            {
                heading: 'Các tab trạng thái',
                items: [
                    'Chờ vận đơn, Lấy hàng, Đang giao, Đã giao, Đã thanh toán, Đơn hoàn, Đã hủy.',
                    'Bấm tab để lọc nhanh nhóm đơn cần xử lý.',
                ],
            },
            {
                heading: 'Nút thao tác hiện theo trạng thái',
                items: [
                    'Chưa có vận đơn → Tạo vận đơn, Tính phí.',
                    'Vận đơn đang chạy → Đồng bộ trạng thái, In nhãn, Hủy vận đơn.',
                    'Đã giao / đã thanh toán / đã hủy → chỉ xem.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/shipping/orders',
        title: 'Đơn vận chuyển',
        intro: 'Danh sách vận đơn của kho và trạng thái giao từng đơn.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Trạng thái đồng bộ từ hãng vận chuyển — bấm "Đồng bộ" nếu nghi số liệu cũ.',
                    'Đơn giao thất bại nhiều lần nên báo sale gọi lại khách trước khi gửi lại.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/inventory',
        title: 'Tồn kho sản phẩm',
        intro: 'Tồn thực tế tại kho của bạn, kèm thao tác nhập / xuất có duyệt.',
        sections: [
            {
                heading: 'Quy trình',
                items: [
                    'Phiếu nhập / xuất cần trưởng kho duyệt mới tính vào tồn.',
                    'Xuất bán tự động khi tạo vận đơn — không cần thao tác tay.',
                    'SKU dưới ngưỡng tồn sẽ cảnh báo để nhập bổ sung.',
                ],
            },
        ],
    },
    {
        path: '/warehouse/reports',
        title: 'Báo cáo kho',
        intro: 'Doanh số và luồng hàng theo kho — dành cho trưởng kho.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Doanh số xác nhận = đơn đã giao + đã thanh toán.',
                    'Hàng hoàn cao bất thường nên đối chiếu với chất lượng đóng gói / địa chỉ.',
                ],
            },
        ],
    },

    // ─── Accounting ──────────────────────────────────────────────────────
    {
        path: '/accounting/dashboard',
        title: 'Tổng quan kế toán',
        intro: 'Dòng tiền trong kỳ: COD đã thu, chờ đối soát, hoàn hàng.',
        sections: [
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Theo dõi tiền COD chưa về so với đơn đã giao để đòi đối tác kịp thời.',
                    'Nắm tỷ lệ hoàn để dự phòng dòng tiền chính xác.',
                ],
            },
        ],
    },
    {
        path: '/accounting/workspace',
        title: 'Theo dõi đơn & dòng tiền',
        intro: 'Danh sách đơn theo trạng thái tiền: đã giao chờ thu, đã thanh toán, hoàn / lỗi.',
        sections: [
            {
                heading: 'Luồng nghiệp vụ',
                items: [
                    'Đơn giao thành công chuyển vào diện chờ thu COD.',
                    'Khi hãng vận chuyển chuyển tiền và đối soát khớp → đánh dấu đã thanh toán.',
                    'Đơn hoàn cần ghi chú lý do để trừ doanh thu đúng kỳ.',
                ],
            },
        ],
    },
    {
        path: '/accounting/reports',
        title: 'Báo cáo kinh doanh',
        intro: 'Bộ báo cáo doanh số toàn hệ thống dành cho kế toán: theo kho, theo sale, theo marketing.',
        sections: [
            {
                heading: 'Các báo cáo',
                items: [
                    'Doanh số theo kho & kinh doanh hệ thống: doanh thu, khách mới vs khách mua lại.',
                    'Tổng hợp chốt đơn / doanh số chi tiết: đối chiếu số liệu với bộ phận sale, marketing.',
                ],
            },
        ],
    },

    // ─── Allocator ───────────────────────────────────────────────────────
    {
        path: '/allocator/dashboard',
        title: 'Tổng quan chia số',
        intro: 'Tình hình lead chờ chia và tốc độ xử lý trong ngày.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Lead chờ chia lâu làm giảm tỷ lệ chốt — ưu tiên chia ngay khi lead về.',
                    'Theo dõi tồn lead chưa chia để cân đối tải cho từng sale.',
                ],
            },
        ],
    },
    {
        path: '/allocator/workspace',
        title: 'Chia số cho sale',
        intro: 'Phân bổ lead mới về cho từng sale theo năng lực và khối lượng hiện tại.',
        sections: [
            {
                heading: 'Cách dùng',
                items: [
                    'Chọn các lead chưa phân bổ → chọn sale nhận → xác nhận.',
                    'Lead trùng số điện thoại với khách cũ nên chia lại cho sale đã chăm trước đó.',
                    'Cân đối số lead mỗi sale để không ai quá tải hoặc thiếu việc.',
                ],
            },
        ],
    },
];
