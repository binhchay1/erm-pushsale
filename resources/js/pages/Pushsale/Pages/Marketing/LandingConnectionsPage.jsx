import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleSelect, PushsaleMultiSelect } from '@/components/pushsale/PushsaleSelect';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const connectionTabs = [
    ['facebook', 'KẾT NỐI FACEBOOK'],
    ['landing', 'KẾT NỐI NGUỒN DỮ LIỆU'],
    ['website', 'KẾT NỐI WEBSITE'],
    ['', 'TẤT CẢ'],
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
    const defaultMarketerId = String(marketers[0]?.id ?? '');
    const [query, setQuery] = useState({
        search: filters.search ?? '',
        marketer_user_id: filters.marketer_user_id ?? '',
        product_id: filters.product_id ?? '',
        connection_type: filters.connection_type ?? '',
        ad_channel: filters.ad_channel ?? '',
        approved: filters.approved ?? '',
        active: filters.active ?? '',
        per_page: filters.per_page ?? 20,
    });
    const [editingId, setEditingId] = useState(null);
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState(new Set());
    const [advancedOpen, setAdvancedOpen] = useState(false);
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
        router.patch(`${recordsUrl}/${row.id}/flags`, {
            manual_import: flags.manual_import ?? true,
            request_approval: flags.request_approval ?? true,
        }, {
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

    const deleteSelected = () => {
        const ids = [...selected];
        if (!ids.length || !window.confirm(`Xóa ${ids.length} kết nối đã chọn?`)) return;
        router.visit(recordsUrl, {
            method: 'delete',
            data: { ids },
            preserveScroll: true,
            onSuccess: () => setSelected(new Set()),
        });
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Kết nối dữ liệu" />
            <section className="ps-adminlte-page pslc-page pslc-page-v119" data-page-code={activeMenuCode}>
                <form className="pslc-filter ps-page-header ps-page-header-v119" onSubmit={search}>
                    <div className="ps-page-header-main">
                        <div className="ps-title ps-page-title">Kết nối dữ liệu</div>
                        <div className="ps-page-primary-filters pslc-header-controls">
                            <label className="pslc-check">
                                <input type="checkbox" checked={allProducts} onChange={(event) => {
                                    const checked = event.target.checked;
                                    setAllProducts(checked);
                                    if (checked) setQuery((old) => ({ ...old, product_id: '' }));
                                }} /> Chỉ lọc tất cả sản phẩm
                            </label>
                            <PushsaleSelect
                                className="pslc-filter-select"
                                searchable
                                options={marketerOptions}
                                value={query.marketer_user_id}
                                placeholder="--Chọn marketing--"
                                onChange={(value) => setQuery((old) => ({ ...old, marketer_user_id: value }))}
                            />
                            <PushsaleSelect
                                className="pslc-filter-product"
                                disabled={allProducts}
                                searchable
                                options={productOptions}
                                value={query.product_id}
                                placeholder="--Chọn sản phẩm--"
                                onChange={(value) => {
                                    setAllProducts(false);
                                    setQuery((old) => ({ ...old, product_id: value }));
                                }}
                            />
                            <input className="form-control pslc-keyword" placeholder="Tên nguồn dữ liệu / Tài khoản marketing" value={query.search} onChange={(event) => setQuery((old) => ({ ...old, search: event.target.value }))} />
                            <button className="btn btn-primary ps-btn-search"><i className="fa fa-search" /> Tìm kiếm</button>
                            <button type="button" className="btn btn-default pslc-cog" title="Chức năng"><i className="fa fa-gear" /></button>
                            <button type="button" className="btn-icon pslc-toggle" title="Bộ lọc nâng cao" onClick={() => setAdvancedOpen((value) => !value)}><i className={`fa ${advancedOpen ? 'fa-angle-double-up' : 'fa-angle-double-down'}`} /></button>
                        </div>
                    </div>
                    {advancedOpen && (
                        <div className="ps-page-advanced-filters">
                            <PushsaleSelect options={connectionTypeOptions} value={query.connection_type} placeholder="--Loại kết nối--" searchable={false} onChange={(value) => setQuery((old) => ({ ...old, connection_type: value }))} />
                            <PushsaleSelect options={channelOptions} value={query.ad_channel ?? ''} placeholder="--Kênh quảng cáo--" searchable onChange={(value) => setQuery((old) => ({ ...old, ad_channel: value }))} />
                            <PushsaleSelect options={[{ value: '1', label: 'Đã duyệt' }, { value: '0', label: 'Chưa duyệt' }]} value={query.approved ?? ''} placeholder="--Trạng thái duyệt--" searchable={false} onChange={(value) => setQuery((old) => ({ ...old, approved: value }))} />
                        </div>
                    )}
                </form>

                <div className="box-body pslc-tabs-shell">
                    <nav className="pslc-tabs">
                        {connectionTabs.map(([value, label]) => (
                            <button key={value || 'all'} type="button" className={String(query.connection_type ?? '') === String(value) ? 'active' : ''} onClick={() => switchTab(value)}>{label}</button>
                        ))}
                        <button type="button" className="btn btn-danger pslc-delete-auto" disabled={!canManage || selected.size === 0} onClick={deleteSelected}>
                            <i className="fa fa-trash" /> Xóa nguồn dữ liệu tự động
                        </button>
                    </nav>

                    <div className="box-body pslc-table-card">
                        <div className="pslc-table-scroll">
                            <table className="table table-bordered table-multi-select pslc-table">
                                <thead>
                                    <tr>
                                        <th className="text-center pslc-col-stt"><input type="checkbox" checked={rows.length > 0 && rows.every((row) => selected.has(row.id))} onChange={toggleAll} /><br />STT</th>
                                        <th className="text-center pslc-col-marketer">Marketing</th>
                                        <th className="text-center pslc-col-source">Tên nguồn kết nối<br /><span>Url nguồn dữ liệu</span></th>
                                        <th className="text-center no-wrap pslc-col-type">Loại kết nối<br /><span>Kênh quảng cáo</span></th>
                                        <th className="text-center pslc-col-products">Sản phẩm</th>
                                        <th className="text-center pslc-col-sales">Ưu tiên sale</th>
                                        <th className="text-center pslc-col-allocation">Cấu hình chia số</th>
                                        <th className="text-center pslc-col-api">Url kết nối V2</th>
                                        <th className="text-center pslc-col-small" title="Nhập thủ công">Nhập TC</th>
                                        <th className="text-center pslc-col-small">Duyệt</th>
                                        <th className="text-center pslc-col-updated">Cập nhật</th>
                                        <th className="text-center pslc-col-add">
                                            {canManage ? <button type="button" className="btn-icon pslc-add-link" onClick={openCreate}><i className="fa fa-plus" /> <span>Thêm</span></button> : null}
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
                                                <td className="text-center pslc-type-cell"><div>Nguồn dữ liệu</div><div className="pslc-channel">({channelOptions.find((item) => item.value === row.ad_channel)?.label ?? row.ad_channel ?? 'Facebook ads'})</div></td>
                                                <td className="text-left pslc-products-cell">{row.products?.length ? row.products.map((mapping) => <div key={mapping.id}>{mapping.product_name}</div>) : <span className="text-muted">Chờ duyệt gắn sản phẩm</span>}</td>
                                                <td className="text-left">{row.sale_names?.length ? row.sale_names.map((name, saleIndex) => <div key={`${name}-${saleIndex}`}>{saleIndex + 1}. {name}</div>) : ''}</td>
                                                <td className="text-left">{allocationLabels[row.allocation_method] ?? ''}</td>
                                                <td className="text-center pslc-api-cell">
                                                    <input
                                                        className="form-control pslc-api-copy-input"
                                                        readOnly
                                                        value={mainSource?.submit_url ?? row.api_base_url ?? ''}
                                                        title="Double click để chọn/copy URL"
                                                        onDoubleClick={(event) => { event.currentTarget.select(); copy(event.currentTarget.value); }}
                                                    />
                                                    <div className="pslc-api-actions">
                                                        <button type="button" className="btn-icon" onClick={() => copy(mainSource?.submit_url ?? row.api_base_url)}><i className="fa fa-link" /> Kết nối</button>
                                                        <button type="button" className="btn-icon" onClick={() => copy(mainSource?.submit_url ?? row.api_base_url)}><i className="fa fa-copy" /> Copy</button>
                                                    </div>
                                                </td>
                                                <td className="text-center"><input type="checkbox" checked={Boolean(row.manual_import)} onChange={() => updateFlags(row, { manual_import: true })} title="Bật nhập thủ công cho nguồn landing" /></td>
                                                <td className="text-center"><input type="checkbox" checked={Boolean(row.request_approval || row.is_approved)} onChange={() => updateFlags(row, { request_approval: true })} title="Nguồn landing bắt buộc qua duyệt trước khi chạy" /></td>
                                                <td className="text-center">{row.updated_by ?? 'admin'}<br />{row.updated_at}</td>
                                                <td className="text-center pslc-actions"><button type="button" className="btn-icon" onClick={() => openEdit(row)} title="Chỉnh sửa"><i className="fa fa-edit" /></button></td>
                                            </tr>
                                        );
                                    }) : (
                                        <tr><td colSpan="12" className="text-center text-muted">Không có dữ liệu kết nối.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <PushsalePagination meta={connections} routeUrl={routeUrl} filters={query} itemLabel="nguồn" />
                    </div>
                </div>
            </section>

            <PageDialog open={open} title={editingId ? 'CHỈNH SỬA NGUỒN DỮ LIỆU' : 'THÊM MỚI NGUỒN DỮ LIỆU'} onClose={() => setOpen(false)}>
                <form className="pslc-form pslc-source-form-v118 pslc-source-form-v119" onSubmit={save}>
                    <div className="pslc-dialog-body">
                        <div className="pslc-source-simple-form">
                            <label>Loại kết nối <span className="required">(*)</span></label>
                            <PushsaleSelect options={connectionTypeOptions} value={form.data.connection_type} onChange={(value) => form.setData('connection_type', value || 'landing')} searchable={false} />

                            <label>Cấu hình chia số</label>
                            <PushsaleSelect options={allocationOptions} value={form.data.allocation_method} onChange={(value) => form.setData('allocation_method', value || 'inherit')} searchable />

                            <label>Tên nguồn dữ liệu <span className="required">(*)</span></label>
                            <input className="form-control" required value={form.data.name} onChange={(event) => {
                                form.setData('name', event.target.value);
                                updateMainSource('name', event.target.value);
                            }} />

                            <label>Url nguồn dữ liệu <span className="required">(*)</span></label>
                            <input className="form-control" type="url" required value={form.data.sources?.[0]?.source_url ?? ''} onChange={(event) => updateMainSource('source_url', event.target.value)} />

                            <label>Url API</label>
                            <input className="form-control pslc-api-preview" readOnly disabled value={form.data.current_submit_url ?? ''} placeholder="Tự sinh sau khi lưu nguồn landing" />

                            <label>Sử dụng woocommerce</label>
                            <label className="pslc-inline-checkbox"><input type="checkbox" checked={Boolean(form.data.metadata?.woocommerce)} onChange={() => {}} /> Sử dụng woocommerce</label>

                            <label>Kênh quảng cáo <span className="required">(*)</span></label>
                            <PushsaleSelect options={channelOptions} value={form.data.ad_channel} onChange={(value) => form.setData('ad_channel', value || 'facebook_ads')} searchable />

                            <label>Upsale URL</label>
                            <input className="form-control" type="url" value={form.data.upsell_urls_text ?? ''} onChange={(event) => form.setData('upsell_urls_text', event.target.value)} placeholder="Không bắt buộc" />

                            <label>Chọn nhanh sale từ Nhóm sale</label>
                            <div className="pslc-inline-action-field">
                                <PushsaleSelect options={saleTeamOptions} value="" placeholder="--Chọn nhóm sale--" searchable onChange={applyTeam} />
                                <button type="button" className="btn-icon" title="Xóa chọn nhóm" onClick={() => form.setData('sale_user_ids', [])}><i className="fa fa-trash" /></button>
                                <button type="button" className="btn-icon" title="Làm mới danh sách sale"><i className="fa fa-refresh" /></button>
                            </div>

                            <label>Ưu tiên sale <span className="required">(*)</span></label>
                            <div className="pslc-inline-action-field">
                                <PushsaleMultiSelect
                                    label="Sale"
                                    options={saleOptions}
                                    selectedIds={form.data.sale_user_ids ?? []}
                                    enabled
                                    onEnabledChange={() => {}}
                                    onChange={(ids) => form.setData('sale_user_ids', ids)}
                                    allLabel="Tất cả Sale đều có quyền"
                                    placeholder="--Chọn sale ưu tiên--"
                                />
                                <button type="button" className="btn-icon" title="Xóa sale" onClick={() => form.setData('sale_user_ids', [])}><i className="fa fa-trash" /></button>
                            </div>

                            <div></div>
                            <div className="pslc-dialog-checks pslc-dialog-checks-two">
                                <label><input type="checkbox" checked readOnly /> Nhập thủ công</label>
                                <label><input type="checkbox" checked readOnly /> Duyệt</label>
                                <span className="small-tip">Sản phẩm/gói và ngân sách duyệt ở menu duyệt kết nối.</span>
                            </div>
                        </div>
                        {Object.keys(form.errors).length > 0 && <div className="alert alert-danger pslc-errors">{Object.entries(form.errors).map(([key, message]) => <div key={key}><strong>{key}:</strong> {message}</div>)}</div>}
                    </div>
                    <footer className="pslc-dialog-footer"><button className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Lưu</button></footer>
                </form>
            </PageDialog>
        </AppLayout>
    );
}
