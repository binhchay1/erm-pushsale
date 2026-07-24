import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';

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

function valueFromSearch(key, fallback = '') {
    if (typeof window === 'undefined') return fallback;
    return new URLSearchParams(window.location.search).get(key) ?? fallback;
}

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function optionLabel(option) {
    return option?.label ?? option?.name ?? option?.email ?? option?.id ?? '';
}

function StatusBadge({ value }) {
    const normalized = String(value ?? '').toLocaleLowerCase('vi');
    const approved = normalized.includes('đã phê duyệt');
    const pending = normalized.includes('chưa phê duyệt');

    return (
        <span className={`ps-login-status ${approved ? 'ps-login-status-success' : pending ? 'ps-login-status-warning' : 'ps-login-status-default'}`}>
            {value || '—'}
        </span>
    );
}

function AccessPagination({ pagination, routeUrl }) {
    return (
        <div className="ps-login-pagination ps-login-access-pagination">
            <div className="ps-login-note">* Dữ liệu lấy từ tài khoản thật trong hệ thống và trạng thái quyền đăng nhập hiện tại.</div>
            <PushsalePagination
                meta={pagination}
                routeUrl={routeUrl}
                filters={currentFilters()}
                itemLabel="tài khoản"
                perPageOptions={[20, 50, 100, 200, 500]}
            />
        </div>
    );
}

export default function LoginAccessPage({ schema, rows = [], pagination = {}, filterOptions = {}, routeUrl, pageRuntimeError = null }) {
    const [filters, setFilters] = useState(() => ({
        search: valueFromSearch('search'),
        company_id: valueFromSearch('company_id', '-1'),
        role: valueFromSearch('role', '-1'),
        user_id: valueFromSearch('user_id', '-1'),
        login_permission_status: valueFromSearch('login_permission_status', '-1'),
        sort: valueFromSearch('sort', '1'),
        per_page: valueFromSearch('per_page', pagination?.per_page ?? 20),
    }));

    const companies = filterOptions.companies ?? [];
    const roles = filterOptions.roles ?? [];
    const users = filterOptions.loginUsers ?? filterOptions.users ?? [];
    const statuses = [
        { id: '-1', label: '-- Trạng thái phê duyệt --' },
        { id: '1', label: 'Chưa phê duyệt' },
        { id: '2', label: 'Đã phê duyệt' },
    ];
    const sorts = [
        { id: '1', label: 'Ngày giảm dần' },
        { id: '2', label: 'Ngày tăng dần' },
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

    const toggleAccess = (row, allow) => {
        if (!row?._user_id) return;
        const endpoint = `${routeUrl}/users/${row._user_id}/${allow ? 'approve' : 'block'}`;
        router.patch(endpoint, {}, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        });
    };

    const searchActions = (
        <form className="ps-login-search-box ps-login-access-search" onSubmit={submit}>
            <input
                className="form-control input-sm"
                value={filters.search}
                placeholder="Tên tài khoản/Mã truy cập"
                onChange={(event) => setField('search', event.target.value)}
            />
            <button type="submit" className="btn btn-sm btn-primary">
                <i className="fa fa-search" /> Tìm kiếm
            </button>
            <button type="button" className="btn btn-sm btn-default" title="Tải lại" onClick={reset}>
                <i className="fa fa-refresh" />
            </button>
        </form>
    );

    const advancedFilters = (
        <form className="ps-login-filter-grid ps-login-access-filter-grid" onSubmit={submit}>
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
            <select className="form-control input-sm" value={filters.login_permission_status} onChange={(event) => setField('login_permission_status', event.target.value)}>
                {statuses.map((status) => <option key={status.id} value={status.id}>{status.label}</option>)}
            </select>
            <select className="form-control input-sm" value={filters.sort} onChange={(event) => setField('sort', event.target.value)}>
                {sorts.map((sort) => <option key={sort.id} value={sort.id}>{sort.label}</option>)}
            </select>
            <select className="form-control input-sm" value={filters.per_page} onChange={(event) => setField('per_page', event.target.value)}>
                {[20, 50, 100, 200, 500].map((size) => <option key={size} value={size}>{size}</option>)}
            </select>
        </form>
    );

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Quản lý cho phép tài khoản đăng nhập'} />
            <PushsalePageShell
                title="Quản lý cho phép tài khoản đăng nhập"
                actions={searchActions}
                advancedFilters={advancedFilters}
                notice={pageRuntimeError ? <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div> : null}
                className="ps-login-history-page ps-login-access-page pushsale-page"
                data-page-code="1.7.2"
            >
                <div className="ps-login-table-wrap ps-login-access-table-wrap">
                    <table className="table table-bordered table-striped table-condensed ps-login-table ps-login-access-table">
                        <thead>
                            <tr>
                                <th>Đơn vị</th>
                                <th>Tài khoản</th>
                                <th>Mã truy cập</th>
                                <th>Ngày đăng nhập</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => {
                                const approved = String(row.status ?? '').toLocaleLowerCase('vi').includes('đã phê duyệt');
                                return (
                                    <tr key={row._user_id ?? row.account ?? index}>
                                        <td>{row.company || '—'}</td>
                                        <td>{row.account || '—'}</td>
                                        <td className="ps-login-access-code">{row.access_code || '—'}</td>
                                        <td>{formatDateTime(row.login_at)}</td>
                                        <td className="text-center"><StatusBadge value={row.status} /></td>
                                        <td className="text-center">
                                            <div className="ps-login-access-actions">
                                                {row._edit_url ? (
                                                    <Link href={row._edit_url} className="btn btn-xs btn-default" title="Mở hồ sơ tài khoản">
                                                        <i className="fa fa-pencil" /> Sửa
                                                    </Link>
                                                ) : null}
                                                <button
                                                    type="button"
                                                    className={`btn btn-xs ${approved ? 'btn-danger' : 'btn-success'}`}
                                                    onClick={() => toggleAccess(row, !approved)}
                                                >
                                                    <i className={`fa ${approved ? 'fa-ban' : 'fa-check'}`} /> {approved ? 'Chặn' : 'Cho phép'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            }) : (
                                <tr>
                                    <td colSpan="6" className="ps-login-empty">Chưa có tài khoản phù hợp với bộ lọc.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <AccessPagination pagination={pagination} routeUrl={routeUrl} />
            </PushsalePageShell>
        </AppLayout>
    );
}
