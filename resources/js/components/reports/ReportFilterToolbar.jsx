import { router } from '@inertiajs/react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import {
    PushsaleExportButton,
    PushsaleSearchButton,
} from '@/components/reports/PushsaleReportChrome';
import { cleanInertiaFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import { translateReportText } from '@/lib/reportI18n';
import { useT } from '@/providers/I18nProvider';

function chunkFields(items, size = 4) {
    const rows = [];
    for (let index = 0; index < items.length; index += size) {
        rows.push(items.slice(index, index + size));
    }
    return rows;
}

export function cleanReportFilterPayload(values = {}) {
    return cleanInertiaFilters(values);
}

function ReportFilterToolbarShell({
    title,
    routeUrl,
    draft,
    set,
    onSearch,
    filterOptions = {},
    primary = ['date_type', 'date_from'],
    advanced = [],
    filterFields = null,
    pageCode,
    className = '',
    headerClassName = '',
    bodyClassName = '',
    primaryClassName = 'ps-report-v2-primary ps-report-toolbar-controls',
    advancedClassName = 'ps-report-v2-advanced-wrap ps-adv-filter-panel',
    advancedRowClassName = 'ps-report-v2-advanced ps-adv-filter-row ps-report-adv-grid',
    advancedCols = 4,
    actionsClassName = 'ps-report-toolbar-actions',
    collapsible = true,
    defaultFiltersCollapsed = false,
    showExport = true,
    exportLabel,
    searchLabel,
    notice = null,
    actionsExtra = null,
    children,
}) {
    const t = useT();
    const resolvedTitle = translateReportText(t, title, title);
    const allowed = filterFields ? new Set(filterFields) : null;
    const include = (field) => !allowed || allowed.has(field) || (field === 'date_from' && allowed.has('date_to'));

    const visiblePrimary = primary.filter((field) => field !== 'date_to' && include(field));
    const visibleAdvanced = advanced.filter((field) => include(field));
    const advancedRows = chunkFields(visibleAdvanced, advancedCols);

    const primaryFilters = visiblePrimary.length > 0 ? (
        <div className={primaryClassName}>
            {visiblePrimary.map((field) => (
                <ReportFilterField
                    key={field}
                    field={field}
                    draft={draft}
                    onChange={set}
                    filterOptions={filterOptions}
                />
            ))}
        </div>
    ) : null;

    const advancedFilters = advancedRows.length > 0 ? (
        <div className={advancedClassName}>
            {advancedRows.map((row, rowIndex) => (
                <div
                    key={`adv-row-${rowIndex}`}
                    className={advancedRowClassName}
                    style={{ '--ps-adv-cols': Math.max(row.length, advancedCols) }}
                >
                    {row.map((field) => (
                        <ReportFilterField
                            key={field}
                            field={field}
                            draft={draft}
                            onChange={set}
                            filterOptions={filterOptions}
                        />
                    ))}
                </div>
            ))}
        </div>
    ) : null;

    return (
        <PushsalePageShell
            title={resolvedTitle}
            pageCode={pageCode}
            className={className}
            headerClassName={headerClassName}
            bodyClassName={bodyClassName}
            collapsible={collapsible}
            defaultFiltersCollapsed={defaultFiltersCollapsed}
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            notice={notice}
            actions={(
                <div className={actionsClassName}>
                    <PushsaleSearchButton onClick={onSearch} label={searchLabel} />
                    {showExport ? (
                        <PushsaleExportButton
                            routeUrl={routeUrl}
                            filters={cleanReportFilterPayload(draft)}
                            label={exportLabel}
                        />
                    ) : null}
                    {actionsExtra}
                </div>
            )}
        >
            {children}
        </PushsalePageShell>
    );
}

/**
 * Config-driven report filter toolbar (DRY #2).
 * Owns draft state via useInertiaFilters unless `draft` + `onChange` are provided.
 */
export function ReportFilterToolbar({
    title,
    routeUrl,
    filters = {},
    filterOptions = {},
    primary = ['date_type', 'date_from'],
    advanced = [],
    filterFields = null,
    pageCode,
    className = '',
    headerClassName = '',
    bodyClassName = '',
    primaryClassName = 'ps-report-v2-primary ps-report-toolbar-controls',
    advancedClassName = 'ps-report-v2-advanced-wrap ps-adv-filter-panel',
    advancedRowClassName = 'ps-report-v2-advanced ps-adv-filter-row ps-report-adv-grid',
    advancedCols = 4,
    actionsClassName = 'ps-report-toolbar-actions',
    collapsible = true,
    defaultFiltersCollapsed = false,
    showExport = true,
    exportLabel,
    searchLabel,
    notice = null,
    actionsExtra = null,
    children,
    draft: controlledDraft,
    onChange: controlledOnChange,
    onSearch: controlledOnSearch,
}) {
    const isControlled = controlledDraft !== undefined && typeof controlledOnChange === 'function';
    const { draft: internalDraft, set: internalSet, apply } = useInertiaFilters(
        routeUrl,
        isControlled ? {} : filters,
        { sync: !isControlled },
    );

    const draft = isControlled ? controlledDraft : internalDraft;
    const set = isControlled ? controlledOnChange : internalSet;
    const onSearch = controlledOnSearch ?? (() => {
        if (isControlled) {
            router.get(routeUrl, { ...cleanReportFilterPayload(draft), page: 1 }, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
            return;
        }
        apply();
    });

    return (
        <ReportFilterToolbarShell
            title={title}
            routeUrl={routeUrl}
            draft={draft}
            set={set}
            onSearch={onSearch}
            filterOptions={filterOptions}
            primary={primary}
            advanced={advanced}
            filterFields={filterFields}
            pageCode={pageCode}
            className={className}
            headerClassName={headerClassName}
            bodyClassName={bodyClassName}
            primaryClassName={primaryClassName}
            advancedClassName={advancedClassName}
            advancedRowClassName={advancedRowClassName}
            advancedCols={advancedCols}
            actionsClassName={actionsClassName}
            collapsible={collapsible}
            defaultFiltersCollapsed={defaultFiltersCollapsed}
            showExport={showExport}
            exportLabel={exportLabel}
            searchLabel={searchLabel}
            notice={notice}
            actionsExtra={actionsExtra}
        >
            {children}
        </ReportFilterToolbarShell>
    );
}
