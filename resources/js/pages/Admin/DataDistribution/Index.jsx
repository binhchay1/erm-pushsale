import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { PageHeader } from '@/components/layout/PageHeader';
import AppLayout from '@/layouts/AppLayout';

function currentPath() {
    if (typeof window === 'undefined') return '/admin/leads';
    return window.location.pathname;
}

function submitUrl() {
    const path = currentPath();
    if (path.startsWith('/allocator/')) return '/allocator/leads/distribute';
    if (path.startsWith('/marketing/')) return '/marketing/leads/distribute';
    return '/admin/leads/distribute';
}

function listUrl() {
    const path = currentPath();
    if (path.startsWith('/allocator/')) return '/allocator/workspace';
    if (path.startsWith('/marketing/')) return '/marketing/leads';
    return '/admin/leads';
}

function normalizeDate(value) {
    return value ? String(value).slice(0, 10) : '';
}

function formatDateRange(filters) {
    const from = normalizeDate(filters.date_from);
    const to = normalizeDate(filters.date_to);
    return `${from} 00:00 - ${to} 23:59`;
}

function CheckOption({ checked, onChange, children }) {
    return (
        <label className="psdd-check">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            <span>{children}</span>
        </label>
    );
}

export default function DataDistributionIndex({ filters = {}, products = [], sales = [], teams = [], operationOptions = [], stats = {}, lastBatch = null }) {
    const [localFilters, setLocalFilters] = useState({
        returning_scope: filters.returning_scope ?? '',
        data_scope: filters.data_scope ?? 'all',
        operation_stage: filters.operation_stage ?? '',
        team_id: filters.team_id ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
    });

    const [selectedProducts, setSelectedProducts] = useState(() => new Map());
    const [selectedSales, setSelectedSales] = useState(() => new Set(sales.filter((sale) => sale.can_receive).map((sale) => String(sale.id))));
    const [distributing, setDistributing] = useState(false);

    const form = useForm({
        filters: localFilters,
        product_allocations: [],
        sale_user_ids: [],
        operation_policy: 'keep',
        delete_operation_history: false,
        delete_internal_messages: false,
        hide_sales_not_receiving: true,
        skip_sales_not_receiving: true,
        hide_locked_sales: true,
        skip_locked_sales: true,
    });

    useEffect(() => {
        form.setData('filters', localFilters);
    }, [localFilters]);

    const visibleSales = useMemo(() => sales.filter((sale) => {
        if (form.data.hide_sales_not_receiving && !sale.receive_data) return false;
        if (form.data.hide_locked_sales && sale.is_locked) return false;
        return true;
    }), [sales, form.data.hide_sales_not_receiving, form.data.hide_locked_sales]);

    const selectedTotal = useMemo(() => [...selectedProducts.values()].reduce((sum, item) => sum + Number(item || 0), 0), [selectedProducts]);

    const setProductQuantity = (product, quantity) => {
        const next = new Map(selectedProducts);
        const value = Math.max(0, Math.min(Number(product.contact_count || 0), Number(quantity || 0)));
        if (value > 0) next.set(String(product.id), value);
        else next.delete(String(product.id));
        setSelectedProducts(next);
    };

    const toggleSale = (id, checked) => {
        const next = new Set(selectedSales);
        checked ? next.add(String(id)) : next.delete(String(id));
        setSelectedSales(next);
    };

    const selectAllSales = () => setSelectedSales(new Set(visibleSales.map((sale) => String(sale.id))));

    const applyFilters = () => {
        const payload = Object.fromEntries(Object.entries(localFilters).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        router.get(listUrl(), payload, { preserveState: false, preserveScroll: true, replace: true });
    };

    const distribute = (event) => {
        event.preventDefault();
        const payload = {
            ...form.data,
            filters: localFilters,
            product_allocations: [...selectedProducts.entries()].map(([productId, quantity]) => ({ product_id: Number(productId), quantity: Number(quantity) })),
            sale_user_ids: [...selectedSales].map((id) => Number(id)),
        };

        if (payload.product_allocations.length === 0) {
            toast.error('Nhập số lượng data cần phân bổ cho ít nhất một sản phẩm.');
            return;
        }
        if (payload.sale_user_ids.length === 0) {
            toast.error('Chọn ít nhất một Sale nhận data.');
            return;
        }

        setDistributing(true);
        toast.loading('Đang phân bổ data…', { id: 'manual-data-distribution', duration: 9000 });
        router.post(submitUrl(), payload, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => {
                toast.success('Đã phân bổ data cho Sale.', { id: 'manual-data-distribution', duration: 6500 });
                setSelectedProducts(new Map());
            },
            onError: (errors) => {
                toast.error(Object.values(errors || {})[0] ?? 'Không thể phân bổ data.', { id: 'manual-data-distribution', duration: 8000 });
            },
            onFinish: () => setDistributing(false),
        });
    };

    return (
        <AppLayout activeMenuCode="1.5">
            <Head title="Phân bổ data" />
            <form className="psdd-page" onSubmit={distribute}>
                <PageHeader
                    title="Phân bổ data"
                    pageCode="1.5"
                    className="psdd-topbar"
                    filters={(
                        <>
                            <select value={localFilters.returning_scope} onChange={(event) => setLocalFilters({ ...localFilters, returning_scope: event.target.value })}>
                                <option value="">--Chọn khách cũ--</option>
                                <option value="new">Khách mới</option>
                                <option value="old">Khách cũ / trùng cần xử lý</option>
                            </select>
                            <select value={localFilters.data_scope} onChange={(event) => setLocalFilters({ ...localFilters, data_scope: event.target.value })}>
                                <option value="all">--Toàn bộ--</option>
                                <option value="landing">Landing</option>
                                <option value="pancake">Pancake</option>
                                <option value="manual">Nhập tay</option>
                            </select>
                            <select value={localFilters.operation_stage} onChange={(event) => setLocalFilters({ ...localFilters, operation_stage: event.target.value })}>
                                <option value="">--Chọn tác nghiệp--</option>
                                {operationOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                            <DateRangeFilter
                                boxed
                                className="psdd-date-range"
                                from={localFilters.date_from}
                                to={localFilters.date_to}
                                onChange={({ date_from, date_to }) => setLocalFilters({ ...localFilters, date_from, date_to })}
                            />
                        </>
                    )}
                    actions={<PushsaleSearchButton onClick={applyFilters} label="Tìm kiếm" />}
                />

                <div className="psdd-notice">
                    - Mỗi lần phân bổ tối đa {stats.max_batch_size ?? 5000} bản ghi<br />
                    - Khi chọn sản phẩm sẽ chỉ hiển thị sale được phân quyền đối với sản phẩm đó
                </div>

                <div className="psdd-columns">
                    <section className="psdd-product-panel">
                        <h2>DANH SÁCH DATA THEO SẢN PHẨM</h2>
                        <table className="psdd-table psdd-product-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 52 }}>#</th>
                                    <th>Sản phẩm</th>
                                    <th style={{ width: 150 }}>Số contact</th>
                                    <th style={{ width: 155 }}>SL Phân bổ</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.length === 0 && (
                                    <tr><td colSpan="4" className="psdd-empty">Không có data chờ phân bổ theo điều kiện lọc.</td></tr>
                                )}
                                {products.map((product, index) => (
                                    <tr key={product.id}>
                                        <td className="center">{index + 1}</td>
                                        <td>
                                            <strong>{product.name}</strong>
                                            {product.sku && <small>({product.sku})</small>}
                                            {product.type === 'combo' && <em>Combo</em>}
                                        </td>
                                        <td className="center">{product.contact_count}</td>
                                        <td>
                                            <input
                                                type="number"
                                                min="0"
                                                max={product.contact_count}
                                                value={selectedProducts.get(String(product.id)) ?? ''}
                                                onChange={(event) => setProductQuantity(product, event.target.value)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    <section className="psdd-sale-panel">
                        <h2>DANH SÁCH SALE ĐƯỢC PHÂN BỔ</h2>
                        <table className="psdd-table psdd-sale-table">
                            <thead>
                                <tr>
                                    <th>Sale</th>
                                    <th>Tài khoản</th>
                                    <th>Số contact</th>
                                    <th>Nhận data</th>
                                    <th>Đang sử dụng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><b>Nhóm</b></td>
                                    <td colSpan="4">
                                        <div className="psdd-chipbox" onClick={selectAllSales}>
                                            <span className="psdd-chip">× Tất cả sale</span>
                                            <select value={localFilters.team_id} onChange={(event) => setLocalFilters({ ...localFilters, team_id: event.target.value })}>
                                                <option value="">--Tất cả nhóm--</option>
                                                {teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                                {visibleSales.map((sale) => (
                                    <tr key={sale.id}>
                                        <td>
                                            <label className="psdd-sale-select">
                                                <input type="checkbox" checked={selectedSales.has(String(sale.id))} onChange={(event) => toggleSale(sale.id, event.target.checked)} />
                                                <span>{sale.name}</span>
                                            </label>
                                        </td>
                                        <td>{sale.email}<small>{sale.team}</small></td>
                                        <td className="center">{sale.contact_count}</td>
                                        <td className="center">{sale.receive_data ? 'Có' : 'Tắt'}</td>
                                        <td className="center">{sale.is_locked ? 'Khóa' : sale.active_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <select className="psdd-operation-policy" value={form.data.operation_policy} onChange={(event) => form.setData('operation_policy', event.target.value)}>
                            <option value="keep">--Giữ nguyên tác nghiệp--</option>
                            <option value="new_customer">Đưa về khách mới</option>
                            <option value="follow_up">Đưa về cần gọi lại</option>
                        </select>

                        <div className="psdd-options">
                            <CheckOption checked={form.data.delete_operation_history} onChange={(value) => form.setData('delete_operation_history', value)}>Xóa lịch sử tác nghiệp</CheckOption>
                            <CheckOption checked={form.data.delete_internal_messages} onChange={(value) => form.setData('delete_internal_messages', value)}>Xóa tin nhắn nội bộ</CheckOption>
                            <CheckOption checked={form.data.hide_sales_not_receiving} onChange={(value) => form.setData('hide_sales_not_receiving', value)}>Không hiển thị sale tắt nhận dữ liệu</CheckOption>
                            <CheckOption checked={form.data.skip_sales_not_receiving} onChange={(value) => form.setData('skip_sales_not_receiving', value)}>Không phân bổ cho sale tắt nhận data</CheckOption>
                            <CheckOption checked={form.data.hide_locked_sales} onChange={(value) => form.setData('hide_locked_sales', value)}>Không hiển thị sale khóa tài khoản</CheckOption>
                            <CheckOption checked={form.data.skip_locked_sales} onChange={(value) => form.setData('skip_locked_sales', value)}>Không phân bổ cho sale bị khóa tài khoản</CheckOption>
                        </div>

                        <div className="psdd-actions">
                            <span>Đang chọn <b>{selectedTotal}</b> contact / <b>{selectedSales.size}</b> sale</span>
                            <button type="button" onClick={distribute} disabled={distributing || selectedTotal <= 0 || selectedSales.size <= 0}>{distributing ? 'Đang phân bổ…' : 'Phân bổ data'}</button>
                        </div>
                    </section>
                </div>

                {lastBatch && (
                    <div className="psdd-last-batch">
                        Lần phân bổ gần nhất: #{lastBatch.id} — {lastBatch.allocated_contacts}/{lastBatch.total_contacts} contact — {lastBatch.status}
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
