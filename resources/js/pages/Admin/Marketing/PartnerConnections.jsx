import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleSelect } from '@/components/pushsale/PushsaleSelect';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const connectionTabs = [
    ['facebook', 'KẾT NỐI FACEBOOK'],
    ['landing', 'KẾT NỐI LADIPAGE'],
    ['website', 'KẾT NỐI WEBSITE'],
    ['', 'TẤT CẢ'],
];

const connectionTypeLabels = {
    facebook: 'Facebook',
    landing: 'Ladipage',
    website: 'Website',
};

const channelLabels = {
    facebook_ads: 'Facebook ads',
    youtube: 'Youtube',
    google_ads: 'Google ads',
    tiktok_ads: 'Tiktok Ads',
    zalo_ads: 'Zalo ads',
    hotline: 'Hotline',
    seo: 'SEO',
    affiliate: 'Affiliate',
    other: 'Kênh khác',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function jsonFetch(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = body.message
            || Object.values(body.errors || {}).flat().join(' ')
            || 'Không thể thực hiện thao tác.';
        throw new Error(message);
    }
    return body;
}

function RoundTick({ checked, disabled = false, title, onChange }) {
    if (disabled) {
        return (
            <span className="ps-round-tick-wrap is-disabled" title={title} aria-label={title} role="img">
                <span className={`ps-round-tick ${checked ? 'is-on' : 'is-off'}`} aria-hidden="true">
                    {checked ? <i className="fa fa-check" /> : null}
                </span>
            </span>
        );
    }

    return (
        <label className="ps-round-tick-wrap" title={title}>
            <input
                type="checkbox"
                className="ps-round-tick-input"
                checked={Boolean(checked)}
                onChange={(event) => onChange?.(event.target.checked)}
                aria-label={title}
            />
            <span className={`ps-round-tick ${checked ? 'is-on' : 'is-off'}`} aria-hidden="true">
                {checked ? <i className="fa fa-check" /> : null}
            </span>
        </label>
    );
}

function copyText(value) {
    if (!value) return;
    navigator.clipboard?.writeText(value).then(
        () => toast.success('Đã copy.'),
        () => toast.error('Không copy được.'),
    );
}

function MarketerCell({ name, email }) {
    if (!name && !email) return <span className="text-muted">—</span>;
    return (
        <div className="ps-pc-marketer">
            <div>{name || '—'}</div>
            {email ? <div className="text-muted">({email})</div> : null}
        </div>
    );
}

