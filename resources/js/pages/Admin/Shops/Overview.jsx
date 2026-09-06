import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function formatMoney(value) {
    const n = Number(value ?? 0);
    return new Intl.NumberFormat('vi-VN').format(n);
}

function formatPct(value) {
    if (value === null || value === undefined) return '—';
    return `${value}%`;
}

export default function ShopOverview({ overview, filters = {} }) {
    const t = useT();
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? overview?.period?.from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? overview?.period?.to ?? '');

    const applyFilters = (event) => {
        event.preventDefault();
        router.get('/admin/shops/overview', {
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true, replace: true });
    };

    const rows = overview?.shops ?? [];
    const totals = overview?.totals ?? {};
    const matrix = overview?.product_matrix ?? [];

    return (
        <AppLayout>
            <Head title={t('shops.overview_title')} />
            <section className="ps-adminlte-page ps-shop-overview-page" data-page-code="1.1.4">
                <PageHeader
                    title={t('shops.overview_title')}
                    subtitle={t('shops.overview_subtitle')}
                    pageCode="1.1.4"
                    filters={(
                        <form id="shop-overview-filters" className="ps-inline-filters" onSubmit={applyFilters}>
                            <label>
                                <span>{t('filters.date_from')}</span>
                                <input type="date" className="form-control input-sm" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                            </label>
                            <label>
                                <span>{t('filters.date_to')}</span>
                                <input type="date" className="form-control input-sm" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                            </label>
                        </form>
                    )}
                    actions={(
                        <>
                            <button type="submit" form="shop-overview-filters" className="btn btn-primary btn-sm">{t('common.apply')}</button>
                            <a className="btn btn-default btn-sm" href="/admin/shops">{t('shops.manage_link')}</a>
                        </>
                    )}
                />

                <h4>{t('shops.compare_heading')}</h4>
                <div className="table-responsive">
                    <table className="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{t('shops.col_name')}</th>
                                <th>{t('shops.col_contacts')}</th>
                                <th>{t('shops.col_tlc')}</th>
                                <th>{t('shops.col_appointments')}</th>
                                <th>{t('shops.col_tlh')}</th>
                                <th>{t('shops.col_sales')}</th>
                                <th>{t('shops.col_mkt_sales')}</th>
                                <th>{t('shops.col_products')}</th>
                                <th>{t('shops.col_stock')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.shop_id}>
                                    <td>{row.shop_name}</td>
                                    <td>{row.contacts}</td>
                                    <td>{formatPct(row.tlc)}</td>
                                    <td>{row.appointments}</td>
                                    <td>{formatPct(row.tlh)}</td>
                                    <td>{formatMoney(row.sales_revenue)}</td>
                                    <td>{formatMoney(row.marketing_revenue)}</td>
                                    <td>{row.product_count}</td>
                                    <td>{row.stock_quantity}</td>
                                </tr>
                            ))}
                            <tr>
                                <th>{t('common.total')}</th>
                                <th>{totals.contacts ?? 0}</th>
                                <th>{formatPct(totals.tlc)}</th>
                                <th>{totals.appointments ?? 0}</th>
                                <th>{formatPct(totals.tlh)}</th>
                                <th>{formatMoney(totals.sales_revenue)}</th>
                                <th>{formatMoney(totals.marketing_revenue)}</th>
                                <th>{totals.product_count ?? 0}</th>
                                <th>{totals.stock_quantity ?? 0}</th>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4>{t('shops.product_matrix_heading')}</h4>
                <p className="text-muted">{t('shops.metric_help')}</p>
                <p className="text-muted"><em>{t('shops.live_report_note')}</em></p>
                <div className="table-responsive">
                    <table className="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{t('shops.col_name')}</th>
                                <th>{t('shops.col_product')}</th>
                                <th>{t('shops.col_contacts')}</th>
                                <th>{t('shops.col_tlc')}</th>
                                <th>{t('shops.col_tlh')}</th>
                                <th>{t('shops.col_sales')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {matrix.length === 0 && (
                                <tr><td colSpan={6}>{t('shops.empty_matrix')}</td></tr>
                            )}
                            {matrix.map((row) => (
                                <tr key={`${row.shop_id}-${row.product_id}`}>
                                    <td>{row.shop_name}</td>
                                    <td>{row.product_name}</td>
                                    <td>{row.contacts}</td>
                                    <td>{formatPct(row.tlc)}</td>
                                    <td>{formatPct(row.tlh)}</td>
                                    <td>{formatMoney(row.revenue)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
