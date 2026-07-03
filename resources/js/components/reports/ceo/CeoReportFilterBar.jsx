import { ChevronDown, ChevronsDown, ChevronsUp, HelpCircle, Search, Settings } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

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
    onShowNote,
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

    return (
        <div className="m-header-wrap">
            <div className="m-header">
                <div className="form-group">
                    <span className="module-title">{t('reports.ceo_report.module_title')}</span>
                </div>
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={local.date_type ?? ''}
                        onChange={(e) => set('date_type', e.target.value)}
                    >
                        <option value="">{t('filters.date_type_placeholder')}</option>
                        {(options?.dateTypes ?? []).map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <div className="date-range-wrap">
                        <input
                            type="date"
                            className="ps-input"
                            value={local.date_from ?? ''}
                            onChange={(e) => set('date_from', e.target.value)}
                        />
                        <input
                            type="date"
                            className="ps-input"
                            value={local.date_to ?? ''}
                            onChange={(e) => set('date_to', e.target.value)}
                        />
                    </div>
                </div>
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={local.delivery_status ?? ''}
                        onChange={(e) => set('delivery_status', e.target.value)}
                    >
                        <option value="">{t('filters.delivery_status_placeholder')}</option>
                        {(options?.deliveryStatuses ?? []).map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={local.reconciliation_status ?? ''}
                        onChange={(e) => set('reconciliation_status', e.target.value)}
                    >
                        <option value="">{t('filters.reconciliation_placeholder')}</option>
                        {(options?.reconciliationStatuses ?? []).map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <div className="divSearch">
                        <button
                            type="button"
                            className="btn-icon"
                            title={summaryCollapsed ? t('reports.ceo_report.show_summary') : t('reports.ceo_report.hide_summary')}
                            onClick={onToggleSummary}
                        >
                            {summaryCollapsed ? (
                                <ChevronsDown className="size-4" />
                            ) : (
                                <ChevronsUp className="size-4" />
                            )}
                        </button>
                        <button type="button" className="btn-ps-primary" onClick={applySearch}>
                            <Search className="size-3.5" />
                            {t('common.search')}
                        </button>
                        <button type="button" className="btn-help" title={t('reports.ceo_report.legend')} onClick={onShowNote}>
                            <HelpCircle className="size-7" />
                        </button>
                    </div>
                </div>
            </div>

            <div className="m-header">
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={local.parent_product_id ?? ''}
                        onChange={(e) => set('parent_product_id', e.target.value)}
                    >
                        {parentProducts.map((opt) => (
                            <option key={opt.value || 'all'} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={local.product_id ?? ''}
                        onChange={(e) => set('product_id', e.target.value)}
                    >
                        {products.map((opt) => (
                            <option key={opt.value || 'all'} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={local.discount_mode ?? ''}
                        onChange={(e) => set('discount_mode', e.target.value)}
                    >
                        {(options?.discountModes ?? []).map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <select
                        className="ps-select"
                        value={String(local.per_page ?? '20')}
                        onChange={(e) => set('per_page', e.target.value)}
                    >
                        {PER_PAGE_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.value === '999999' ? t('filters.per_page_all') : opt.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="form-group">
                    <label className="chkAdvanced">
                        <input
                            type="checkbox"
                            checked={!!local.no_closing_date_limit}
                            onChange={(e) => set('no_closing_date_limit', e.target.checked ? 1 : 0)}
                        />
                        {t('filters.no_closing_date_limit')}
                    </label>
                </div>
                <div className="form-group" style={{ position: 'relative' }}>
                    <button
                        type="button"
                        className="ichucnang"
                        onClick={() => setMenuOpen((v) => !v)}
                        aria-expanded={menuOpen}
                    >
                        <Settings className="size-4" />
                        <ChevronDown className="size-3" />
                    </button>
                    <ul className={`dropdown-menu-ps ${menuOpen ? 'open' : ''}`}>
                        <li>
                            <a
                                href="#"
                                onClick={(e) => {
                                    e.preventDefault();
                                    onExport?.('shipping');
                                    setMenuOpen(false);
                                }}
                            >
                                {t('reports.ceo_report.export_shipping')}
                            </a>
                        </li>
                        <li>
                            <a
                                href="#"
                                onClick={(e) => {
                                    e.preventDefault();
                                    onExport?.('sale');
                                    setMenuOpen(false);
                                }}
                            >
                                {t('reports.ceo_report.export_sale')}
                            </a>
                        </li>
                        <li>
                            <a
                                href="#"
                                onClick={(e) => {
                                    e.preventDefault();
                                    onExport?.('marketing');
                                    setMenuOpen(false);
                                }}
                            >
                                {t('reports.ceo_report.export_marketing')}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    );
}
