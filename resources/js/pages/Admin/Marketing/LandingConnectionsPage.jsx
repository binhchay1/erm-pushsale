import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import PageHeader from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleSelect, PushsaleMultiSelect } from '@/components/pushsale/PushsaleSelect';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { ConfirmActionDialog } from '@/components/ui/ConfirmActionDialog';
import { useT } from '@/providers/I18nProvider';

const connectionTabs = [
    ['facebook', 'tab_facebook'],
    ['landing', 'tab_landing'],
    ['website', 'tab_website'],
    ['', 'tab_all'],
];

const connectionTypeOptions = [
    { value: 'landing', label: 'Landing' },
    { value: 'website', label: 'Website' },
    { value: 'facebook', label: 'Facebook' },
];

const channelOptions = [
    { value: 'facebook_ads', label: 'Facebook ads' },
    { value: 'youtube', label: 'Youtube' },
    { value: 'google_ads', label: 'Google ads' },
    { value: 'tiktok_ads', label: 'Tiktok Ads' },
    { value: 'zalo_ads', label: 'Zalo ads' },
    { value: 'hotline', label: 'Hotline' },
    { value: 'seo', label: 'SEO' },
    { value: 'affiliate', label: 'Affiliate' },
    { value: 'other', label: 'Kênh khác' },
];

const allocationOptions = [
    { value: 'inherit', label: '--Chọn cấu hình chia số--' },
    { value: 'round_robin', label: 'Cấu hình chia số luân phiên' },
    { value: 'priority', label: 'Cấu hình chia số theo ưu tiên sale' },
    { value: 'manual', label: 'Không tự chia số' },
];

const sourceTypeLabels = {
    main: 'Nguồn dữ liệu',
    upsell: 'Nguồn upsale',
};

const allocationLabels = {
    inherit: 'Theo cấu hình chia số chung',
    round_robin: 'Luân phiên sale ưu tiên',
    priority: 'Theo thứ tự ưu tiên sale',
    manual: 'Không tự chia số',
};

const blankKey = () => globalThis.crypto?.randomUUID?.() ?? `src_${Date.now()}_${Math.random().toString(16).slice(2)}`;

const blankSource = (sourceType = 'main') => ({
    id: null,
    client_key: blankKey(),
    name: '',
    source_type: sourceType,
    source_url: '',
    redirect_url: '',
    sort_order: 0,
    is_active: true,
});

const blankProduct = (sourceKey = '') => ({
    product_id: '',
    source_key: sourceKey,
    item_type: 'product',
    external_field: '',
    external_value: '',
    quantity: 1,
    unit_price_override: '',
    is_default: true,
});

const defaultBudgetDates = () => {
    const pad = (value) => String(value).padStart(2, '0');
    const start = new Date();
    const end = new Date();
    end.setDate(end.getDate() + 29);
    return {
        start: `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`,
        end: `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`,
    };
};

const blankForm = (marketerId = '') => {
    const source = blankSource('main');
    const budgetDates = defaultBudgetDates();
    return {
        name: '',
        marketer_user_id: marketerId,
        connection_type: 'landing',
        ad_channel: 'facebook_ads',
        allocation_method: 'inherit',
        budget_type: 'total',
        budget_amount: 0,
        budget_start_date: budgetDates.start,
        budget_end_date: budgetDates.end,
        success_url: '',
        manual_import: true,
        request_approval: true,
        is_approved: false,
        is_active: true,
        notes: '',
        upsell_urls_text: '',
        sources: [{ ...source, name: 'Nguồn dữ liệu' }],
        products: [],
        sale_user_ids: [],
    };
};

function normalizeEditableSources(sources = []) {
    const normalized = (sources ?? [])
        .filter((source) => ['main', 'upsell'].includes(source?.source_type))
        .map((source, index) => ({
            ...source,
            source_type: source.source_type === 'upsell' ? 'upsell' : 'main',
            sort_order: source.sort_order ?? index,
        }));
    if (!normalized.some((source) => source.source_type === 'main')) normalized.unshift(blankSource('main'));
    return normalized;
}

function splitUpsellUrls(value = '') {
    return String(value ?? '')
        .split(/\r?\n|,/)
        .map((url) => url.trim())
        .filter(Boolean)
        .filter((url, index, source) => source.indexOf(url) === index);
}

