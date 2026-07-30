import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
    PushsaleSelect,
    useInertiaFilters,
} from '@/components/reports/PushsaleReportChrome';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import { ReportFilterToolbar } from '@/components/reports/ReportFilterToolbar';
import { cleanReportPayload, REVENUE_DIMENSION_OPTIONS } from '@/components/reports/extra/extraReportUtils';

function renderExtraField(field, draft, set, filterOptions) {
    return (
        <ReportFilterField
            key={field}
            field={field}
            draft={draft}
            onChange={set}
            filterOptions={filterOptions}
        />
    );
}

/**
 * Extra-report thin wrapper over shared ReportFilterToolbar (DRY #7).
 * Owns filter draft via ReportFilterToolbar → useInertiaFilters (single owner).
 */
export function ExtraPushsaleReportToolbar({
    title,
    routeUrl,
    filters,
    filterOptions,
    filterFields = [],
    className = '',
    headerClassName = '',
    primary = [],
    advanced = [],
    actionsExtra = null,
    exportLabel = null,
}) {
    return (
        <ReportFilterToolbar
            title={title}
            routeUrl={routeUrl}
            filters={filters}
            filterOptions={filterOptions}
            filterFields={filterFields.length ? filterFields : null}
            className={`ps-report-toolbar-shell ps-extra-toolbar ps-report-v2-toolbar ${className}`.trim()}
            headerClassName={headerClassName || `ps-report-v2-header ${className}`.trim()}
            primary={primary}
            advanced={advanced}
            exportLabel={exportLabel}
            actionsExtra={actionsExtra}
        />
    );
}

/** @deprecated Prefer ExtraPushsaleReportToolbar — alias kept for ExtraReport call sites. */
export function PushsaleReportToolbar(props) {
    return <ExtraPushsaleReportToolbar {...props} />;
}

export function CommonToolbar({ title, routeUrl, filters, filterOptions, filterFields = [], compact = false }) {
    return (
        <ExtraPushsaleReportToolbar
            title={title}
            routeUrl={routeUrl}
            filters={filters}
            filterOptions={filterOptions}
            filterFields={filterFields}
            className={compact ? 'is-compact' : ''}
            primary={['date_type', 'date_from', 'date_to', 'warehouse_id', 'sale_id', 'marketer_id', 'search']}
            advanced={[
                'team_leader_id',
                'team_id',
                'marketing_team_id',
                'parent_product_id',
                'product_id',
                'delivery_status',
                'discount_mode',
                'reconciliation_status',
                'per_page',
                'no_closing_date_limit',
            ]}
        />
    );
}

export function RevenueDimensionField() {
    return (
        <select
            className="ps-control ps-revenue-view-select"
            value="warehouse"
            onChange={() => {}}
            title="Giai đoạn này đang chuẩn hóa theo mẫu 1.Kho của Pushsale"
        >
            {REVENUE_DIMENSION_OPTIONS.map((option) => (
                <option value={option.value} key={option.value}>{option.label}</option>
            ))}
        </select>
    );
}

