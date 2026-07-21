import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatDate, formatMoneyInput, parseMoneyInput } from '@/lib/format';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';

const connectionTypes = [
    ['landing', 'KẾT NỐI NGUỒN DỮ LIỆU'],
    ['website', 'KẾT NỐI WEBSITE'],
    ['facebook', 'KẾT NỐI FACEBOOK'],
];

const sourceTypeLabels = {
    main: 'Landing chính',
    upsell: 'Trang upsale',
    thank_you: 'Trang cảm ơn',
};

const allocationLabels = {
    inherit: 'Theo cấu hình chia số chung',
    round_robin: 'Luân phiên sale ưu tiên',
    priority: 'Theo thứ tự ưu tiên sale',
    manual: 'Không tự chia số',
};

const isoDate = (date) => {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};
const defaultBudgetDates = () => {
    const start = new Date();
    const end = new Date();
    end.setDate(end.getDate() + 29);

    return { start: isoDate(start), end: isoDate(end) };
};

const blankKey = () => globalThis.crypto?.randomUUID?.() ?? `src_${Date.now()}_${Math.random().toString(16).slice(2)}`;

const blankSource = (sourceType = 'main') => ({
    id: null,
    client_key: blankKey(),
    name: sourceType === 'main' ? 'Landing chính' : sourceType === 'upsell' ? 'Trang upsale' : 'Trang cảm ơn',
    source_type: sourceType,
    source_url: '',
    redirect_url: '',
    sort_order: 0,
    is_active: true,
});

const blankProduct = (isDefault = false, sourceKey = '') => ({
    product_id: '',
    source_key: sourceKey,
    item_type: 'product',
    external_field: '',
    external_value: '',
    quantity: 1,
    unit_price_override: '',
    is_default: isDefault,
});

const blankForm = () => {
    const mainSource = blankSource('main');
    const budgetDates = defaultBudgetDates();

    return {
        name: '',
        marketer_user_id: '',
        connection_type: 'landing',
        ad_channel: 'landing',
        allocation_method: 'inherit',
        budget_type: 'total',
        budget_amount: 0,
        budget_start_date: budgetDates.start,
        budget_end_date: budgetDates.end,
        success_url: '',
        manual_import: false,
        is_approved: false,
        is_active: true,
        notes: '',
        sources: [mainSource],
        products: [blankProduct(true, mainSource.client_key)],
        sale_user_ids: [],
    };
};

function PageDialog({ open, title, onClose, children }) {
    return (
        <PushsaleDialog
            open={Boolean(open)}
            onOpenChange={(nextOpen) => !nextOpen && onClose?.()}
            title={title}
            width="calc(100vw - 44px)"
            className="pslc-dialog"
            bodyClassName="pslc-dialog-shell"
        >
            {children}
        </PushsaleDialog>
    );
}

