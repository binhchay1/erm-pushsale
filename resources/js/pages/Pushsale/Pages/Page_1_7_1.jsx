import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN');

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}

function todayIso() {
    const date = new Date();
    return date.toISOString().slice(0, 10);
}

function daysAgoIso(days) {
    const date = new Date();
    date.setDate(date.getDate() - days);
    return date.toISOString().slice(0, 10);
}

function valueFromSearch(key, fallback = '') {
    if (typeof window === 'undefined') return fallback;
    return new URLSearchParams(window.location.search).get(key) ?? fallback;
}

function optionLabel(option) {
    return option?.label ?? option?.name ?? option?.email ?? option?.id ?? '';
}

function StatusBadge({ value }) {
    const normalized = String(value ?? '').toLocaleLowerCase('vi');
    const tone = normalized.includes('thành công') && !normalized.includes('không')
        ? 'success'
        : normalized.includes('đăng xuất')
            ? 'default'
            : 'danger';

    return <span className={`ps-login-status ps-login-status-${tone}`}>{value || '—'}</span>;
}

function Pagination({ pagination, routeUrl, perPage, onPerPageChange }) {
    const current = Math.max(1, Number(pagination?.current_page || 1));
    const last = Math.max(1, Number(pagination?.last_page || 1));
    const total = Number(pagination?.total || 0);

    const visit = (page) => {
        const params = new URLSearchParams(window.location.search);
        params.set('page', String(Math.max(1, Math.min(last, page))));
        params.set('per_page', String(perPage || 20));
        router.get(routeUrl, Object.fromEntries(params.entries()), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <div className="ps-login-pagination">
            <div className="ps-login-note">* Hệ thống chỉ lưu lịch sử 60 ngày gần nhất</div>
            <div className="ps-login-pager-controls">
                <label className="ps-login-page-size">
                    <span>Hiển thị</span>
                    <select className="form-control input-sm" value={perPage} onChange={(event) => onPerPageChange(event.target.value)}>
                        {[20, 50, 100, 200, 500].map((size) => <option key={size} value={size}>{size}</option>)}
                    </select>
                    <span>dòng</span>
                </label>
                <div className="btn-group ps-login-short-pager">
                    <button type="button" className="btn btn-default btn-sm" disabled>
                        {numberFormatter.format(Number(pagination?.from || 0))} - {numberFormatter.format(Number(pagination?.to || 0))} / {numberFormatter.format(total)}
                    </button>
                    <button type="button" className="btn btn-default btn-sm" disabled={current <= 1} onClick={() => visit(current - 1)} title="Trang trước">
                        <i className="fa fa-caret-left" />
                    </button>
                    <button type="button" className="btn btn-default btn-sm" disabled={current >= last} onClick={() => visit(current + 1)} title="Trang sau">
                        <i className="fa fa-caret-right" />
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function LoginHistoryPage({ schema, rows = [], pagination = {}, filterOptions = {}, routeUrl, pageRuntimeError = null }) {
    const [filters, setFilters] = useState(() => ({
        search: valueFromSearch('search'),
        company_id: valueFromSearch('company_id', '-1'),
        role: valueFromSearch('role', '-1'),
        user_id: valueFromSearch('user_id', '-1'),
        login_status: valueFromSearch('login_status', '-1'),
        sort: valueFromSearch('sort', 'created_desc'),
        date_from: valueFromSearch('date_from', daysAgoIso(60)),
        date_to: valueFromSearch('date_to', todayIso()),
        per_page: valueFromSearch('per_page', pagination?.per_page ?? 20),
    }));

    const companies = filterOptions.companies ?? [];
    const roles = filterOptions.roles ?? [];
    const users = filterOptions.loginUsers ?? filterOptions.users ?? [];
    const statuses = filterOptions.loginStatuses ?? [
        { id: 'success', label: 'Thành công' },
        { id: 'failed', label: 'Không thành công' },
        { id: 'logout', label: 'Đăng xuất' },
    ];
    const sorts = filterOptions.loginSorts ?? [
        { id: 'created_desc', label: 'Mới nhất' },
        { id: 'ip', label: 'Sắp xếp theo IP' },
        { id: 'user', label: 'Sắp xếp theo tài khoản' },
    ];

    const filteredUsers = useMemo(() => {
        if (!filters.role || filters.role === '-1') return users;
        return users.filter((user) => String(user.role ?? '') === String(filters.role));
    }, [filters.role, users]);

    const setField = (key, value) => {
        setFilters((current) => ({
            ...current,
            [key]: value,
            ...(key === 'role' ? { user_id: '-1' } : {}),
        }));
    };

    const submit = (event) => {
        event?.preventDefault();
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '' || value === '-1') return;
            params.set(key, String(value));
        });
        params.delete('page');
        router.get(routeUrl, Object.fromEntries(params.entries()), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
        });
    };

    const reset = () => {
        router.get(routeUrl, {}, { preserveState: false, preserveScroll: false, replace: true });
    };

    const changePerPage = (value) => {
        const next = { ...filters, per_page: value };
        setFilters(next);
        const params = new URLSearchParams(window.location.search);
        params.set('per_page', String(value));
        params.delete('page');
        router.get(routeUrl, Object.fromEntries(params.entries()), {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Lịch sử đăng nhập'} />
            <div className="ps-login-history-page pushsale-page" data-page-code="1.7.1">
                {pageRuntimeError && <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>}

                <form className="ps-login-toolbar" onSubmit={submit}>
                    <div className="ps-login-title">Lịch sử đăng nhập</div>
                    <div className="ps-login-search-box">
                        <input
                            className="form-control input-sm"
                            value={filters.search}
                            placeholder="IPAddress/Mã truy cập/Tài khoản"
                            onChange={(event) => setField('search', event.target.value)}
                        />
                        <button type="submit" className="btn btn-sm btn-primary">
                            <i className="fa fa-search" /> Tìm kiếm
                        </button>
                        <button type="button" className="btn btn-sm btn-default" title="Tải lại" onClick={reset}>
                            <i className="fa fa-refresh" />
                        </button>
                    </div>
                </form>

                <form className="ps-login-filter-grid" onSubmit={submit}>
                    <select className="form-control input-sm" value={filters.company_id} onChange={(event) => setField('company_id', event.target.value)}>
                        <option value="-1">-- Chọn đơn vị --</option>
                        {companies.map((company) => <option key={company.id} value={company.id}>{optionLabel(company)}</option>)}
                    </select>
                    <select className="form-control input-sm" value={filters.role} onChange={(event) => setField('role', event.target.value)}>
                        <option value="-1">-- Quyền --</option>
                        {roles.map((role) => <option key={role.id} value={role.id}>{optionLabel(role)}</option>)}
                    </select>
                    <select className="form-control input-sm" value={filters.user_id} onChange={(event) => setField('user_id', event.target.value)}>
                        <option value="-1">-- User --</option>
                        {filteredUsers.map((user) => <option key={user.id} value={user.id}>{optionLabel(user)}</option>)}
                    </select>
                    <div className="ps-login-date-range">
                        <input className="form-control input-sm" type="date" value={filters.date_from} onChange={(event) => setField('date_from', event.target.value)} />
                        <span>-</span>
                        <input className="form-control input-sm" type="date" value={filters.date_to} onChange={(event) => setField('date_to', event.target.value)} />
                    </div>
                    <select className="form-control input-sm" value={filters.login_status} onChange={(event) => setField('login_status', event.target.value)}>
                        <option value="-1">-- Trạng thái đăng nhập --</option>
                        {statuses.map((status) => <option key={status.id} value={status.id}>{optionLabel(status)}</option>)}
                    </select>
                    <select className="form-control input-sm" value={filters.sort} onChange={(event) => setField('sort', event.target.value)}>
                        {sorts.map((sort) => <option key={sort.id} value={sort.id}>{optionLabel(sort)}</option>)}
                    </select>
                </form>

                <div className="ps-login-table-wrap">
                    <table className="table table-bordered table-striped table-condensed ps-login-table">
                        <thead>
                            <tr>
                                <th>IPAddress</th>
                                <th>Đơn vị</th>
                                <th>Tài khoản</th>
                                <th>Mã truy cập</th>
                                <th>Mã browser</th>
                                <th>Ngày thực hiện</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row.id ?? `${row.ip_address}-${row.created_at}-${index}`}>
                                    <td>{row.ip_address || '—'}</td>
                                    <td>{row.company || '—'}</td>
                                    <td>{row.account || '—'}</td>
                                    <td className="ps-login-access-code">{row.access_code || '—'}</td>
                                    <td className="ps-login-browser" title={row.browser || ''}>{row.browser || '—'}</td>
                                    <td>{formatDateTime(row.created_at)}</td>
                                    <td><StatusBadge value={row.status} /></td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan="7" className="ps-login-empty">Chưa có dữ liệu đăng nhập phù hợp với bộ lọc.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination pagination={pagination} routeUrl={routeUrl} perPage={filters.per_page} onPerPageChange={changePerPage} />
            </div>
        </AppLayout>
    );
}
