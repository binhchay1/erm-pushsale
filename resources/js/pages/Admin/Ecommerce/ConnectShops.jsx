import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function selectOptions(options, placeholder) {
    return (
        <>
            {placeholder ? <option value="">{placeholder}</option> : null}
            {(options ?? []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </>
    );
}

export default function ConnectShops({ filters = {}, platforms = [], warehouses = [], rows = [], routeUrl = '/admin/ecommerce/connect-shops' }) {
    const t = useT();
    const [draft, setDraft] = useState(filters);

    useEffect(() => setDraft(filters), [filters]);

    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const search = () => router.get(routeUrl, draft, { preserveScroll: true, preserveState: true, replace: true });
    const addUrl = `/admin/integrations#${draft.platform || 'tiktok'}`;

    const primaryFilters = (
        <div className="ps-ecommerce-shop-filters">
            <select value={draft.platform ?? 'tiktok'} onChange={(event) => set('platform', event.target.value)}>
                {selectOptions(platforms)}
            </select>
            <select value={draft.warehouse_id ?? ''} onChange={(event) => set('warehouse_id', event.target.value)}>
                {selectOptions(warehouses, t('pages.ecommerce_shops.choose_warehouse'))}
            </select>
            <input value={draft.keyword ?? ''} onChange={(event) => set('keyword', event.target.value)} placeholder={t('pages.ecommerce_shops.keyword_placeholder')} />
        </div>
    );

    return (
        <AppLayout activeMenuCode="2.9.1">
            <Head title={t('pages.ecommerce_shops.title')} />
            <PushsalePageShell
                title={t('pages.ecommerce_shops.title')}
                className="ps-ecommerce-shop-page"
                primaryFilters={primaryFilters}
                actions={(
                    <button type="button" className="btn btn-sm btn-primary" onClick={search}>
                        <i className="fa fa-search" /> {t('pages.ecommerce_shops.search')}
                    </button>
                )}
                collapsible={false}
            >
                <div className="ps-ecommerce-shop-table-wrap">
                    <table className="table table-bordered ps-ecommerce-shop-table">
                        <thead>
                            <tr>
                                <th className="text-center" style={{ width: 60 }}>STT</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.platform')}</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.warehouse')}</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.shop_id')}</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.shop_name')}</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.logo')}</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.note')}</th>
                                <th className="text-center no-wrap">{t('pages.ecommerce_shops.updated_at')}</th>
                                <th className="text-center no-wrap" style={{ width: 130 }}>
                                    <Link href={addUrl} className="btn-icon ps-ecommerce-add-link" title={t('pages.ecommerce_shops.add_connection')}>
                                        <i className="fa fa-plus" /> <span className="text">{t('pages.ecommerce_shops.add')}</span>
                                    </Link>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={`${row.platform}-${row.id}`}>
                                    <td className="text-center">{index + 1}</td>
                                    <td className="text-center">{row.platformLabel}</td>
                                    <td>{row.warehouseName}</td>
                                    <td className="text-center">{row.shopId || '—'}</td>
                                    <td>{row.shopName || '—'}</td>
                                    <td className="text-center">
                                        {row.logoUrl ? <img src={row.logoUrl} alt="logo" className="ps-ecommerce-logo" /> : '—'}
                                    </td>
                                    <td>{row.note}</td>
                                    <td className="text-center">{row.updatedAt ?? '—'}</td>
                                    <td className="text-center">
                                        <Link href={row.settingsUrl} className="btn-icon" title={t('pages.ecommerce_shops.edit_connection')}>
                                            <i className="fa fa-edit" />
                                        </Link>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={9} className="text-center ps-empty">{t('pages.ecommerce_shops.empty')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </PushsalePageShell>
        </AppLayout>
    );
}