function Pagination({ paginator, perPage, onPerPage }) {
    const links = paginator?.links ?? [];
    return (
        <div className="pslc-pagination">
            <span>Hiển thị {paginator?.from ?? 0} - {paginator?.to ?? 0} / {paginator?.total ?? 0} nguồn dữ liệu</span>
            <div className="pslc-pages">
                {links.map((link, index) => (
                    <button
                        type="button"
                        key={`${link.label}-${index}`}
                        className={link.active ? 'active' : ''}
                        disabled={!link.url}
                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
            <label>Hiển thị
                <select value={perPage} onChange={(event) => onPerPage(event.target.value)}>
                    {[10, 20, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
                </select>
                dòng
            </label>
        </div>
    );
}

function MultiSalePicker({ sales, teams, selected, onChange }) {
    const selectedSet = useMemo(() => new Set(selected.map(Number)), [selected]);
    const toggle = (id) => {
        const numeric = Number(id);
        const next = new Set(selectedSet);
        next.has(numeric) ? next.delete(numeric) : next.add(numeric);
        onChange([...next]);
    };
    const addTeam = (team) => onChange([...new Set([...selected.map(Number), ...(team.users ?? []).map((user) => Number(user.id))])]);

    return (
        <div className="pslc-sale-picker">
            <div className="pslc-team-quick">
                <span>Chọn nhanh sale từ Nhóm sale</span>
                {teams.map((team) => <button key={team.id} type="button" onClick={() => addTeam(team)}>{team.name}</button>)}
                {selected.length > 0 && <button type="button" onClick={() => onChange([])}>Bỏ chọn</button>}
            </div>
            <div className="pslc-sale-list">
                {sales.map((sale, index) => (
                    <label key={sale.id}>
                        <input type="checkbox" checked={selectedSet.has(Number(sale.id))} onChange={() => toggle(sale.id)} />
                        <span>{index + 1}. {sale.name}</span>
                    </label>
                ))}
            </div>
        </div>
    );
}

export default function LandingConnectionsPage({
    connections,
    filters = {},
    marketers = [],
    sales = [],
    saleTeams = [],
    products = [],
    canManage = false,
    canApprove = false,
}) {
    const [query, setQuery] = useState({
        search: filters.search ?? '',
        marketer_user_id: filters.marketer_user_id ?? '',
        product_id: filters.product_id ?? '',
        connection_type: filters.connection_type ?? 'landing',
        active: filters.active ?? '',
        per_page: filters.per_page ?? 20,
    });
    const [editingId, setEditingId] = useState(null);
    const [open, setOpen] = useState(false);
    const [expanded, setExpanded] = useState(new Set());
    const [selected, setSelected] = useState(new Set());
    const [allProducts, setAllProducts] = useState(!filters.product_id);
    const form = useForm(blankForm());
    const rows = connections?.data ?? [];

    const search = (event) => {
        event?.preventDefault();
        const payload = Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '' && value !== null));
        router.get('/admin/marketing/landing-connections', payload, { preserveState: true, replace: true, preserveScroll: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(blankForm());
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row) => {
        setEditingId(row.id);
        form.setData({
            name: row.name ?? '',
            marketer_user_id: row.marketer_user_id ?? '',
            connection_type: row.connection_type ?? 'landing',
            ad_channel: row.ad_channel ?? '',
            allocation_method: row.allocation_method ?? 'inherit',
            budget_type: row.budget_type ?? 'total',
            budget_amount: Number(row.budget_amount ?? 0),
            budget_start_date: row.budget_start_date ?? '',
            budget_end_date: row.budget_end_date ?? '',
            success_url: row.success_url ?? '',
            manual_import: Boolean(row.manual_import),
            is_approved: Boolean(row.is_approved),
            is_active: Boolean(row.is_active),
            notes: row.notes ?? '',
            sources: (row.sources ?? []).length ? row.sources.map((source) => ({ ...source })) : [blankSource('main')],
            products: (row.products ?? []).length ? row.products.map((product) => ({ ...product, unit_price_override: product.unit_price_override ?? '' })) : [blankProduct(true)],
            sale_user_ids: row.sale_user_ids ?? [],
        });
        form.clearErrors();
        setOpen(true);
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        editingId
            ? form.put(`/admin/marketing/landing-connections/records/${editingId}`, options)
            : form.post('/admin/marketing/landing-connections/records', options);
    };

    const updateSource = (index, key, value) => form.setData('sources', form.data.sources.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    const removeSource = (index) => {
        const removedKey = form.data.sources[index]?.client_key;
        form.setData({
            ...form.data,
            sources: form.data.sources.filter((_, rowIndex) => rowIndex !== index),
            products: form.data.products.filter((mapping) => mapping.source_key !== removedKey),
        });
    };
    const addSource = (type = 'upsell') => form.setData('sources', [...form.data.sources, { ...blankSource(type), sort_order: form.data.sources.length }]);
    const updateProduct = (index, key, value) => form.setData('products', form.data.products.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    const removeProduct = (index) => form.setData('products', form.data.products.filter((_, rowIndex) => rowIndex !== index));
    const addProduct = () => {
        const mainKey = form.data.sources.find((source) => source.source_type === 'main')?.client_key ?? '';
        form.setData('products', [...form.data.products, blankProduct(false, mainKey)]);
    };

    const copy = async (value) => {
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

    const toggleExpanded = (id) => setExpanded((current) => {
        const next = new Set(current);
        next.has(id) ? next.delete(id) : next.add(id);
        return next;
    });

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

        router.visit('/admin/marketing/landing-connections/records', {
            method: 'delete',
            data: { ids },
            preserveScroll: true,
            onSuccess: () => setSelected(new Set()),
        });
    };

    return (
        <AppLayout>
            <Head title="Kết nối dữ liệu landing" />
            <section className="ps-adminlte-page pslc-page" data-page-code="2.4.1">
                <form className="pslc-filter" onSubmit={search}>
                    <div className="m-header-wrap">
                        <div className="m-header pslc-header">
                            <div className="ps-title">Kết nối dữ liệu</div>
                            <div className="pslc-header-controls">
                                <label className="pslc-check"><input type="checkbox" checked={allProducts} onChange={(event) => {
                                    const checked = event.target.checked;
                                    setAllProducts(checked);
                                    if (checked) setQuery((old) => ({ ...old, product_id: '' }));
                                }} /> Tất cả sản phẩm</label>
                                <select className="form-control" value={query.marketer_user_id} onChange={(event) => setQuery((old) => ({ ...old, marketer_user_id: event.target.value }))}>
                                    <option value="">--Marketing--</option>
                                    {marketers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                </select>
                                <select className="form-control" disabled={allProducts} value={query.product_id} onChange={(event) => {
                                    setAllProducts(false);
                                    setQuery((old) => ({ ...old, product_id: event.target.value }));
                                }}>
                                    <option value="">--Sản phẩm--</option>
                                    {products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}
                                </select>
                                <input className="form-control" placeholder="Tìm kiếm tên nguồn hoặc URL" value={query.search} onChange={(event) => setQuery((old) => ({ ...old, search: event.target.value }))} />
                                <button className="btn btn-primary"><i className="fa fa-search" /> Tìm kiếm</button>
                                <button type="button" className="btn btn-default" title="Cài đặt"><i className="fa fa-cog" /></button>
                                <button type="button" className="btn btn-default" title="Trợ giúp"><i className="fa fa-question-circle" /></button>
                            </div>
                        </div>
                    </div>
                </form>

                <nav className="pslc-tabs">
                    {connectionTypes.map(([value, label]) => (
                        <button
                            type="button"
                            key={value}
                            className={query.connection_type === value ? 'active' : ''}
                            onClick={() => {
                                const next = { ...query, connection_type: value };
                                setQuery(next);
                                router.get('/admin/marketing/landing-connections', Object.fromEntries(Object.entries(next).filter(([, item]) => item !== '')), { preserveState: true, replace: true });
                            }}
                        >{label}</button>
                    ))}
                    <button type="button" className={!query.connection_type ? 'active' : ''} onClick={() => {
                        const next = { ...query, connection_type: '' };
                        setQuery(next);
                        router.get('/admin/marketing/landing-connections', Object.fromEntries(Object.entries(next).filter(([, item]) => item !== '')), { preserveState: true, replace: true });
                    }}>TẤT CẢ</button>
                </nav>

                <div className="pslc-toolbar">
                    <button type="button" className="btn btn-danger" disabled={!canManage || selected.size === 0} onClick={deleteSelected}><i className="fa fa-trash" /> Xóa nguồn đã chọn ({selected.size})</button>
                    {canManage && <button type="button" className="btn btn-success" onClick={openCreate}><i className="fa fa-plus" /> Thêm nguồn kết nối</button>}
                </div>

                <div className="pslc-table-scroll">
                    <table className="pslc-table">
                        <thead>
                            <tr>
                                <th className="w-check"><input type="checkbox" checked={rows.length > 0 && rows.every((row) => selected.has(row.id))} onChange={toggleAll} aria-label="Chọn tất cả dòng hiện tại" /></th>
                                <th className="w-stt">STT</th>
                                <th>Marketing</th>
                                <th className="w-source">Tên nguồn kết nối<br /><span>Url nguồn dữ liệu</span></th>
                                <th>Loại kết nối<br /><span>Kênh quảng cáo</span></th>
                                <th className="w-products">Sản phẩm / gói sản phẩm</th>
                                <th className="w-budget">Ngân sách chạy<br /><span>Đơn vị: VND</span></th>
                                <th>Ưu tiên sale</th>
                                <th>Cấu hình chia số</th>
                                <th className="w-api">URL nhận dữ liệu</th>
                                <th>Nhập TC</th>
                                <th>Duyệt</th>
                                <th>Cập nhật</th>
                                <th className="w-actions">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row.id}>
                                    <td className="text-center"><input type="checkbox" checked={selected.has(row.id)} onChange={() => toggleSelected(row.id)} aria-label={`Chọn ${row.name}`} /></td>
                                    <td className="text-center">{(connections.from ?? 1) + index}</td>
                                    <td><strong>{row.marketer ?? '—'}</strong></td>
                                    <td>
                                        <button type="button" className="pslc-source-name" onClick={() => toggleExpanded(row.id)}>
                                            <i className={`fa fa-${expanded.has(row.id) ? 'minus' : 'plus'}-square-o`} /> {row.name}
                                        </button>
                                        {(expanded.has(row.id) ? row.sources : row.sources?.slice(0, 1))?.map((source) => (
                                            <small key={source.id}><b>{sourceTypeLabels[source.source_type]}:</b> {source.source_url}</small>
                                        ))}
                                    </td>
                                    <td><strong>{row.connection_type}</strong><small>{row.ad_channel || '—'}</small></td>
                                    <td>{row.products?.map((mapping) => <small key={mapping.id}><b>{mapping.quantity}×</b> {mapping.product_name}{mapping.external_value ? ` — ${mapping.external_value}` : ''}</small>)}</td>
                                    <td className="pslc-money-cell">
                                        <strong>{formatCurrency(row.budget_total ?? row.budget_amount ?? 0)}</strong>
                                        <small>{row.budget_type === 'daily' ? `${formatCurrency(row.budget_amount ?? 0)} / ngày` : 'Tổng ngân sách'}</small>
                                        {(row.budget_start_date || row.budget_end_date) && <small>{formatDate(row.budget_start_date)} - {formatDate(row.budget_end_date)}</small>}
                                    </td>
                                    <td>{row.sale_names?.length ? row.sale_names.map((name, saleIndex) => <small key={`${name}-${saleIndex}`}>{saleIndex + 1}. {name}</small>) : <span>Toàn bộ sale</span>}</td>
                                    <td><strong>{allocationLabels[row.allocation_method] ?? row.allocation_method}</strong><small>{row.contacts} contact đã nhận</small></td>
                                    <td>
                                        {row.sources?.map((source) => (
                                            <div className="pslc-api-line" key={source.id}>
                                                <span>{sourceTypeLabels[source.source_type]}</span>
                                                {source.submit_url
                                                    ? <button type="button" onClick={() => copy(source.submit_url)} title={source.submit_url}><i className="fa fa-copy" /> Copy URL</button>
                                                    : <em>Chỉ làm trang đích</em>}
                                            </div>
                                        ))}
                                    </td>
                                    <td className="text-center">{row.manual_import ? <i className="fa fa-check text-green" /> : ''}</td>
                                    <td className="text-center">{row.is_approved ? <i className="fa fa-check text-green" /> : <i className="fa fa-clock-o text-orange" />}</td>
                                    <td className="text-center"><strong>{row.updated_at}</strong></td>
                                    <td className="text-center pslc-actions">
                                        <button type="button" disabled={!canManage} onClick={() => openEdit(row)} title="Cập nhật"><i className="fa fa-pencil-square-o" /></button>
                                        <button type="button" disabled={!canManage} onClick={() => window.confirm(`Xóa kết nối ${row.name}?`) && router.delete(`/admin/marketing/landing-connections/records/${row.id}`, { preserveScroll: true, onSuccess: () => setSelected((current) => { const next = new Set(current); next.delete(row.id); return next; }) })} title="Xóa"><i className="fa fa-trash" /></button>
                                    </td>
                                </tr>
                            )) : <tr><td colSpan="14" className="ps-empty">Chưa có kết nối phù hợp. Hãy tạo đầy đủ nguồn, sản phẩm và cấu hình chia số trước khi nhận lead.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={connections} perPage={query.per_page} onPerPage={(value) => {
                    const next = { ...query, per_page: value };
                    setQuery(next);
                    router.get('/admin/marketing/landing-connections', next, { preserveState: true, replace: true });
                }} />
            </section>

            <PageDialog open={open} title={editingId ? 'CHỈNH SỬA NGUỒN DỮ LIỆU' : 'THÊM NGUỒN DỮ LIỆU'} onClose={() => setOpen(false)}>
                <form className="pslc-form" onSubmit={save}>
                    <div className="pslc-dialog-body">
                        <section className="pslc-form-section">
                            <h4>THÔNG TIN KẾT NỐI</h4>
                            <div className="pslc-form-grid">
                                <label>Marketing (*)
                                    <select className="form-control" required value={form.data.marketer_user_id} onChange={(event) => form.setData('marketer_user_id', event.target.value)}>
                                        <option value="">--Marketing--</option>
                                        {marketers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                                    </select>
                                </label>
                                <label>Loại kết nối (*)
                                    <select className="form-control" value={form.data.connection_type} onChange={(event) => form.setData('connection_type', event.target.value)}>
                                        <option value="landing">Landing</option><option value="website">Website</option><option value="facebook">Facebook</option>
                                    </select>
                                </label>
                                <label>Cấu hình chia số (*)
                                    <select className="form-control" value={form.data.allocation_method} onChange={(event) => form.setData('allocation_method', event.target.value)}>
                                        {Object.entries(allocationLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </select>
                                </label>
                                <label>Tên nguồn dữ liệu (*)<input className="form-control" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                                <label>Kênh quảng cáo (*)<input className="form-control" value={form.data.ad_channel} onChange={(event) => form.setData('ad_channel', event.target.value)} placeholder="landing / facebook / tiktok..." /></label>
                                <label>URL hoàn tất mặc định<input className="form-control" type="url" value={form.data.success_url} onChange={(event) => form.setData('success_url', event.target.value)} placeholder="https://.../cam-on" /></label>
                                <label className="span-2">Ghi chú cấu hình<input className="form-control" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} placeholder="Quy ước nguồn, sản phẩm, cách vận hành..." /></label>
                            </div>
                            <div className="pslc-budget-box">
                                <div className="pslc-budget-title">
                                    <strong>NGÂN SÁCH CHẠY</strong>
                                    <span>Toàn bộ số tiền nhập và báo cáo trong hệ thống đều là VND.</span>
                                </div>
                                <div className="pslc-budget-grid">
                                    <label>Loại ngân sách (*)
                                        <select className="form-control" value={form.data.budget_type} onChange={(event) => form.setData('budget_type', event.target.value)}>
                                            <option value="total">Tổng ngân sách cho cả kỳ</option>
                                            <option value="daily">Ngân sách mỗi ngày</option>
                                        </select>
                                    </label>
                                    <label>{form.data.budget_type === 'daily' ? 'Ngân sách/ngày (VND)' : 'Tổng ngân sách (VND)'} (*)
                                        <div className="pslc-money-input">
                                            <input className="form-control" inputMode="numeric" required value={formatMoneyInput(form.data.budget_amount)} onChange={(event) => form.setData('budget_amount', parseMoneyInput(event.target.value))} />
                                            <span>₫</span>
                                        </div>
                                    </label>
                                    <label>Từ ngày (*)<input className="form-control" type="date" required={Number(form.data.budget_amount) > 0} value={form.data.budget_start_date ?? ''} onChange={(event) => form.setData('budget_start_date', event.target.value)} /></label>
                                    <label>Đến ngày (*)<input className="form-control" type="date" required={Number(form.data.budget_amount) > 0} min={form.data.budget_start_date || undefined} value={form.data.budget_end_date ?? ''} onChange={(event) => form.setData('budget_end_date', event.target.value)} /></label>
                                    <div className="pslc-budget-preview">
                                        <span>Ngân sách kế hoạch toàn kỳ</span>
                                        <strong>{formatCurrency(form.data.budget_type === 'daily' && form.data.budget_start_date && form.data.budget_end_date ? Number(form.data.budget_amount || 0) * Math.max(1, Math.round((new Date(`${form.data.budget_end_date}T00:00:00`) - new Date(`${form.data.budget_start_date}T00:00:00`)) / 86400000) + 1) : Number(form.data.budget_amount || 0))}</strong>
                                    </div>
                                </div>
                                <p>Dashboard dùng chi tiêu thực tế theo ngày khi Marketing đã nhập. Nếu kỳ chưa có thực chi, hệ thống tự phân bổ ngân sách kế hoạch theo đúng số ngày giao nhau, không cộng trùng hai lần.</p>
                            </div>
                            <div className="pslc-inline-checks">
                                <label><input type="checkbox" checked={form.data.manual_import} onChange={(event) => form.setData('manual_import', event.target.checked)} /> Nhập thủ công</label>
                                <label><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /> Đang hoạt động</label>
                                <label title={!canApprove ? 'Chỉ Admin được duyệt kết nối' : ''}><input type="checkbox" disabled={!canApprove} checked={form.data.is_approved} onChange={(event) => form.setData('is_approved', event.target.checked)} /> Duyệt</label>
                            </div>
                        </section>

                        <section className="pslc-form-section">
                            <div className="pslc-section-title"><h4>NGUỒN LANDING / UPSELL</h4><div><button type="button" className="btn btn-xs btn-info" onClick={() => addSource('upsell')}><i className="fa fa-plus" /> Trang upsale</button><button type="button" className="btn btn-xs btn-default" onClick={() => addSource('thank_you')}><i className="fa fa-plus" /> Trang cảm ơn</button></div></div>
                            <p className="pslc-guide">Form của từng trang gửi trực tiếp tới URL API được sinh tự động. Landing chính sẽ redirect sang URL tiếp theo kèm <code>ps_flow</code>; trang upsale gửi lại mã này để gộp đúng khách và đúng đơn.</p>
                            <div className="pslc-source-editor">
                                {form.data.sources.map((source, index) => (
                                    <div className="pslc-source-card" key={source.client_key}>
                                        <div className="pslc-source-card-head"><strong>Nguồn {index + 1}</strong>{form.data.sources.length > 1 && source.source_type !== 'main' && <button type="button" onClick={() => removeSource(index)}><i className="fa fa-trash" /></button>}</div>
                                        <div className="pslc-form-grid source-grid">
                                            <label>Vai trò nguồn (*)<select className="form-control" value={source.source_type} disabled={source.source_type === 'main'} onChange={(event) => updateSource(index, 'source_type', event.target.value)}>{source.source_type === 'main' && <option value="main">Landing chính</option>}<option value="upsell">Trang upsale</option><option value="thank_you">Trang cảm ơn</option></select></label>
                                            <label>Tên nguồn (*)<input className="form-control" required value={source.name} onChange={(event) => updateSource(index, 'name', event.target.value)} /></label>
                                            <label className="span-2">URL nguồn dữ liệu (*)<input className="form-control" type="url" required value={source.source_url} onChange={(event) => updateSource(index, 'source_url', event.target.value)} placeholder="https://landing.example/..." /></label>
                                            <label className="span-2">Redirect sau khi nhận thành công<input className="form-control" type="url" value={source.redirect_url ?? ''} onChange={(event) => updateSource(index, 'redirect_url', event.target.value)} placeholder={source.source_type === 'main' ? 'URL trang upsale' : 'URL trang cảm ơn tiếp theo'} /></label>
                                            <label className="span-2">Ghi chú nguồn<input className="form-control" value={source.notes ?? ''} onChange={(event) => updateSource(index, 'notes', event.target.value)} placeholder="Tên form, vị trí form, quy ước field..." /></label>
                                            <label className="pslc-source-active"><input type="checkbox" checked={Boolean(source.is_active)} onChange={(event) => updateSource(index, 'is_active', event.target.checked)} /> Cho phép nhận dữ liệu từ nguồn này</label>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="pslc-form-section">
                            <div className="pslc-section-title"><h4>SẢN PHẨM / GÓI SẢN PHẨM</h4><button type="button" className="btn btn-xs btn-success" onClick={addProduct}><i className="fa fa-plus" /> Thêm gói</button></div>
                            <p className="pslc-guide">Giá và mã sản phẩm lấy từ backend. Có thể map chính xác giá trị option của form, ví dụ field <code>combo</code> = <code>Mua 2 Thỏi</code>; nhiều giá trị hợp lệ ngăn bằng <code>|</code>. Client không được tự gửi giá để tránh sai doanh thu và tồn kho.</p>
                            <div className="pslc-product-editor">
                                {form.data.products.map((mapping, index) => (
                                    <div className="pslc-product-row" key={`product-${index}`}>
                                        <span className="pslc-row-number">{index + 1}</span>
                                        <select className="form-control" required value={mapping.product_id} onChange={(event) => updateProduct(index, 'product_id', event.target.value)}><option value="">--Sản phẩm--</option>{products.map((product) => <option key={product.id} value={product.id}>{product.name}{product.sku ? ` (${product.sku})` : ''} — {formatCurrency(product.unit_price ?? 0)}</option>)}</select>
                                        <select className="form-control" value={mapping.source_key} onChange={(event) => updateProduct(index, 'source_key', event.target.value)}><option value="">Tất cả nguồn</option>{form.data.sources.map((source) => <option key={source.client_key} value={source.client_key}>{source.name}</option>)}</select>
                                        <select className="form-control" value={mapping.item_type} onChange={(event) => updateProduct(index, 'item_type', event.target.value)}><option value="product">Sản phẩm</option><option value="combo">Combo</option><option value="upsell">Upsell</option><option value="gift">Quà tặng</option></select>
                                        <input className="form-control" placeholder="Tên field (vd: combo)" value={mapping.external_field ?? ''} onChange={(event) => updateProduct(index, 'external_field', event.target.value)} />
                                        <input className="form-control" placeholder="Giá trị field" value={mapping.external_value ?? ''} onChange={(event) => updateProduct(index, 'external_value', event.target.value)} />
                                        <input className="form-control" type="number" min="1" title="Số lượng" value={mapping.quantity} onChange={(event) => updateProduct(index, 'quantity', Number(event.target.value))} />
                                        <div className="pslc-money-input compact"><input className="form-control" inputMode="numeric" placeholder="Giá override" value={formatMoneyInput(mapping.unit_price_override)} onChange={(event) => updateProduct(index, 'unit_price_override', event.target.value === '' ? '' : parseMoneyInput(event.target.value))} /><span>₫</span></div>
                                        <label className="pslc-default-check"><input type="checkbox" checked={Boolean(mapping.is_default)} onChange={(event) => updateProduct(index, 'is_default', event.target.checked)} /> Mặc định</label>
                                        <button type="button" className="pslc-remove" disabled={form.data.products.length === 1} onClick={() => removeProduct(index)}><i className="fa fa-trash" /></button>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="pslc-form-section">
                            <h4>ƯU TIÊN SALE</h4>
                            <MultiSalePicker sales={sales} teams={saleTeams} selected={form.data.sale_user_ids} onChange={(ids) => form.setData('sale_user_ids', ids)} />
                        </section>

                        {Object.keys(form.errors).length > 0 && <div className="alert alert-danger pslc-errors">{Object.entries(form.errors).map(([key, message]) => <div key={key}><strong>{key}:</strong> {message}</div>)}</div>}
                    </div>
                    <footer className="pslc-dialog-footer"><button type="button" className="btn btn-default" onClick={() => setOpen(false)}>Đóng</button><button className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Lưu</button></footer>
                </form>
            </PageDialog>
        </AppLayout>
    );
}
