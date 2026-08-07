import { translateLegacyText } from '@/lib/legacyI18n';

/**
 * Shared report i18n helpers for labels that still come from backend config or
 * legacy Pushsale templates. Keep this narrow and deterministic: known text is
 * translated, unknown business names (product, source, user, campaign) pass
 * through unchanged.
 */

const TEXT_KEY_MAP = {
    'Bảng tổng hợp kết quả chia data trong ngày': 'reports.runtime.data_allocation.title',
    'Báo cáo doanh số chi tiết sale': 'reports.revenue_sales.detail_title',
    'Báo cáo doanh số': 'reports.extra.sale-revenue.title',
    'Báo cáo doanh số V2': 'reports.extra.sale-revenue-v2.title',
    'Báo cáo công việc sale': 'reports.extra.sale-work.title',
    'Báo cáo nhóm sale': 'reports.extra.sale-team.title',
    'Báo cáo data sale': 'reports.extra.sale-data.title',
    'Báo cáo tối ưu Sale': 'reports.extra.sale-optimization.title',
    'Sales report': 'reports.extra.sale-revenue.title',
    'Tìm kiếm': 'reports.pushsale.search',
    'Xuất Excel': 'reports.pushsale.export_excel',
    'Không có dữ liệu.': 'reports.pushsale.no_data',
    'Không có dữ liệu': 'reports.pushsale.no_data',
    'Không có dữ liệu theo điều kiện lọc.': 'reports.pushsale.no_matching_filter',
    'Không có dữ liệu phù hợp với bộ lọc.': 'reports.pushsale.no_matching_filter',
    'No matching data.': 'reports.pushsale.no_data',
    'Chưa có dữ liệu phù hợp với bộ lọc.': 'reports.pushsale.no_matching_filter',
    'Tìm theo tên / mã': 'reports.pushsale.search_name_code',
    'Từ khóa tìm kiếm': 'reports.pushsale.search_placeholder',
    'STT': 'reports.pushsale.stt',
    'Day': 'reports.runtime.columns.day',
    'Ngày': 'reports.runtime.columns.day',
    'Sale': 'reports.pushsale.sale',
    'SALE': 'reports.pushsale.sale',
    'Marketing': 'reports.pushsale.marketing',
    'MARKETING': 'reports.pushsale.marketing',
    'Team': 'reports.runtime.columns.team',
    'Contact mới': 'reports.pushsale.contact_new',
    'Contact trùng': 'reports.runtime.columns.duplicate_contacts',
    'Contact cũ': 'reports.pushsale.contact_old',
    'CSKH': 'reports.runtime.columns.care',
    'Thủ công': 'reports.runtime.columns.manual',
    'KHÁCH HÀNG MỚI': 'reports.ceo_report.new_customers_group',
    'KHÁCH HÀNG CŨ': 'reports.ceo_report.old_customers_group',
    'TỔNG CHUNG': 'reports.ceo_report.total_group',
    'Chốt đơn': 'reports.pushsale.closed_orders',
    'Tỷ lệ chốt (%)': 'reports.pushsale.close_rate',
    'Số sản phẩm': 'reports.pushsale.product_qty',
    'Doanh số tạm tính': 'reports.pushsale.expected_revenue',
    'Doanh số tạm tính sau chiết khấu': 'reports.pushsale.actual_revenue',
    'Phí COD': 'reports.ceo_report.cod_fee',
    'Hỗ trợ COD': 'reports.ceo_report.cod_support',
    'CK': 'reports.ceo_report.discount',
    'Đặt cọc': 'reports.ceo_report.deposit',
    'KPI doanh số': 'reports.pushsale.target',
    'Tỷ lệ (%)': 'reports.pushsale.rate',
    'Tổng contact': 'reports.pushsale.total_contact',
    'Tổng contact chưa TN': 'reports.pushsale.total_contact_untouched',
    'Số contact': 'reports.pushsale.contact_count',
    'Chưa TN': 'reports.pushsale.untouched',
    'Có': 'reports.pushsale.yes',
    'Không': 'reports.pushsale.no',
    'Total Allocated': 'reports.runtime.summary.total_allocated',
    'Unique Contacts': 'reports.runtime.summary.unique_contacts',
    'Duplicate Contacts': 'reports.runtime.summary.duplicate_contacts',
    'total allocated': 'reports.runtime.summary.total_allocated',
    'unique contacts': 'reports.runtime.summary.unique_contacts',
    'duplicate contacts': 'reports.runtime.summary.duplicate_contacts',
    'Tổng': 'reports.pushsale.total',
    'Tổng:': 'reports.pushsale.total_colon',
    'TÊN SALE': 'reports.revenue_sales.name_label',
    'Tên sale': 'reports.revenue_sales.name_label',
    'TÊN MARKETING': 'reports.revenue_marketing.name_label',
    'Tên marketing': 'reports.revenue_marketing.name_label',
    'Số lượng': 'reports.pushsale.quantity',
    'Doanh số': 'reports.pushsale.revenue',
    'ĐƠN CHỐT (1)': 'reports.revenue_metrics_table.closed_orders',
    'Đơn chốt (1)': 'reports.revenue_metrics_table.closed_orders',
    'XÁC NHẬN GIAO HÀNG (2)': 'reports.revenue_metrics_table.confirmed_delivery',
    'XNGH (2)': 'reports.revenue_metrics_table.confirmed_delivery',
    'HỦY VẬN ĐƠN (3)': 'reports.revenue_metrics_table.canceled_shipping',
    'Hủy VĐ (3)': 'reports.revenue_metrics_table.canceled_shipping',
    'CHUYỂN ĐVGH (4)': 'reports.revenue_metrics_table.transferred_carrier',
    'ĐÃ HOÀN (5)': 'reports.revenue_metrics_table.returned',
    'ĐANG HOÀN (6)': 'reports.revenue_metrics_table.returning',
    'ĐÃ GIAO HÀNG (7)': 'reports.revenue_metrics_table.delivered',
    'ĐÃ THANH TOÁN (8)': 'reports.revenue_metrics_table.paid',
    'GIAO THÀNH CÔNG (9)': 'reports.revenue_metrics_table.successful_delivery',
    '% ĐÃ HOÀN (10)': 'reports.revenue_metrics_table.return_rate',
    '% HỦY (11)': 'reports.revenue_metrics_table.shipping_cancel_rate',
    '% XNGH (12)': 'reports.revenue_metrics_table.confirm_rate',
    '% GH Thành công (13)': 'reports.revenue_metrics_table.success_rate',
    'Contact (14)': 'reports.revenue_metrics_table.contacts',
    'Tỷ lệ chốt (%) (15)': 'reports.revenue_metrics_table.closing_rate',
    'Số sản phẩm (16)': 'reports.revenue_metrics_table.product_count',
    'Giá trị đơn (17)': 'reports.revenue_metrics_table.average_order_value',
    '% DS ĐÃ HOÀN (18)': 'reports.revenue_metrics_table.revenue_return_rate',
    '% DS HỦY (19)': 'reports.revenue_metrics_table.revenue_cancel_rate',
    'Gói chính': 'reports.columns.primary_packets',
    'Gói tin chính': 'dashboard.marketing.packet_dialog.primary',
    'Gói tin upsale': 'dashboard.marketing.packet_dialog.upsale',
    'Upsale': 'reports.pushsale.upsale',
    'Sau chiết khấu': 'reports.pushsale.discount_after',
    '-- Đối soát --': 'reports.pushsale.reconciliation_placeholder',
    '-- Trạng thái giao hàng --': 'reports.pushsale.delivery_status_placeholder',
    '--Select warehouse--': 'reports.pushsale.choose_warehouse',
    '--Select product--': 'reports.pushsale.choose_product',
    '--Sản phẩm cha--': 'reports.pushsale.parent_product_placeholder',
    '-- Sản phẩm cha --': 'reports.pushsale.parent_product_placeholder',
    '--Chọn nhóm--': 'reports.pushsale.choose_team',
    '--Trưởng nhóm--': 'reports.pushsale.choose_team_leader',
    '--Chọn trưởng nhóm--': 'reports.pushsale.choose_team_leader',
    '--Chọn nhóm sale--': 'reports.pushsale.choose_sales_team',
    '-- Chọn nhóm sale --': 'reports.pushsale.choose_sales_team',
    '-- Chọn sale --': 'reports.pushsale.choose_sale',
    '--Marketing--': 'reports.pushsale.choose_marketing',
    '--Nhóm marketing--': 'reports.pushsale.choose_marketing_team',
    '-- Chọn sản phẩm --': 'reports.pushsale.choose_product',
    '--Sản phẩm / gói sản phẩm--': 'reports.pushsale.choose_product',
    '-- Chọn trạng thái giao hàng --': 'reports.pushsale.delivery_status_placeholder',
    '-- Chọn nhóm doanh số --': 'reports.extra.warehouse_sales.choose_visible',
    '--Hiển thị tất--': 'reports.pushsale.show_all',
    'Không giới hạn ngày chốt': 'reports.pushsale.no_closing_date_limit',
    'Cấu hình hiển thị': 'reports.pushsale.display_settings',
    'Xuất dữ liệu': 'reports.pushsale.export_data',
    'Chưa phân sale': 'reports.pushsale.unassigned_sale',
    'Giai đoạn này đang chuẩn hóa theo mẫu 1.Kho của Pushsale': 'reports.pushsale.revenue_dimension_hint',
    '1.Kho': 'reports.revenue_dimensions.warehouse',
    '2.Số sản phẩm/đơn': 'reports.revenue_dimensions.products_per_order',
    '3.Sản phẩm': 'reports.revenue_dimensions.product',
    '4.Sale': 'reports.revenue_dimensions.sale',
    '5.Marketing': 'reports.revenue_dimensions.marketing',
    '6.Care đơn': 'reports.revenue_dimensions.care',
    '7.Nhóm sale': 'reports.revenue_dimensions.sale_team',
    '8.Nhóm marketing': 'reports.revenue_dimensions.marketing_team',
    '9.Tỉnh/Thành phố': 'reports.revenue_dimensions.province',
    '10.Kênh quảng cáo': 'reports.revenue_dimensions.channel',
    '11.Khách cũ/mới': 'reports.revenue_dimensions.customer_type',
    '12.Phương thức giao hàng': 'reports.revenue_dimensions.shipping_method',
    'Ngày data về hệ thống': 'reports.date_types.system_received',
    'Ngày sale nhận data': 'reports.date_types.sale_received',
    'Ngày chốt đơn': 'reports.date_types.closed_at',
    'Ngày cập nhật': 'reports.date_types.updated_at',
    'Ngày tạo đơn': 'reports.date_types.order_created_at',
    'Ngày đăng đơn': 'reports.date_types.posted_at',
    'Ngày giao hàng': 'reports.date_types.delivered_at',
    'Chờ vận đơn': 'reports.delivery_statuses.waiting_waybill',
    'Hoãn giao hàng': 'reports.delivery_statuses.postponed',
    'Hủy vận đơn': 'reports.delivery_statuses.cancel_waybill',
    'Hủy đăng đơn': 'reports.delivery_statuses.cancel_posting',
    'Đã hoàn': 'reports.delivery_statuses.returned',
    'Đang hoàn': 'reports.delivery_statuses.returning',
    'Đã giao hàng': 'reports.delivery_statuses.delivered',
    'Đã thanh toán': 'reports.delivery_statuses.paid',
    'Giao hàng 1 phần': 'reports.delivery_statuses.partial',
    'Không lấy được hàng': 'reports.delivery_statuses.pickup_failed',
    'Giao ngay': 'reports.delivery_statuses.deliver_now',

};

export function normalizeReportText(value) {
    return typeof value === 'string' ? value.replace(/\s+/g, ' ').trim() : value;
}

export function translateReportText(t, value, fallback = value) {
    const normalized = normalizeReportText(value);
    if (!normalized) return fallback ?? '';
    const key = TEXT_KEY_MAP[normalized] ?? TEXT_KEY_MAP[String(normalized).toLowerCase()];
    if (!key) {
        return translateLegacyText(normalized, null) ?? fallback ?? value;
    }
    const translated = t(key);
    return translated !== key ? translated : (translateLegacyText(normalized, null) ?? fallback ?? value);
}

export function translateReportOptionLabel(t, option) {
    const label = option?.name ?? option?.label ?? option?.text;
    return translateReportText(t, label, label);
}

export function translateReportColumns(t, columns = []) {
    return columns.map((column) => ({
        ...column,
        label: column?.label_key
            ? t(`reports.columns.${column.label_key}`)
            : translateReportText(t, column?.label, column?.label),
    }));
}
