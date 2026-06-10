/**
 * Nội dung giải thích nghiệp vụ cho từng màn hình.
 * Khớp theo prefix dài nhất của pathname — thêm trang mới chỉ cần thêm entry.
 */
const GUIDES = [
    // ─── Admin: Điều hành ────────────────────────────────────────────────
    {
        path: '/admin/dashboard',
        title: 'Tổng quan điều hành',
        intro: 'Bức tranh nhanh toàn công ty trong ngày / kỳ đã chọn dành cho ban điều hành.',
        sections: [
            {
                heading: 'Trang này cho biết gì?',
                items: [
                    'Số lead mới về, số đơn đã chốt và doanh thu ghi nhận theo thời gian thực.',
                    'Biểu đồ xu hướng doanh thu giúp phát hiện ngày tăng / giảm bất thường.',
                    'Hoạt động gần đây của các bộ phận (chốt đơn, nhập xuất kho, duyệt landing…).',
                ],
            },
            {
                heading: 'Dùng như thế nào?',
                items: [
                    'Mở đầu ngày để nắm tình hình chung, sau đó đi sâu vào từng báo cáo ở menu Báo cáo.',
                    'Số liệu tự lọc theo khoảng ngày — đổi bộ lọc để so sánh các kỳ.',
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
                heading: 'Trang này cho biết gì?',
                items: [
                    'Số đơn đang nằm ở từng khâu: chờ chia số → đang gọi → đã chốt → chờ vận đơn → đang giao → hoàn tất.',
                    'Tỷ lệ chuyển đổi giữa các khâu — khâu nào tỷ lệ thấp là điểm cần xử lý.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Đơn tồn lâu ở "chờ vận đơn" thường do kho chưa tạo vận đơn — nhắc bộ phận kho xử lý.',
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
                heading: 'Trang này cho biết gì?',
                items: [
                    'Doanh thu theo trạng thái: đã chốt, đang giao, đã giao, đã thu tiền.',
                    'So sánh hiệu quả giữa các team sale và marketing.',
                    'Tỷ trọng doanh thu theo sản phẩm và theo kho.',
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
                heading: 'Các nhóm báo cáo',
                items: [
                    'Telesale: công việc tác nghiệp, tổng hợp chốt đơn, doanh số chi tiết, KPI, lịch hẹn gọi lại.',
                    'Marketing: doanh số theo marketer, tỉ lệ chốt đơn theo sản phẩm.',
                    'Kho / hệ thống: doanh số theo kho, kinh doanh hệ thống (khách mới vs khách mua lại).',
                ],
            },
            {
                heading: 'Quy tắc phân quyền',
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
                    'Có thể lọc theo team hoặc trưởng nhóm để xem nội bộ từng đội.',
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
                ],
            },
        ],
    },
    {
        path: '/admin/landing-approvals',
        title: 'Duyệt trang Landing',
        intro: 'Phê duyệt các trang landing do marketing tạo trước khi chạy quảng cáo.',
        sections: [
            {
                heading: 'Quy trình',
                items: [
                    'Marketing tạo chiến dịch + landing → trạng thái "Chờ duyệt".',
                    'Admin kiểm tra nội dung, sản phẩm gắn kèm rồi bấm Duyệt.',
                    'Chỉ chiến dịch đã duyệt mới bắt đầu nhận lead về hệ thống.',
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
                    'Chi phí / đơn chốt — chiến dịch nào đắt đỏ sẽ thấy ngay.',
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
                    'Số cuộc gọi, số contact đã xử lý và kết quả từng cuộc (chốt, hẹn gọi lại, từ chối…).',
                    'Tỷ lệ chốt trên tổng contact được gán.',
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
                heading: 'Luồng nghiệp vụ',
                items: [
                    'Lead về từ landing / nền tảng quảng cáo → hệ thống ghi nhận tại đây.',
                    'Lead hợp lệ được chia tự động hoặc chia tay cho sale ("Phân bổ thủ công").',
                    'Trạng thái cho biết lead đã thành đơn, trùng số, hay lỗi dữ liệu.',
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
                heading: 'Cách dùng',
                items: [
                    'Mỗi nền tảng có một URL webhook riêng — dán URL này vào cấu hình của nền tảng đó.',
                    'Nút "Gửi thử" tạo một lead mẫu để kiểm tra kết nối hoạt động.',
                ],
            },
        ],
    },
    {
        path: '/admin/shipping-partners',
        title: 'Đối tác vận chuyển',
        intro: 'Khai báo tài khoản API của các hãng vận chuyển (GHN, GHTK, VTP…) để tạo vận đơn tự động.',
        sections: [
            {
                heading: 'Cách dùng',
                items: [
                    'Nhập token / mã shop do hãng vận chuyển cấp rồi lưu.',
                    'Dùng các nút test để kiểm tra kết nối trước khi cho vận hành thật.',
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
                    'Chưa có vận đơn → hiện "Tạo vận đơn" và "Tính phí".',
                    'Đã có vận đơn đang chạy → hiện "Đồng bộ trạng thái", "In nhãn", "Hủy vận đơn".',
                    'Đơn đã giao / đã hủy → chỉ xem, không thao tác được nữa.',
                ],
            },
            {
                heading: 'Lưu ý',
                items: [
                    'Trạng thái giao hàng đồng bộ từ hãng vận chuyển qua webhook hoặc khi bấm "Đồng bộ".',
                    'Hàng hoàn về kho cần bấm "Nhận hàng hoàn" để cộng lại tồn kho.',
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
                    'Lệch COD: số tiền hãng báo khác số hệ thống — cần kiểm tra lại đơn.',
                    'Không khớp đơn: mã vận đơn hãng gửi về không tìm thấy trong hệ thống.',
                    'Khớp đúng: đối soát xong, tiền về đủ.',
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
                    'Kho, kế toán, chia số có màn hình tác nghiệp riêng.',
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
                heading: 'Lưu ý',
                items: [
                    'Trưởng nhóm xem được dữ liệu của mọi thành viên trong team.',
                    'Sơ đồ nhân sự được vẽ tự động từ cấu trúc khai báo ở đây.',
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
                heading: 'Lưu ý',
                items: [
                    'Mỗi đơn hàng gắn với một sản phẩm — báo cáo doanh số theo sản phẩm lấy từ đây.',
                    'Tồn kho theo dõi theo từng SKU tại mục Tồn kho sản phẩm.',
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
                    'Sơ đồ phản ánh đúng phân quyền dữ liệu: cấp trên thấy dữ liệu của cấp dưới.',
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
                heading: 'Luồng nghiệp vụ',
                items: [
                    'Đơn giao thành công → chờ hãng vận chuyển chuyển COD → đối soát → ghi nhận đã thanh toán.',
                    'Đơn hoàn / lỗi cần ghi chú lý do để trừ doanh thu chính xác.',
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
                    'Xuất kho thủ công dùng cho điều chuyển / hủy hàng; xuất bán tự trừ khi đơn được giao cho vận chuyển.',
                    'Hàng hoàn về cộng lại tồn khi kho bấm nhận hàng hoàn.',
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
                    'Sửa dữ liệu gốc rồi cho đồng bộ lại, hoặc xóa nếu là rác.',
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
                heading: 'Dùng như thế nào?',
                items: [
                    'Theo dõi tiến độ KPI cá nhân và việc cần làm hôm nay.',
                    'Khách hẹn gọi lại đến hạn sẽ hiện ở đây — ưu tiên xử lý trước.',
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
        ],
    },
    {
        path: '/sales/performance',
        title: 'Báo cáo hiệu suất',
        intro: 'Kết quả làm việc của bạn (trưởng nhóm thấy cả team): cuộc gọi, tỷ lệ chốt, doanh số.',
        sections: [
            {
                heading: 'Lưu ý',
                items: [
                    'Doanh số tính theo đơn đã chốt trong khoảng ngày chọn.',
                    'Tỷ lệ chốt = đơn chốt / tổng contact được gán.',
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
                    'Tạo chiến dịch, gắn sản phẩm và ngân sách → gửi admin duyệt.',
                    'Sau khi duyệt, landing bắt đầu nhận lead và đổ về hệ thống.',
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
                ],
            },
        ],
    },
];

/** Tìm guide khớp prefix dài nhất với pathname hiện tại. */
export function findPageGuide(pathname) {
    let best = null;

    for (const guide of GUIDES) {
        if (pathname === guide.path || pathname.startsWith(guide.path + '/')) {
            if (!best || guide.path.length > best.path.length) {
                best = guide;
            }
        }
    }

    // Trang con của reports extra (vd /admin/reports/extra/sale-1) khớp prefix cha
    return best;
}
