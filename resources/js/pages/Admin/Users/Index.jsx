import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatCurrency } from '@/lib/format';


function visitPage(url) {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true });
}

function CircleStatus({ active, title, onClick, disabled = false }) {
    const content = active ? <i className="fa fa-check-circle-o" /> : <i className="fa fa-circle-o" />;
    if (!onClick) return <span className={`ps-circle-status ${active ? 'is-on' : 'is-off'}`} title={title}>{content}</span>;
    return (
        <button
            type="button"
            className={`ps-circle-status ps-circle-button ${active ? 'is-on' : 'is-off'}`}
            title={title}
            disabled={disabled}
            onClick={onClick}
        >
            {content}
        </button>
    );
}

function PasswordModal({ user, onClose }) {
    const form = useForm({ password: '', password_confirmation: '' });
    if (!user) return null;

    const submit = (event) => {
        event.preventDefault();
        form.patch(`/admin/users/${user.id}/password`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="ps-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div className="ps-source-modal" role="dialog" aria-modal="true">
                <div className="ps-source-modal-header">
                    <strong>THAY ĐỔI MẬT KHẨU</strong>
                    <button type="button" onClick={onClose}><i className="fa fa-times" /></button>
                </div>
                <form className="ps-source-modal-body ps-taxonomy-form" onSubmit={submit}>
                    <div className="alert alert-info">Tài khoản: <strong>{user.name}</strong> ({user.email})</div>
                    <label>Mật khẩu mới
                        <input className="form-control" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required />
                    </label>
                    <label>Nhập lại mật khẩu
                        <input className="form-control" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required />
                    </label>
                    {Object.keys(form.errors).length > 0 && <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div>}
                    <div className="text-right">
                        <button type="button" className="btn btn-default" onClick={onClose}>Hủy</button>{' '}
                        <button className="btn btn-primary" disabled={form.processing}><i className="fa fa-save" /> Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function UsersIndex({ users, filters = {}, roles = [], accountCount = 0, canCreate = true }) {
    const [passwordUser, setPasswordUser] = useState(null);
    const [form, setForm] = useState({
        search: filters.search ?? '',
        role: filters.role ?? '',
        leader: filters.leader ?? '',
        receive_data: filters.receive_data ?? '',
        locked: filters.locked ?? '',
    });
    const rows = users?.data ?? [];
    const links = users?.links ?? [];

    const submit = (event) => {
        event?.preventDefault();
        const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''));
        router.get('/admin/users', query, { preserveState: true, replace: true });
    };

    const exportCsv = () => {
        const headers = ['ID', 'Họ tên', 'Chức vụ', 'Mã nhân viên', 'Lương cứng', 'Số điện thoại', 'Email', 'Trưởng nhóm', 'Nhận dữ liệu', 'Ca làm việc', 'Đang sử dụng', 'Ngày cập nhật'];
        const body = rows.map((row) => [
            row.id,
            row.name,
            row.role_label,
            row.employee_code,
            row.base_salary,
            row.phone ?? '',
            row.email,
            row.is_team_leader ? 'Có' : 'Không',
            row.receive_data ? 'Có' : 'Không',
            row.work_shift ?? '',
            row.is_locked ? 'Đã khóa' : 'Đang sử dụng',
            `${row.updated_by ?? ''} ${row.updated_at ?? ''}`.trim(),
        ]);
        const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const csv = '\ufeff' + [headers, ...body].map((line) => line.map(quote).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `danh-sach-nhan-vien-${new Date().toISOString().slice(0, 10)}.csv`;
        anchor.click();
        URL.revokeObjectURL(url);
    };

    const canBulk = useMemo(() => rows.some((row) => row.can_manage), [rows]);

    return (
        <AppLayout>
            <Head title="Danh sách nhân viên" />
            <section className="ps-adminlte-page ps-users-page" data-page-code="1.2.1">
                <form onSubmit={submit}>
                    <div className="m-header-wrap">
                        <div className="m-header ps-header-grid">
                            <div className="ps-title">
                                Danh sách nhân viên | <span className="ps-orange">Số TK: {accountCount}</span>
                            </div>
                            <div className="ps-header-search">
                                <input
                                    type="text"
                                    className="form-control"
                                    value={form.search}
                                    onChange={(event) => setForm((old) => ({ ...old, search: event.target.value }))}
                                />
                                <button className="btn btn-sm btn-primary" type="submit">
                                    <i className="fa fa-search" /> Tìm kiếm
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="box-body ps-filter-row">
                        <select className="form-control" value={form.role} onChange={(event) => setForm((old) => ({ ...old, role: event.target.value }))}>
                            <option value="">--Chức vụ--</option>
                            {roles.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
                        </select>
                        <select className="form-control" value={form.leader} onChange={(event) => setForm((old) => ({ ...old, leader: event.target.value }))}>
                            <option value="">--Chọn trưởng nhóm--</option>
                            <option value="1">Trưởng nhóm</option>
                            <option value="0">Thành viên</option>
                        </select>
                        <select className="form-control" value={form.receive_data} onChange={(event) => setForm((old) => ({ ...old, receive_data: event.target.value }))}>
                            <option value="">--Chọn TT nhận dữ liệu--</option>
                            <option value="1">Có nhận dữ liệu</option>
                            <option value="0">Không nhận dữ liệu</option>
                        </select>
                        <select className="form-control" value={form.locked} onChange={(event) => setForm((old) => ({ ...old, locked: event.target.value }))}>
                            <option value="">--Chọn TT sử dụng--</option>
                            <option value="0">Đang sử dụng</option>
                            <option value="1">Đã khóa</option>
                        </select>
                    </div>
                </form>

                <div className="box-body ps-toolbar">
                    {canCreate && (
                        <Link className="btn btn-sm btn-primary" href="/admin/users/create">
                            <i className="fa fa-plus" /> Thêm tài khoản
                        </Link>
                    )}
                    <Link className="btn btn-sm btn-default" href="/admin/users/create?mode=bulk">
                        <i className="fa fa-gears" /> Thêm nhiều tài khoản
                    </Link>
                    <button type="button" className="btn btn-sm btn-default" disabled={!canBulk} title="Cập nhật tại màn hình sửa tài khoản">
                        <i className="fa fa-gears" /> Cập nhật nhận dữ liệu
                    </button>
                    <button type="button" className="btn btn-sm btn-default" onClick={exportCsv}>
                        <i className="fa fa-file-excel-o" /> Xuất Excel
                    </button>
                </div>

                <div className="ps-table-scroll">
                    <table className="table table-bordered ps-source-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>#</th>
                                <th>Họ tên</th>
                                <th>Chức vụ</th>
                                <th>Mã nhân viên</th>
                                <th>Lương cứng</th>
                                <th>Số điện thoại</th>
                                <th>Email</th>
                                <th>Trưởng nhóm</th>
                                <th>Nhận dữ liệu</th>
                                <th>Ca làm việc</th>
                                <th>Đang sử dụng</th>
                                <th>Ngày cập nhật</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row.id} className={row.is_locked ? 'disableRow' : ''}>
                                    <td className="text-center">{(users.from ?? 1) + index}</td>
                                    <td className="text-center">{row.id}</td>
                                    <td className="text-center ps-user-name">{row.name} <span>({row.email?.split('@')[0]})</span></td>
                                    <td className="text-center">{row.role_label}</td>
                                    <td className="text-center">{row.employee_code}</td>
                                    <td className="text-right">{row.base_salary ? formatCurrency(row.base_salary) : ''}</td>
                                    <td className="text-center">{row.phone ?? ''}</td>
                                    <td className="text-center">{row.email}</td>
                                    <td className="text-center">{row.is_team_leader ? (row.team_name || 'Trưởng nhóm') : ''}</td>
                                    <td className="text-center"><CircleStatus active={row.receive_data} disabled={!row.can_manage} title={row.receive_data ? 'Tắt nhận dữ liệu' : 'Bật nhận dữ liệu'} onClick={row.can_manage ? () => router.patch(`/admin/users/${row.id}/operational-status`, { receive_data: !row.receive_data }, { preserveScroll: true }) : undefined} /></td>
                                    <td className="text-center">{row.work_shift ?? ''}</td>
                                    <td className="text-center"><CircleStatus active={!row.is_locked} disabled={!row.can_manage} title={row.is_locked ? 'Mở khóa tài khoản' : 'Khóa tài khoản'} onClick={row.can_manage ? () => router.patch(`/admin/users/${row.id}/operational-status`, { is_locked: !row.is_locked }, { preserveScroll: true }) : undefined} /></td>
                                    <td className="text-center ps-update-cell">
                                        {row.updated_by && <strong>{row.updated_by}</strong>}
                                        <span>{row.updated_at}</span>
                                    </td>
                                    <td className="text-center ps-row-actions">
                                        {row.can_manage ? (
                                            <>
                                                <Link href={`/admin/users/${row.id}/edit`} title="Cập nhật"><i className="fa fa-edit" /></Link>
                                                <button type="button" title="Thay đổi mật khẩu" onClick={() => setPasswordUser(row)}><i className="fa fa-retweet" /></button>
                                                <button type="button" title="Xóa" onClick={() => window.confirm(`Xóa tài khoản ${row.name}?`) && router.delete(`/admin/users/${row.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button>
                                            </>
                                        ) : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan="14" className="ps-empty">Không có tài khoản phù hợp.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="ps-pagination-bar">
                    <span>{users.from ?? 0} - {users.to ?? 0} / {users.total ?? 0}</span>
                    <ul className="pagination pagination-sm">
                        {links.map((link, index) => (
                            <li key={`${link.label}-${index}`} className={`${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}>
                                <button type="button" disabled={!link.url} onClick={() => visitPage(link.url)} dangerouslySetInnerHTML={{ __html: link.label }} />
                            </li>
                        ))}
                    </ul>
                </div>
            </section>
            <PasswordModal user={passwordUser} onClose={() => setPasswordUser(null)} />
        </AppLayout>
    );
}