function upsellUrlsText(sources = []) {
    return (sources ?? [])
        .filter((source) => source.source_type === 'upsell')
        .map((source) => source.source_url)
        .filter(Boolean)
        .join('\n');
}

function mergeUpsellSources(currentSources = [], urls = []) {
    const main = (currentSources ?? []).find((source) => source.source_type === 'main') ?? blankSource('main');
    const oldUpsells = (currentSources ?? []).filter((source) => source.source_type === 'upsell');
    const upsells = urls.map((url, index) => ({
        ...(oldUpsells.find((source) => source.source_url === url) ?? oldUpsells[index] ?? blankSource('upsell')),
        name: `Trang upsale ${index + 1}`,
        source_type: 'upsell',
        source_url: url,
        redirect_url: '',
        sort_order: index + 1,
        is_active: true,
    }));
    return [main, ...upsells].map((source, index) => ({ ...source, sort_order: index }));
}

function cleanPayload(data) {
    const payload = { ...data };
    // Contract v130: nguồn landing luôn nhập thủ công và luôn phải qua menu duyệt.
    payload.manual_import = true;
    payload.request_approval = true;
    const urls = splitUpsellUrls(payload.upsell_urls_text);
    const sources = mergeUpsellSources(payload.sources, urls);
    const main = sources.find((source) => source.source_type === 'main') ?? blankSource('main');
    const mainSource = {
        ...main,
        name: payload.name || main.name || 'Nguồn dữ liệu',
        redirect_url: urls[0] || main.redirect_url || payload.success_url || '',
        is_active: true,
    };
    payload.sources = [mainSource, ...sources.filter((source) => source.source_type !== 'main')];
    // Trang 2.4.1 chỉ tạo/sửa nguồn dữ liệu. Product/package được xử lý duy nhất ở menu duyệt.
    // Không gửi products từ dialog này để tránh validate theo flow cũ khi nguồn có upsell.
    delete payload.products;
    payload.success_url = payload.success_url || '';
    // Luồng mới: form tạo/sửa nguồn landing không duyệt trực tiếp.
    // Menu duyệt riêng sẽ gắn sản phẩm/gói + ngân sách rồi mới bật duyệt.
    payload.is_approved = false;
    delete payload.upsell_urls_text;
    return payload;
}

function PageDialog({ open, title, onClose, children }) {
    return (
        <PushsaleDialog
            open={Boolean(open)}
            onOpenChange={(nextOpen) => !nextOpen && onClose?.()}
            title={title}
            width="80vw"
            className="pslc-dialog pslc-source-dialog"
            bodyClassName="pslc-dialog-shell"
        >
            {children}
        </PushsaleDialog>
    );
}

