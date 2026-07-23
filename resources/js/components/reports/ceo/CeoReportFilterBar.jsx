import { ChevronDown, ChevronsDown, ChevronsUp, Search, Settings } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { useLocalizedFilterOptions } from '@/hooks/use-localized-filter-options';
import { useReportSearch } from '@/hooks/useReportSearch';
import { useT } from '@/providers/I18nProvider';

const PER_PAGE_OPTIONS = [
    { value: '20', label: '20' },
    { value: '50', label: '50' },
    { value: '100', label: '100' },
    { value: '200', label: '200' },
    { value: '500', label: '500' },
    { value: '1000', label: '1000' },
    { value: '999999', label: '—' },
];

export function CeoReportFilterBar({
    filters,
    filterOptions,
    routeUrl,
    onExport,
    summaryCollapsed = false,
    onToggleSummary,
}) {
    const t = useT();
    const { search } = useReportSearch(routeUrl, filters);
    const options = useLocalizedFilterOptions(filterOptions);
    const [menuOpen, setMenuOpen] = useState(false);
    const [local, setLocal] = useState(() => ({ ...filters }));

    useEffect(() => {
        setLocal({ ...filters });
    }, [filters]);

    const set = (key, value) => {
        setLocal((prev) => ({ ...prev, [key]: value }));
    };

    const applySearch = () => {
        setMenuOpen(false);
        search(local);
    };

    const parentProducts = useMemo(
        () => [{ value: '', label: t('filters.parent_product_placeholder') }, ...(options?.parentProducts ?? [])],
        [options?.parentProducts, t],
    );

    const products = useMemo(
        () => [{ value: '', label: t('filters.product_placeholder') }, ...(options?.products ?? [])],
        [options?.products, t],
    );

    const select = (key, value, setter, children) => (
        <select className="ps-select" value={value ?? ''} onChange={(e) => setter(key, e.target.value)}>
            {children}
        </select>
    );

    const primaryFilters = (
        <div className="ps-ceo-toolbar-grid ps-ceo-toolbar-primary">
            {select('date_type', local.date_type, set, (
                <>
                    <option value="">{t('filters.date_type_placeholder')}</option>
                    {(options?.dateTypes ?? []).map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                </>
            ))}
            <div className="date-range-wrap">
                <input type="date" className="ps-input" value={local.date_from ?? ''} onChange={(e) => set('date_from', e.target.value)} />
                <span className="ps-date-separator">-</span>
                <input type="date" className="ps-input" value={local.date_to ?? ''} onChange={(e) => set('date_to', e.target.value)} />
            </div>
            {select('delivery_status', local.delivery_status, set, (
                <>
                    <option value="">{t('filters.delivery_status_placeholder')}</option>
                    {(options?.deliveryStatuses ?? []).map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                </>
            ))}
            {select('reconciliation_status', local.reconciliation_status, set, (
                <>
                    <option value="">{t('filters.reconciliation_placeholder')}</option>
                    {(options?.reconciliationStatuses ?? []).map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                </>
            ))}
        </div>
    );

    const advancedFilters = (
        <div className="ps-ceo-toolbar-grid ps-ceo-toolbar-advanced">
            {select('parent_product_id', local.parent_product_id, set, parentProducts.map((opt) => <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>))}
            {select('product_id', local.product_id, set, products.map((opt) => <option key={opt.value || 'all'} value={opt.value}>{opt.label}</option>))}
            {select('discount_mode', local.discount_mode, set, (options?.discountModes ?? []).map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>))}
            {select('per_page', String(local.per_page ?? '20'), set, PER_PAGE_OPTIONS.map((opt) => <option key={opt.value} value={opt.value}>{opt.value === '999999' ? t('filters.per_page_all') : opt.label}</option>))}
            <label className="chkAdvanced ps-ceo-checkbox">
                <input type="checkbox" checked={!!local.no_closing_date_limit} onChange={(e) => set('no_closing_date_limit', e.target.checked ? 1 : 0)} />
                <span>{t('filters.no_closing_date_limit')}</span>
            </label>
        </div>
    );

    const exportMenu = (
        <div className="ps-ceo-export-menu">
            <button type="button" className="ichucnang" onClick={() => setMenuOpen((v) => !v)} aria-expanded={menuOpen} title="Xuất dữ liệu">
                <Settings className="size-4" />
                <ChevronDown className="size-3" />
            </button>
            <ul className={`dropdown-menu-ps ${menuOpen ? 'open' : ''}`}>
                {['shipping', 'sale', 'marketing'].map((type) => (
                    <li key={type}>
                        <a href="#" onClick={(e) => { e.preventDefault(); onExport?.(type); setMenuOpen(false); }}>
                            {t(`reports.ceo_report.export_${type}`)}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );

    return (
        <PushsalePageShell
            title={t('reports.ceo_report.module_title')}
            className="ps-ceo-page-chrome ps-report-toolbar-shell"
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={(
                <div className="ps-ceo-toolbar-actions">
                    <button type="button" className="btn-icon ps-ceo-summary-toggle" title={summaryCollapsed ? t('reports.ceo_report.show_summary') : t('reports.ceo_report.hide_summary')} onClick={onToggleSummary}>
                        {summaryCollapsed ? <ChevronsDown className="size-4" /> : <ChevronsUp className="size-4" />}
                    </button>
                    <button type="button" className="btn-ps-primary" onClick={applySearch}>
                        <Search className="size-3.5" />
                        {t('common.search')}
                    </button>
                    {exportMenu}
                </div>
            )}
        />
    );
}
