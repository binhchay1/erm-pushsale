/**
 * Shared static / server-backed option helpers for report filters (DRY #3).
 * Prefer Inertia `filterOptions` from backend; use these only as fallbacks.
 */

export const REPORT_PER_PAGE_VALUES = [20, 50, 100, 200, 500, 1000];

export function reportPerPageOptions(values = REPORT_PER_PAGE_VALUES) {
    return values.map((value) => ({
        id: String(value),
        value: String(value),
        label: String(value),
    }));
}

/** Resolve option list from filterOptions with fallback keys. */
export function resolveFilterOptions(filterOptions = {}, ...keys) {
    for (const key of keys) {
        const list = filterOptions?.[key];
        if (Array.isArray(list) && list.length > 0) {
            return list;
        }
    }
    return [];
}

/**
 * Field catalog: maps filter keys → control type + option source + placeholder.
 * Data for selects comes from server `filterOptions` (operationStages ← menu 1.8.1).
 */
export const REPORT_FILTER_FIELD_CATALOG = {
    date_type: {
        type: 'select',
        optionsKeys: ['dateTypes'],
        placeholder: '-- Kiểu ngày --',
        placeholderKey: 'reports.pushsale.date_standard',
    },
    date_from: {
        type: 'date_range',
    },
    date_to: {
        type: 'hidden',
    },
    operation_stage: {
        type: 'operation_stage',
        optionsKeys: ['operationStages'],
        placeholder: '-- Chọn tác nghiệp --',
        placeholderKey: 'reports.pushsale.choose_operation_stage',
    },
    product_id: {
        type: 'select',
        optionsKeys: ['products', 'productGroups'],
        placeholder: '-- Chọn sản phẩm --',
        placeholderKey: 'reports.pushsale.choose_product',
    },
    parent_product_id: {
        type: 'select',
        optionsKeys: ['parentProducts', 'productGroups'],
        placeholder: '--Sản phẩm cha--',
        placeholderKey: 'reports.pushsale.parent_product_placeholder',
    },
    sale_id: {
        type: 'select',
        optionsKeys: ['salesUsers', 'sales'],
        placeholder: '-- Chọn sale --',
        placeholderKey: 'reports.pushsale.choose_sale',
    },
    sale_leader_id: {
        type: 'select',
        optionsKeys: ['saleLeaders', 'teamLeaders'],
        placeholder: '-- Trưởng nhóm sale --',
        placeholderKey: 'reports.pushsale.choose_team_leader',
    },
    sale_team_id: {
        type: 'select',
        optionsKeys: ['saleTeams', 'salesTeams', 'teams'],
        placeholder: '-- Chọn nhóm sale --',
        placeholderKey: 'reports.pushsale.choose_sales_team',
    },
    team_leader_id: {
        type: 'select',
        optionsKeys: ['teamLeaders', 'saleLeaders'],
        placeholder: '--Trưởng nhóm--',
        placeholderKey: 'reports.pushsale.choose_team_leader',
    },
    team_id: {
        type: 'select',
        optionsKeys: ['salesTeams', 'teams', 'saleTeams'],
        placeholder: '--Chọn nhóm--',
        placeholderKey: 'reports.pushsale.choose_sales_team',
    },
    marketer_id: {
        type: 'select',
        optionsKeys: ['marketingUsers', 'marketers'],
        placeholder: '--Marketing--',
        placeholderKey: 'reports.pushsale.choose_marketing',
    },
    marketing_team_id: {
        type: 'select',
        optionsKeys: ['marketingTeams'],
        placeholder: '--Nhóm marketing--',
        placeholderKey: 'reports.pushsale.choose_marketing_team',
    },
    marketing_team_leader_id: {
        type: 'select',
        optionsKeys: ['marketingTeamLeaders', 'marketingLeaders'],
        placeholder: '--Trưởng nhóm--',
        placeholderKey: 'reports.pushsale.choose_team_leader',
    },
    warehouse_id: {
        type: 'select',
        optionsKeys: ['warehouses'],
        placeholder: '--Chọn kho--',
        placeholderKey: 'reports.pushsale.choose_warehouse',
    },
    delivery_status: {
        type: 'select',
        optionsKeys: ['deliveryStatuses'],
        placeholder: '-- Trạng thái giao hàng --',
        placeholderKey: 'reports.pushsale.delivery_status_placeholder',
    },
    discount_mode: {
        type: 'select',
        optionsKeys: ['discountModes'],
        placeholder: 'Sau chiết khấu',
        placeholderKey: 'reports.pushsale.discount_after',
    },
    reconciliation_status: {
        type: 'select',
        optionsKeys: ['reconciliationStatuses'],
        placeholder: '-- Đối soát --',
        placeholderKey: 'reports.pushsale.reconciliation_placeholder',
    },
    per_page: {
        type: 'select',
        optionsKeys: ['perPageOptions'],
        fallbackOptions: reportPerPageOptions(),
        placeholder: '50',
    },
    search: {
        type: 'search',
        placeholder: 'Từ khóa tìm kiếm',
        placeholderKey: 'reports.pushsale.search_placeholder',
    },
    no_closing_date_limit: {
        type: 'checkbox',
        label: 'Không giới hạn ngày chốt',
        labelKey: 'reports.pushsale.no_closing_date_limit',
    },
};

export function getReportFieldDef(field) {
    return REPORT_FILTER_FIELD_CATALOG[field] ?? null;
}