export function RevenueGroupCompactPicker({ groups, selectedKeys, onChange }) {
    const selected = new Set(selectedKeys);
    const addGroup = (key) => {
        if (!key) return;
        const next = new Set(selectedKeys);
        next.add(key);
        onChange(groups.filter((group) => next.has(group.key)).map((group) => group.key));
    };
    const removeGroup = (key) => {
        if (selectedKeys.length <= 1) return;
        onChange(selectedKeys.filter((item) => item !== key));
    };

    return (
        <div className="ps-revenue-group-compact is-inline">
            <div className="ps-revenue-group-tags">
                {selectedKeys.map((key) => {
                    const group = groups.find((item) => item.key === key);
                    return group ? (
                        <button type="button" key={key} className="ps-revenue-group-tag" onClick={() => removeGroup(key)} title={group.description}>
                            <span>×</span> {group.number}.{group.label}
                        </button>
                    ) : null;
                })}
            </div>
            <select className="ps-control" value="" onChange={(event) => addGroup(event.target.value)}>
                <option value="">-- Chọn nhóm doanh số --</option>
                {groups.map((group) => (
                    <option key={group.key} value={group.key} disabled={selected.has(group.key)}>
                        {group.number}. {group.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

/**
 * Owns its own useInertiaFilters draft — parent only owns selectedKeys UI state.
 */
export function RevenueOverviewToolbar({
    title,
    routeUrl,
    filters,
    filterOptions,
    filterFields,
    groups = [],
    selectedKeys = [],
    onSelectedKeys,
    variant = 'summary',
}) {
    const { draft, set, apply } = useInertiaFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const render = (field) => (fields.has(field) ? renderExtraField(field, draft, set, filterOptions) : null);
    const leaderField = fields.has('marketing_team_leader_id') ? 'marketing_team_leader_id' : 'team_leader_id';
    const teamField = fields.has('marketing_team_id') ? 'marketing_team_id' : 'team_id';

    const primaryFilters = (
        <div className="ps-revenue-overview-primary">
            <RevenueDimensionField />
            {render('parent_product_id')}
            {render('product_id')}
            {render('date_type')}
            {(fields.has('date_from') || fields.has('date_to')) ? <PushsaleDateRange filters={draft} onChange={set} /> : null}
        </div>
    );

    const advancedFilters = (
        <div className="ps-revenue-overview-advanced-wrap ps-adv-filter-panel">
            <div className="ps-revenue-overview-advanced ps-adv-filter-row" style={{ '--ps-adv-cols': 4 }}>
                {render('discount_mode')}
                {render('delivery_status')}
                {render('reconciliation_status')}
                {render('warehouse_id')}
            </div>
            {groups.length > 0 ? (
                <div className="ps-revenue-overview-groups-row">
                    <RevenueGroupCompactPicker groups={groups} selectedKeys={selectedKeys} onChange={onSelectedKeys} />
                </div>
            ) : null}
            <div className="ps-revenue-overview-advanced ps-adv-filter-row" style={{ '--ps-adv-cols': 4 }}>
                {render(leaderField)}
                {render(teamField)}
                {render('per_page')}
                <div className="ps-revenue-overview-check-cell">
                    {render('no_closing_date_limit')}
                </div>
            </div>
        </div>
    );

    return (
        <PushsalePageShell
            title={title}
            className={`ps-report-toolbar-shell ps-extra-toolbar ps-revenue-overview-toolbar ps-revenue-overview-toolbar-${variant}`}
            headerClassName="ps-revenue-overview-header"
            bodyClassName="ps-revenue-overview-body"
            collapsible
            defaultFiltersCollapsed={false}
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={(
                <div className="ps-revenue-overview-actions ps-report-toolbar-actions">
                    <PushsaleSearchButton onClick={() => apply()} />
                    <button type="button" className="ps-report-gear" title="Cấu hình hiển thị">
                        <i className="fa fa-gear" aria-hidden="true" />
                    </button>
                </div>
            )}
        />
    );
}

/**
 * Owns its own useInertiaFilters draft.
 */
export function MarketingWorkToolbar({ title, routeUrl, filters, filterOptions, filterFields = [] }) {
    const { draft, set, apply } = useInertiaFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const render = (field) => (fields.has(field) ? renderExtraField(field, draft, set, filterOptions) : null);

    const primaryFilters = (
        <div className="ps-marketing-work-primary ps-report-toolbar-controls">
            {render('date_type')}
            {(fields.has('date_from') || fields.has('date_to')) ? <PushsaleDateRange filters={draft} onChange={set} /> : null}
            {render('customer_type')}
        </div>
    );

    const advancedFilters = (
        <div className="ps-marketing-work-advanced-wrap ps-adv-filter-panel">
            <div className="ps-marketing-work-advanced ps-adv-filter-row" style={{ '--ps-adv-cols': 5 }}>
                <PushsaleSelect placeholder="Sale" value="sale" options={[{ value: 'sale', label: 'Sale' }]} onChange={() => {}} />
                {render('marketing_team_leader_id')}
                {render('marketing_team_id')}
                {render('team_id')}
                {render('marketer_id')}
            </div>
            <div className="ps-marketing-work-advanced ps-adv-filter-row" style={{ '--ps-adv-cols': 5 }}>
                {render('sale_id')}
                {render('search')}
                {render('parent_product_id')}
                {render('product_id')}
                {render('per_page')}
                {render('no_closing_date_limit')}
            </div>
        </div>
    );

    return (
        <PushsalePageShell
            title={title}
            className="ps-report-toolbar-shell ps-extra-toolbar ps-marketing-work-toolbar"
            headerClassName="ps-marketing-work-header"
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={(
                <div className="ps-report-toolbar-actions">
                    <PushsaleSearchButton onClick={() => apply()} />
                    <PushsaleExportButton routeUrl={routeUrl} filters={cleanReportPayload(draft)} />
                </div>
            )}
        />
    );
}