export default function LandingConnectionsPage({
    connections,
    filters = {},
    marketers = [],
    sales = [],
    saleTeams = [],
    products = [],
    routeUrl = '/admin/marketing/landing-connections',
    recordsUrl = '/admin/marketing/landing-connections/records',
    canManage = false,
    canApprove = false,
    activeMenuCode = '2.4.1',
}) {
    const t = useT();
    const l = (key, params = {}) => t(`pages.landing_connections.${key}`, params);
    const defaultMarketerId = String(marketers[0]?.id ?? '');
    const [query, setQuery] = useState({
        search: filters.search ?? '',
        marketer_user_id: filters.marketer_user_id ?? '',
        product_id: filters.product_id ?? '',
        connection_type: filters.connection_type ?? '',
        ad_channel: filters.ad_channel ?? '',
        approved: filters.approved ?? '',
        active: filters.active ?? '',
        creation_type: filters.creation_type ?? '',
        import_type: filters.import_type ?? '',
        allocation_method: filters.allocation_method ?? '',
        sale_user_id: filters.sale_user_id ?? '',
        per_page: filters.per_page ?? 20,
    });
    const [editingId, setEditingId] = useState(null);
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState(new Set());
    const [confirmAction, setConfirmAction] = useState(null);
    const [allProducts, setAllProducts] = useState(!filters.product_id);
    const form = useForm(blankForm(defaultMarketerId));
    const rows = connections?.data ?? [];

    const marketerOptions = useMemo(() => marketers.map((user) => ({
        value: String(user.id),
        label: user.name,
        subLabel: user.email,
    })), [marketers]);
    const saleOptions = useMemo(() => sales.map((user) => ({
        value: String(user.id),
        label: user.name,
        subLabel: user.email,
    })), [sales]);
    const saleTeamOptions = useMemo(() => saleTeams.map((team) => ({
        value: String(team.id),
        label: team.name,
        subLabel: `${team.users?.length ?? 0} sale`,
    })), [saleTeams]);
    const productCatalog = useMemo(() => products.map((product) => ({
        ...product,
        type: product.type ?? product.product_type ?? 'product',
    })), [products]);
    const productOptions = useMemo(() => productCatalog.map((product) => ({
        value: String(product.id),
        label: product.name,
        subLabel: product.sku ? `${product.sku}${product.type === 'combo' ? ' · Gói sản phẩm' : ''}` : (product.type === 'combo' ? 'Gói sản phẩm' : ''),
    })), [productCatalog]);
    const connectionTypeTranslatedOptions = useMemo(() => connectionTypeOptions.map((option) => ({ ...option, label: l(`connection_type.${option.value}`) })), [t]);
    const channelTranslatedOptions = useMemo(() => channelOptions.map((option) => ({ ...option, label: l(`channel.${option.value}`) })), [t]);
    const allocationTranslatedOptions = useMemo(() => allocationOptions.map((option) => ({ ...option, label: l(`allocation.${option.value}`) })), [t]);
    const approvalOptions = useMemo(() => [
        { value: '1', label: l('approved') },
        { value: '0', label: l('pending_approval') },
    ], [t]);
    const creationTypeOptions = [
        { value: 'manual', label: 'Tạo thủ công' },
        { value: 'auto', label: 'Tạo tự động' },
    ];
    const importTypeOptions = [
        { value: 'manual', label: 'Nhập thủ công' },
        { value: 'auto', label: 'Tự động' },
    ];
    const perPageOptions = [20, 50, 100, 200, 500, 1000, 999999].map((value) => ({
        value: String(value),
        label: value === 999999 ? '--Hiển thị tất--' : String(value),
    }));

    const search = (event) => {
        event?.preventDefault();
        const payload = Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '' && value !== null));
        router.get(routeUrl, payload, { preserveState: true, replace: true, preserveScroll: true });
    };

    const switchTab = (connectionType) => {
        const next = { ...query, connection_type: connectionType };
        setQuery(next);
        router.get(routeUrl, Object.fromEntries(Object.entries(next).filter(([, item]) => item !== '')), {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const openCreate = () => {
        setEditingId(null);
        const data = blankForm(query.marketer_user_id || defaultMarketerId);
        form.setData(data);
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row) => {
        const editableSources = normalizeEditableSources(row.sources ?? []);
        const mainSource = editableSources.find((source) => source.source_type === 'main') ?? blankSource('main');
        const sourceKeys = new Set(editableSources.map((source) => String(source.client_key)));
        setEditingId(row.id);
        form.setData({
            ...blankForm(String(row.marketer_user_id ?? defaultMarketerId)),
            name: row.name ?? '',
            marketer_user_id: String(row.marketer_user_id ?? defaultMarketerId),
            connection_type: row.connection_type ?? 'landing',
            ad_channel: row.ad_channel ?? 'facebook_ads',
            allocation_method: row.allocation_method ?? 'inherit',
            budget_type: row.budget_type ?? 'total',
            budget_amount: Number(row.budget_amount ?? 0),
            budget_start_date: row.budget_start_date ?? defaultBudgetDates().start,
            budget_end_date: row.budget_end_date ?? defaultBudgetDates().end,
            success_url: row.success_url ?? '',
            manual_import: true,
            request_approval: true,
            is_approved: Boolean(row.is_approved),
            is_active: Boolean(row.is_active),
            notes: row.notes ?? '',
            upsell_urls_text: upsellUrlsText(editableSources),
            sources: [{ ...mainSource, name: row.name ?? mainSource.name ?? 'Nguồn dữ liệu' }, ...editableSources.filter((source) => source.source_type !== 'main')],
            products: (row.products ?? []).length
                ? row.products.map((product, index) => ({
                    ...product,
                    product_id: String(product.product_id ?? ''),
                    source_key: sourceKeys.has(String(product.source_key ?? '')) ? product.source_key : mainSource.client_key,
                    unit_price_override: product.unit_price_override ?? '',
                    is_default: index === 0 ? true : Boolean(product.is_default),
                }))
                : [],
            sale_user_ids: row.sale_user_ids ?? [],
            current_submit_url: row.sources?.find((source) => source.source_type === 'main')?.submit_url ?? '',
        });
        form.clearErrors();
        setOpen(true);
    };

    const updateMainSource = (key, value) => {
        const sources = form.data.sources.length ? form.data.sources : [blankSource('main')];
        form.setData('sources', sources.map((source, index) => index === 0 ? { ...source, source_type: 'main', [key]: value } : source));
    };

    const updateFirstProduct = (productId) => {
        const sourceKey = form.data.sources[0]?.client_key ?? '';
        const product = productCatalog.find((item) => String(item.id) === String(productId));
        const productsNext = form.data.products.length ? [...form.data.products] : [blankProduct(sourceKey)];
        productsNext[0] = {
            ...productsNext[0],
            product_id: productId,
            source_key: productsNext[0].source_key || sourceKey,
            item_type: product?.type === 'combo' ? 'combo' : 'product',
            is_default: true,
        };
        form.setData('products', productsNext);
    };

    const applyTeam = (teamId) => {
        const team = saleTeams.find((item) => String(item.id) === String(teamId));
        if (!team) return;
        const ids = new Set((form.data.sale_user_ids ?? []).map(Number));
        (team.users ?? []).forEach((user) => ids.add(Number(user.id)));
        form.setData('sale_user_ids', [...ids]);
    };

    const save = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        form.transform(cleanPayload);
        editingId ? form.put(`${recordsUrl}/${editingId}`, options) : form.post(recordsUrl, options);
    };

    const copy = async (value) => {
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
        } catch {
            const node = document.createElement('textarea');
            node.value = value;
            document.body.appendChild(node);
            node.select();
            document.execCommand('copy');
            node.remove();
        }
    };


    const updateFlags = (row, flags) => {
        if (!row?.id) return;
        const payload = {};
        if (Object.prototype.hasOwnProperty.call(flags, 'manual_import')) payload.manual_import = Boolean(flags.manual_import);
        if (Object.prototype.hasOwnProperty.call(flags, 'request_approval')) payload.request_approval = Boolean(flags.request_approval);
        if (Object.keys(payload).length === 0) return;
        router.patch(`${recordsUrl}/${row.id}/flags`, payload, {
            preserveScroll: true,
            preserveState: true,
            only: ['connections', 'flash'],
        });
    };

    const toggleSelected = (id) => setSelected((current) => {
        const next = new Set(current);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });

    const toggleAll = () => setSelected((current) => {
        const rowIds = rows.map((row) => row.id);
        const allSelected = rowIds.length > 0 && rowIds.every((id) => current.has(id));
        const next = new Set(current);
        rowIds.forEach((id) => allSelected ? next.delete(id) : next.add(id));
        return next;
    });

    const performDeleteSelected = (ids) => {
        router.visit(recordsUrl, {
            method: 'delete',
            data: { ids },
            preserveScroll: true,
            onSuccess: () => setSelected(new Set()),
        });
    };

    const deleteSelected = () => {
        const ids = [...selected];
        if (!ids.length) return;
        setConfirmAction({
            title: l('delete_confirm_title'),
            description: l('delete_confirm_desc', { count: ids.length }),
            onConfirm: () => performDeleteSelected(ids),
        });
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={l('title')} />
            <section className="ps-adminlte-page pslc-page pslc-page-v119" data-page-code={activeMenuCode}>
                <PageHeader
                    className="pslc-header"
                    pageCode={activeMenuCode}
                    title={l('title')}
                    filters={(
                        <>
                            <label className="pslc-check">
                                <input type="checkbox" checked={allProducts} onChange={(event) => {
                                    const checked = event.target.checked;
                                    setAllProducts(checked);
                                    if (checked) setQuery((old) => ({ ...old, product_id: '' }));
                                }} /> {l('filter_all_products')}
                            </label>
                            <PushsaleSelect
                                className="pslc-filter-select"
                                searchable
                                options={marketerOptions}
                                value={query.marketer_user_id}
                                placeholder={l('select_marketing')}
                                onChange={(value) => setQuery((old) => ({ ...old, marketer_user_id: value }))}
                            />
                            <PushsaleSelect
                                className="pslc-filter-product"
                                disabled={allProducts}
                                searchable
                                options={productOptions}
                                value={query.product_id}
                                placeholder={l('select_product')}
                                onChange={(value) => {
                                    setAllProducts(false);
                                    setQuery((old) => ({ ...old, product_id: value }));
                                }}
                            />
                        </>
                    )}
                    actions={(
                        <>
                            <input
                                className="form-control pslc-keyword"
                                placeholder={l('keyword_placeholder')}
                                value={query.search}
                                onChange={(event) => setQuery((old) => ({ ...old, search: event.target.value }))}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        search();
                                    }
                                }}
                            />
                            <button type="button" className="btn btn-primary ps-btn-search" onClick={search}><i className="fa fa-search" /> {l('search')}</button>
                            <button type="button" className="btn btn-default pslc-cog" title={l('actions')}><i className="fa fa-gear" /></button>
                        </>
                    )}
                    advanced={(
                        <div className="pslc-advanced-row">
                            <PushsaleSelect options={channelTranslatedOptions} value={query.ad_channel ?? ''} placeholder={l('channel_placeholder')} searchable onChange={(value) => setQuery((old) => ({ ...old, ad_channel: value }))} />
                            <PushsaleSelect options={creationTypeOptions} value={query.creation_type ?? ''} placeholder="-- Chọn kiểu khởi tạo --" searchable={false} onChange={(value) => setQuery((old) => ({ ...old, creation_type: value }))} />
                            <PushsaleSelect options={approvalOptions} value={query.approved ?? ''} placeholder={l('approval_placeholder')} searchable={false} onChange={(value) => setQuery((old) => ({ ...old, approved: value }))} />
                            <PushsaleSelect options={saleOptions} value={query.sale_user_id ?? ''} placeholder="--Chọn sale--" searchable onChange={(value) => setQuery((old) => ({ ...old, sale_user_id: value }))} />
                            <PushsaleSelect options={importTypeOptions} value={query.import_type ?? ''} placeholder="--Chọn loại nhập--" searchable={false} onChange={(value) => setQuery((old) => ({ ...old, import_type: value }))} />
                            <PushsaleSelect options={allocationTranslatedOptions} value={query.allocation_method ?? ''} placeholder="--Chọn cấu hình chia số--" searchable={false} onChange={(value) => setQuery((old) => ({ ...old, allocation_method: value }))} />
                            <PushsaleSelect options={perPageOptions} value={String(query.per_page ?? 20)} searchable={false} onChange={(value) => setQuery((old) => ({ ...old, per_page: value }))} />
                        </div>
                    )}
                />

                <div className="box-body pslc-tabs-shell">
                    <nav className="pslc-tabs">
                        {connectionTabs.map(([value, labelKey]) => (
                            <button key={value || 'all'} type="button" className={String(query.connection_type ?? '') === String(value) ? 'active' : ''} onClick={() => switchTab(value)}>{l(labelKey)}</button>
                        ))}
                        <button type="button" className="btn btn-danger pslc-delete-auto" disabled={!canManage || selected.size === 0} onClick={deleteSelected}>
                            <i className="fa fa-trash" /> {l('delete_auto_sources')}
                        </button>
                    </nav>

                    <div className="box-body pslc-table-card">
                        <div className="pslc-table-scroll">
                            <table className="table table-bordered table-multi-select pslc-table">
                                <thead>
                                    <tr>
                                        <th className="text-center pslc-col-stt"><input type="checkbox" checked={rows.length > 0 && rows.every((row) => selected.has(row.id))} onChange={toggleAll} /><br />STT</th>
                                        <th className="text-center pslc-col-marketer">Marketing</th>
                                        <th className="text-center pslc-col-source">{l('col_source')}<br /><span>{l('col_source_url')}</span></th>
                                        <th className="text-center no-wrap pslc-col-type">{l('col_type')}<br /><span>{l('col_channel')}</span></th>
                                        <th className="text-center pslc-col-products">{l('col_product')}</th>
                                        <th className="text-center pslc-col-sales">{l('col_sale_priority')}</th>
                                        <th className="text-center pslc-col-allocation">{l('col_allocation')}</th>
                                        <th className="text-center pslc-col-api">{l('col_api_url')}</th>
                                        <th className="text-center pslc-col-small" title={l('manual_import')}>{l('manual_import')}</th>
                                        <th className="text-center pslc-col-small">{l('approve')}</th>
                                        <th className="text-center pslc-col-updated">{l('updated')}</th>
                                        <th className="text-center pslc-col-add">
                                            {canManage ? <button type="button" className="btn-icon pslc-add-link" onClick={openCreate}><i className="fa fa-plus" /> <span>{l('add')}</span></button> : null}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length ? rows.map((row, index) => {
                                        const mainSource = row.sources?.find((source) => source.source_type === 'main') ?? row.sources?.[0];
                                        return (
                                            <tr key={row.id}>
                                                <td className="text-center"><input type="checkbox" checked={selected.has(row.id)} onChange={() => toggleSelected(row.id)} /><br />{(connections.from ?? 1) + index} -</td>
                                                <td className="text-center pslc-td-marketer">{row.marketer ?? '—'}<br />{row.marketer_email && <span className="small-tip">({row.marketer_email})</span>}</td>
                                                <td className="text-left pslc-td-source">{row.name}<br /><span className="small-tip">{mainSource?.source_url ?? '—'}</span></td>
                                                <td className="text-center pslc-type-cell"><div>{l('source_data')}</div><div className="pslc-channel">({channelOptions.find((item) => item.value === row.ad_channel)?.label ?? row.ad_channel ?? 'Facebook ads'})</div></td>
                                                <td className="text-left pslc-products-cell">{row.products?.length ? row.products.map((mapping) => <div key={mapping.id}>{mapping.product_name}</div>) : <span className="text-muted">{l('waiting_product_approval')}</span>}</td>
                                                <td className="text-left">{row.sale_names?.length ? row.sale_names.map((name, saleIndex) => <div key={`${name}-${saleIndex}`}>{saleIndex + 1}. {name}</div>) : ''}</td>
                                                <td className="text-left">{l(`allocation_label.${row.allocation_method}`)}</td>
                                                <td className="text-center pslc-api-cell">
                                                    <input
                                                        className="form-control pslc-api-copy-input"
                                                        readOnly
                                                        value={mainSource?.submit_url ?? row.api_base_url ?? ''}
                                                        title={l('copy_api_hint')}
                                                        onDoubleClick={(event) => { event.currentTarget.select(); copy(event.currentTarget.value); }}
                                                    />
                                                    <div className="pslc-api-actions">
                                                        <button type="button" className="btn-icon" onClick={() => copy(mainSource?.submit_url ?? row.api_base_url)}><i className="fa fa-link" /> {l('connect')}</button>
                                                        <button type="button" className="btn-icon" onClick={() => copy(mainSource?.submit_url ?? row.api_base_url)}><i className="fa fa-copy" /> {l('copy')}</button>
                                                    </div>
                                                </td>
                                                <td className="text-center"><input type="checkbox" checked={Boolean(row.manual_import)} onChange={(event) => updateFlags(row, { manual_import: event.target.checked })} title={l('toggle_manual_import')} /></td>
                                                <td className="text-center"><input type="checkbox" checked={Boolean(row.request_approval)} onChange={(event) => updateFlags(row, { request_approval: event.target.checked })} title={l('toggle_approval')} /></td>
                                                <td className="text-center">{row.updated_by ?? 'admin'}<br />{row.updated_at}</td>
                                                <td className="text-center pslc-actions"><button type="button" className="btn-icon" onClick={() => openEdit(row)} title={l('edit')}><i className="fa fa-edit" /></button></td>
                                            </tr>
                                        );
                                    }) : (
                                        <tr><td colSpan="12" className="text-center text-muted">{l('empty')}</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PushsalePagination meta={connections} routeUrl={routeUrl} filters={query} itemLabel={l('item_label')} />
                    </div>
                </div>
            </section>

            <PageDialog open={open} title={editingId ? l('edit_dialog_title') : l('add_dialog_title')} onClose={() => setOpen(false)}>
                <form className="pslc-form pslc-source-form-v118 pslc-source-form-v119" onSubmit={save}>
                    <div className="pslc-dialog-body">
                        <div className="pslc-source-simple-form">
                            <label>{l('connection_type_label')} <span className="required">(*)</span></label>
                            <PushsaleSelect options={connectionTypeTranslatedOptions} value={form.data.connection_type} onChange={(value) => form.setData('connection_type', value || 'landing')} searchable={false} />

                            <label>{l('allocation_config')}</label>
                            <PushsaleSelect options={allocationTranslatedOptions} value={form.data.allocation_method} onChange={(value) => form.setData('allocation_method', value || 'inherit')} searchable />

                            <label>{l('source_name')} <span className="required">(*)</span></label>
                            <input className="form-control" required value={form.data.name} onChange={(event) => {
                                form.setData('name', event.target.value);
                                updateMainSource('name', event.target.value);
                            }} />

                            <label>{l('source_url')} <span className="required">(*)</span></label>
                            <input className="form-control" type="url" required value={form.data.sources?.[0]?.source_url ?? ''} onChange={(event) => updateMainSource('source_url', event.target.value)} />

                            <label>Url API</label>
                            <input className="form-control pslc-api-preview" readOnly disabled value={form.data.current_submit_url ?? ''} placeholder={l('auto_generated_after_save')} />

                            <label>{l('use_woocommerce')}</label>
                            <label className="pslc-inline-checkbox"><input type="checkbox" checked={Boolean(form.data.metadata?.woocommerce)} onChange={() => {}} /> {l('use_woocommerce')}</label>

                            <label>{l('ad_channel')} <span className="required">(*)</span></label>
                            <PushsaleSelect options={channelTranslatedOptions} value={form.data.ad_channel} onChange={(value) => form.setData('ad_channel', value || 'facebook_ads')} searchable />

                            <label>{l('upsale_url')}</label>
                            <input className="form-control" type="url" value={form.data.upsell_urls_text ?? ''} onChange={(event) => form.setData('upsell_urls_text', event.target.value)} placeholder={l('optional')} />

                            <label>{l('quick_sale_by_team')}</label>
                            <div className="pslc-inline-action-field">
                                <PushsaleSelect options={saleTeamOptions} value="" placeholder={l('select_sale_team')} searchable onChange={applyTeam} />
                                <button type="button" className="btn-icon" title={l('clear_team')} onClick={() => form.setData('sale_user_ids', [])}><i className="fa fa-trash" /></button>
                                <button type="button" className="btn-icon" title={l('refresh_sale_list')}><i className="fa fa-refresh" /></button>
                            </div>

                            <label>{l('sale_priority')} <span className="required">(*)</span></label>
                            <div className="pslc-inline-action-field">
                                <PushsaleMultiSelect
                                    label={l('sale')}
                                    options={saleOptions}
                                    selectedIds={form.data.sale_user_ids ?? []}
                                    enabled
                                    onEnabledChange={() => {}}
                                    onChange={(ids) => form.setData('sale_user_ids', ids)}
                                    allLabel={l('all_sales_allowed')}
                                    placeholder={l('select_priority_sale')}
                                />
                                <button type="button" className="btn-icon" title={l('clear_sale')} onClick={() => form.setData('sale_user_ids', [])}><i className="fa fa-trash" /></button>
                            </div>

                            <div></div>
                            <div className="pslc-dialog-checks pslc-dialog-checks-two">
                                <label><input type="checkbox" checked readOnly /> {l('manual_import')}</label>
                                <label><input type="checkbox" checked readOnly /> {l('approve')}</label>
                                <span className="small-tip">{l('approval_hint')}</span>
                            </div>
                        </div>
                        {Object.keys(form.errors).length > 0 && <div className="alert alert-danger pslc-errors">{Object.entries(form.errors).map(([key, message]) => <div key={key}><strong>{key}:</strong> {message}</div>)}</div>}
                    </div>
                    <footer className="pslc-dialog-footer"><button className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> {l('save')}</button></footer>
                </form>
            </PageDialog>
            <ConfirmActionDialog
                open={Boolean(confirmAction)}
                title={confirmAction?.title}
                description={confirmAction?.description}
                onCancel={() => setConfirmAction(null)}
                onConfirm={() => {
                    const action = confirmAction?.onConfirm;
                    setConfirmAction(null);
                    action?.();
                }}
            />
        </AppLayout>
    );
}