function SourcePickerDialog({
    open,
    onOpenChange,
    partner,
    partnerName,
    marketers,
    products,
    onAttached,
}) {
    const [tab, setTab] = useState('');
    const [search, setSearch] = useState('');
    const [marketerId, setMarketerId] = useState('');
    const [productId, setProductId] = useState('');
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 10 });
    const [selected, setSelected] = useState(() => new Set());

    const marketerOptions = useMemo(() => [
        { value: '', label: '--Chọn marketing--' },
        ...marketers.map((user) => ({ value: String(user.id), label: user.name, subLabel: user.email })),
    ], [marketers]);

    const productOptions = useMemo(() => [
        { value: '', label: '--Chọn sản phẩm--' },
        ...products.map((product) => ({ value: String(product.id), label: product.name, subLabel: product.sku })),
    ], [products]);

    const load = async (nextPage = page, nextTab = tab) => {
        if (!partner || !open) return;
        setLoading(true);
        try {
            const params = new URLSearchParams({
                partner,
                page: String(nextPage),
                per_page: '10',
            });
            if (search.trim()) params.set('search', search.trim());
            if (marketerId) params.set('marketer_user_id', marketerId);
            if (productId) params.set('product_id', productId);
            if (nextTab) params.set('connection_type', nextTab);

            const result = await jsonFetch(`/admin/marketing/partner-connections/eligible-sources?${params}`);
            setRows(result.data || []);
            setMeta(result.meta || meta);
            setPage(result.meta?.current_page || nextPage);
        } catch (error) {
            toast.error(error.message || 'Không tải được danh sách nguồn.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!open) return;
        setSelected(new Set());
        setTab('');
        setSearch('');
        setMarketerId('');
        setProductId('');
        setPage(1);
        load(1, '');
        // eslint-disable-next-line react-hooks/exhaustive-deps -- load on open only
    }, [open, partner]);

    const toggleRow = (id, checked) => {
        setSelected((current) => {
            const next = new Set(current);
            if (checked) next.add(id);
            else next.delete(id);
            return next;
        });
    };

    const toggleAll = (checked) => {
        setSelected((current) => {
            const next = new Set(current);
            rows.forEach((row) => {
                if (row.already_attached) return;
                if (checked) next.add(row.id);
                else next.delete(row.id);
            });
            return next;
        });
    };

    const confirm = async () => {
        if (selected.size === 0) {
            toast.error('Chọn ít nhất một nguồn dữ liệu.');
            return;
        }
        setSubmitting(true);
        try {
            const result = await jsonFetch('/admin/marketing/partner-connections/attach-sources', {
                method: 'POST',
                body: JSON.stringify({
                    partner,
                    landing_connection_ids: [...selected],
                }),
            });
            toast.success(result.message || 'Đã gắn nguồn dữ liệu.');
            onOpenChange(false);
            onAttached?.();
        } catch (error) {
            toast.error(error.message || 'Không gắn được nguồn.');
        } finally {
            setSubmitting(false);
        }
    };

    const selectableRows = rows.filter((row) => !row.already_attached);
    const allSelected = selectableRows.length > 0 && selectableRows.every((row) => selected.has(row.id));

    return (
        <PushsaleDialog
            open={open}
            onOpenChange={onOpenChange}
            size="xl"
            title={`Chọn nguồn dữ liệu kết nối ${partnerName || ''}`.trim()}
            className="ps-pc-source-dialog"
            footer={(
                <div className="ps-pc-source-footer">
                    <button type="button" className="btn btn-primary" disabled={submitting} onClick={confirm}>
                        <i className={`fa ${submitting ? 'fa-spinner fa-spin' : 'fa-check'}`} /> Xác nhận
                    </button>
                    <button type="button" className="btn btn-default" disabled={submitting} onClick={() => onOpenChange(false)}>
                        <i className="fa fa-undo" /> Hủy bỏ
                    </button>
                </div>
            )}
        >
            <div className="ps-pc-source-toolbar">
                <PushsaleSelect
                    className="ps-pc-filter-select"
                    options={marketerOptions}
                    value={marketerId}
                    onChange={setMarketerId}
                    placeholder="--Chọn marketing--"
                />
                <PushsaleSelect
                    searchable
                    className="ps-pc-filter-select"
                    options={productOptions}
                    value={productId}
                    onChange={setProductId}
                    placeholder="--Chọn sản phẩm--"
                />
                <input
                    className="form-control"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Tên source / Tài khoản marketing"
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            load(1, tab);
                        }
                    }}
                />
                <button type="button" className="btn btn-sm btn-primary" onClick={() => load(1, tab)} disabled={loading}>
                    <i className="fa fa-search" /> Tìm kiếm
                </button>
            </div>

            <div className="ps-pc-source-tabs">
                {connectionTabs.map(([value, label]) => (
                    <button
                        key={value || 'all'}
                        type="button"
                        className={tab === value ? 'is-active' : ''}
                        onClick={() => {
                            setTab(value);
                            load(1, value);
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div className="ps-table-scroll">
                <table className="table table-bordered ps-source-table ps-pc-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Marketing</th>
                            <th>Tên nguồn kết nối / Url Landing</th>
                            <th>Loại kết nối / Kênh quảng cáo</th>
                            <th>Sản phẩm</th>
                            <th>Ưu tiên sale</th>
                            <th>Url kết nối</th>
                            <th>Nhập TC</th>
                            <th>Duyệt</th>
                            <th>Cập nhật</th>
                            <th>
                                <input type="checkbox" checked={allSelected} onChange={(event) => toggleAll(event.target.checked)} />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? rows.map((row) => (
                            <tr key={row.id} className={row.already_attached ? 'is-attached' : ''}>
                                <td className="text-center">{row.index}</td>
                                <td><MarketerCell name={row.marketer} email={row.marketer_email} /></td>
                                <td>
                                    <div><strong>{row.source}</strong></div>
                                    {row.url ? <div className="text-muted ps-pc-url">{row.url}</div> : null}
                                </td>
                                <td className="text-center">
                                    <div>{connectionTypeLabels[row.connection_type] || row.connection_type || '—'}</div>
                                    {row.channel ? (
                                        <div className="ps-pc-channel">({channelLabels[row.channel] || row.channel})</div>
                                    ) : null}
                                </td>
                                <td>{row.product || '—'}</td>
                                <td>{row.sale_priority || ''}</td>
                                <td>
                                    <div className="ps-pc-url" title={row.webhook_url}>{row.webhook_url}</div>
                                    {row.webhook_url ? (
                                        <button type="button" className="btn-link ps-pc-copy" onClick={() => copyText(row.webhook_url)}>
                                            <i className="fa fa-copy" /> Copy
                                        </button>
                                    ) : null}
                                </td>
                                <td className="text-center">
                                    <RoundTick checked={row.manual_import} disabled title="Nhập thủ công" />
                                </td>
                                <td className="text-center">
                                    <RoundTick checked={row.approved} disabled title="Duyệt" />
                                </td>
                                <td className="text-center">
                                    <div>{row.updated_by || '—'}</div>
                                    <div className="text-muted">{row.updated_at || ''}</div>
                                </td>
                                <td className="text-center">
                                    <input
                                        type="checkbox"
                                        disabled={row.already_attached}
                                        checked={selected.has(row.id) || row.already_attached}
                                        onChange={(event) => toggleRow(row.id, event.target.checked)}
                                        title={row.already_attached ? 'Đã gắn đối tác này' : 'Chọn nguồn'}
                                    />
                                </td>
                            </tr>
                        )) : (
                            <TableEmptyRow
                                colSpan={11}
                                message={loading ? 'Đang tải…' : 'Không có nguồn dữ liệu phù hợp.'}
                                className="text-center ps-empty"
                            />
                        )}
                    </tbody>
                </table>
            </div>

            <div className="ps-pc-source-pager">
                <div className="ps-pc-source-range">
                    {meta.from || 0} - {meta.to || 0} / {meta.total || 0}
                </div>
                <div className="ps-pc-source-pages">
                    <button type="button" className="btn btn-xs btn-default" disabled={page <= 1 || loading} onClick={() => load(page - 1, tab)}>
                        <i className="fa fa-angle-left" />
                    </button>
                    {Array.from({ length: Math.min(10, meta.last_page || 1) }, (_, index) => {
                        const pageNumber = index + 1;
                        return (
                            <button
                                key={pageNumber}
                                type="button"
                                className={`btn btn-xs ${page === pageNumber ? 'btn-primary' : 'btn-default'}`}
                                disabled={loading}
                                onClick={() => load(pageNumber, tab)}
                            >
                                {pageNumber}
                            </button>
                        );
                    })}
                    <button
                        type="button"
                        className="btn btn-xs btn-default"
                        disabled={page >= (meta.last_page || 1) || loading}
                        onClick={() => load(page + 1, tab)}
                    >
                        <i className="fa fa-angle-right" />
                    </button>
                </div>
            </div>
        </PushsaleDialog>
    );
}

export default function PartnerConnectionsPage({
    providers = [],
    selectedPartner = 'cnvloyalty',
    connections = { data: [], meta: {} },
    filters = {},
    marketers = [],
    products = [],
    canManage = false,
    routeUrl = '/admin/marketing/partner-connections',
    activeMenuCode = '2.6.3',
}) {
    const page = usePage();
    const flashSuccess = page.props?.flash?.success;
    const [search, setSearch] = useState(filters.search ?? '');
    const [pickerOpen, setPickerOpen] = useState(false);
    const [toggling, setToggling] = useState(false);

    const current = useMemo(
        () => providers.find((item) => item.slug === selectedPartner) || providers[0] || null,
        [providers, selectedPartner],
    );

    const rows = connections?.data ?? [];
    const meta = connections?.meta ?? {};

    useEffect(() => {
        if (flashSuccess) toast.success(flashSuccess);
    }, [flashSuccess]);

    const visit = (next = {}) => {
        router.get(routeUrl, {
            partner: next.partner ?? selectedPartner,
            search: next.search ?? search,
            page: next.page ?? 1,
            per_page: next.per_page ?? meta.per_page ?? 20,
        }, { preserveState: true, replace: true });
    };

    const selectPartner = (slug) => {
        if (slug === selectedPartner) return;
        router.get(routeUrl, { partner: slug, search: '' }, { preserveState: true, replace: true });
        setSearch('');
    };

    const toggleProvider = async (checked) => {
        if (!current || !canManage || toggling) return;
        setToggling(true);
        try {
            const result = await jsonFetch('/admin/marketing/partner-connections/provider', {
                method: 'PATCH',
                body: JSON.stringify({ partner: current.slug, is_active: checked }),
            });
            toast.success(result.message || 'Đã cập nhật.');
            router.reload({ preserveScroll: true, only: ['providers', 'selectedPartner'] });
        } catch (error) {
            toast.error(error.message || 'Không cập nhật được trạng thái đối tác.');
        } finally {
            setToggling(false);
        }
    };

    const patchFlags = (row, flags) => {
        if (!canManage) return;
        router.patch(`${routeUrl}/records/${row.id}/flags`, flags, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã cập nhật.'),
            onError: () => toast.error('Không cập nhật được.'),
        });
    };

    const removeRow = (row) => {
        if (!canManage) return;
        if (!window.confirm(`Gỡ nguồn “${row.source}” khỏi đối tác?`)) return;
        router.delete(`${routeUrl}/records/${row.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã gỡ nguồn.'),
        });
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Kết nối các đơn vị đối tác" />

            <section className="ps-adminlte-page ps-partner-connections-page" data-page-code="2.6.3">
                <PageHeader
                    title="Kết nối các đơn vị đối tác"
                    pageCode="2.6.3"
                    className="ps-partner-connections-header"
                    filters={(
                        <form
                            id="ps-partner-connections-filters"
                            className="ps-partner-connections-filters"
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit({ search, page: 1 });
                            }}
                        >
                            <input
                                className="form-control"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Tên nguồn dữ liệu"
                            />
                            <button className="btn btn-sm btn-primary" type="submit">
                                <i className="fa fa-search" /> Tìm kiếm
                            </button>
                        </form>
                    )}
                    collapsible={false}
                />

                <div className="ps-pc-partners">
                    {providers.map((provider) => (
                        <button
                            key={provider.slug}
                            type="button"
                            className={`ps-pc-partner-tile ${provider.slug === selectedPartner ? 'is-selected' : ''}`}
                            title={`Chọn đối tác ${provider.name}`}
                            onClick={() => selectPartner(provider.slug)}
                        >
                            <img src={provider.logo} alt={provider.name} />
                        </button>
                    ))}
                </div>

                {current ? (
                    <div className="ps-pc-info">
                        <div className="ps-pc-info__logo">
                            <img src={current.logo} alt={current.name} />
                        </div>
                        <div className="ps-pc-info__body">
                            <div className="ps-pc-info__title">
                                <strong>{current.name}</strong>
                                {current.caption && current.caption !== current.name ? (
                                    <> : <span>{current.caption}</span></>
                                ) : null}
                            </div>
                            <p className="ps-pc-info__desc">
                                {current.description.split('\n').map((line) => (
                                    <span key={line}>
                                        {line}
                                        <br />
                                    </span>
                                ))}
                            </p>
                        </div>
                        <div className="ps-pc-info__actions">
                            <label className="ps-pc-switch" title={current.is_active ? 'Đang bật' : 'Đang tắt'}>
                                <input
                                    type="checkbox"
                                    checked={Boolean(current.is_active)}
                                    disabled={!canManage || toggling}
                                    onChange={(event) => toggleProvider(event.target.checked)}
                                />
                                <span className="ps-pc-slider" />
                            </label>
                            <button
                                type="button"
                                className="btn-link ps-pc-data-list"
                                onClick={() => document.getElementById('ps-pc-table-anchor')?.scrollIntoView({ behavior: 'smooth' })}
                            >
                                Danh sách dữ liệu
                            </button>
                        </div>
                    </div>
                ) : null}

                <div className="ps-pc-toolbar" id="ps-pc-table-anchor">
                    <button
                        type="button"
                        className="btn btn-sm btn-primary"
                        disabled={!canManage || !current?.is_active}
                        title={!current?.is_active ? 'Bật đối tác trước khi chọn nguồn' : 'Chọn nguồn dữ liệu'}
                        onClick={() => setPickerOpen(true)}
                    >
                        <i className="fa fa-link" /> Chọn nguồn dữ liệu
                    </button>
                </div>

                <div className="ps-table-scroll ps-pc-table-wrap">
                    <table className="table table-bordered ps-source-table ps-pc-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Marketing</th>
                                <th>Tên nguồn kết nối</th>
                                <th>Đường link</th>
                                <th>Loại kết nối / Kênh quảng cáo</th>
                                <th>Sản phẩm</th>
                                <th>Ưu tiên sale</th>
                                <th>Token kết nối</th>
                                <th>Url kết nối</th>
                                <th title="Nhập thủ công">Nhập TC</th>
                                <th>Duyệt</th>
                                <th>Cập nhật</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row.id}>
                                    <td className="text-center">{row.index}</td>
                                    <td><MarketerCell name={row.marketer} email={row.marketer_email} /></td>
                                    <td><strong>{row.source}</strong></td>
                                    <td>
                                        {row.url ? (
                                            <a href={row.url} target="_blank" rel="noreferrer" className="ps-pc-url">{row.url}</a>
                                        ) : '—'}
                                    </td>
                                    <td className="text-center">
                                        <div>{connectionTypeLabels[row.connection_type] || row.connection_type || '—'}</div>
                                        {row.channel ? (
                                            <div className="ps-pc-channel">({channelLabels[row.channel] || row.channel})</div>
                                        ) : null}
                                    </td>
                                    <td>{row.product || '—'}</td>
                                    <td className="text-center">{row.sale_priority || '—'}</td>
                                    <td>
                                        <code className="ps-pc-token">{row.token || '—'}</code>
                                        {row.token ? (
                                            <button type="button" className="btn-link ps-pc-copy" onClick={() => copyText(row.token)}>
                                                <i className="fa fa-copy" /> Copy
                                            </button>
                                        ) : null}
                                    </td>
                                    <td>
                                        <div className="ps-pc-url" title={row.webhook_url}>{row.webhook_url}</div>
                                        {row.webhook_url ? (
                                            <button type="button" className="btn-link ps-pc-copy" onClick={() => copyText(row.webhook_url)}>
                                                <i className="fa fa-copy" /> Copy
                                            </button>
                                        ) : null}
                                    </td>
                                    <td className="text-center">
                                        <RoundTick
                                            checked={row.manual_import}
                                            disabled={!canManage}
                                            title="Nhập thủ công"
                                            onChange={(checked) => patchFlags(row, { manual_import: checked })}
                                        />
                                    </td>
                                    <td className="text-center">
                                        <RoundTick
                                            checked={row.approved}
                                            disabled={!canManage}
                                            title="Duyệt"
                                            onChange={(checked) => patchFlags(row, { is_approved: checked })}
                                        />
                                    </td>
                                    <td className="text-center">
                                        <div>{row.updated_by || '—'}</div>
                                        <div className="text-muted">{row.updated_at || ''}</div>
                                    </td>
                                    <td className="text-center">
                                        {canManage ? (
                                            <button type="button" className="btn btn-xs btn-danger" onClick={() => removeRow(row)}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        ) : null}
                                    </td>
                                </tr>
                            )) : (
                                <TableEmptyRow colSpan={13} message="Chưa có nguồn dữ liệu gắn với đối tác này." className="text-center ps-empty" />
                            )}
                        </tbody>
                    </table>
                </div>

                {meta.total > 0 ? (
                    <PushsalePagination
                        meta={meta}
                        routeUrl={routeUrl}
                        filters={{
                            partner: selectedPartner,
                            search,
                            per_page: meta.per_page ?? 20,
                        }}
                        scrollTargetId="ps-pc-table-anchor"
                    />
                ) : null}
            </section>

            <SourcePickerDialog
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                partner={selectedPartner}
                partnerName={current?.name}
                marketers={marketers}
                products={products}
                onAttached={() => router.reload({ preserveScroll: true })}
            />
        </AppLayout>
    );
}
