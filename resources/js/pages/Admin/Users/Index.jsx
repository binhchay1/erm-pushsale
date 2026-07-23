import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import { formatCurrency } from '@/lib/format';
import { PushsalePageFrame } from '@/pages/Pushsale/components/PushsalePageFrame';

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
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

function roleLabel(roles, value) {
    return roles.find((role) => String(role.value) === String(value))?.label ?? value ?? '';
}

function optionName(options, id) {
    return options.find((item) => String(item.id) === String(id))?.name ?? '';
}

function userToForm(user = null) {
    return {
        role: user?.role ?? '',
        email_local: user?.email_local ?? user?.email?.split('@')?.[0] ?? '',
        password: '',
        password_confirmation: '',
        phone: user?.phone ?? '',
        name: user?.name ?? '',
        employee_code: user?.employee_code ?? '',
        base_salary: user?.base_salary ?? '',
        team_id: user?.team_id ?? '',
        manager_user_id: user?.manager_user_id ?? '',
        work_shift_id: user?.work_shift_id ?? '',
        is_team_leader: Boolean(user?.is_team_leader ?? false),
        receive_data: Boolean(user?.receive_data ?? true),
        is_locked: Boolean(user?.is_locked ?? false),
    };
}

function PasswordDialog({ user, onClose }) {
    const form = useForm({ password: '', password_confirmation: '' });
    if (!user) return null;

    const submit = (event) => {
        event.preventDefault();
        form.patch(`/admin/users/${user.id}/password`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <PushsaleDialog open={Boolean(user)} onOpenChange={(nextOpen) => !nextOpen && onClose()} title="THAY ĐỔI MẬT KHẨU" width="600px" bodyClassName="ps-source-dialog-body ps-taxonomy-form">
            <form onSubmit={submit}>
                <div className="alert alert-info">Tài khoản: <strong>{user.name}</strong> ({user.email})</div>
                <label>Mật khẩu mới
                    <input className="form-control" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required />
                </label>
                <label>Nhập lại mật khẩu
                    <input className="form-control" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required />
                </label>
                {Object.keys(form.errors).length > 0 && <div className="alert alert-danger">{Object.values(form.errors).join(' · ')}</div>}
                <div className="text-right">
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Hủy</button>{' '}
                    <button className="btn btn-primary btn-sm" disabled={form.processing}><i className="fa fa-save" /> Lưu</button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

function AccountDialog({ mode = 'create', user = null, onClose, roles = [], workShifts = [], teams = [], managers = [], emailIdentity = {} }) {
    const form = useForm(userToForm(mode === 'update' ? user : null));
    if (mode === 'update' && !user) return null;
    if (!['create', 'update'].includes(mode)) return null;

    const isUpdate = mode === 'update';
    const title = isUpdate ? 'CẬP NHẬT TÀI KHOẢN' : 'THÊM TÀI KHOẢN';
    const actionText = isUpdate ? 'Cập nhật' : 'Thêm mới';
    const suffix = emailIdentity?.suffix ?? '@saleops.local';

    const submit = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        };
        if (isUpdate) form.patch(`/admin/users/${user.id}/quick-update`, options);
        else form.post('/admin/users', options);
    };

    return (
        <PushsaleDialog
            open
            onOpenChange={(nextOpen) => !nextOpen && onClose()}
            title={title}
            width="760px"
            bodyClassName="ps-employee-dialog-body ps-employee-account-dialog"
        >
            <form className="ps-employee-form" onSubmit={submit}>
                {Object.keys(form.errors).length > 0 && <div className="ps-employee-error">{Object.values(form.errors).join(' · ')}</div>}

                <div className="ps-employee-form-grid">
                    <label className="ps-employee-form-field">
                        <span>Chức vụ <b className="required">(*)</b></span>
                        <select className="form-control" value={form.data.role} onChange={(event) => form.setData('role', event.target.value)} required>
                            <option value="">--Chức vụ--</option>
                            {roles.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
                        </select>
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Họ và tên <b className="required">(*)</b></span>
                        <input className="form-control" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Tài khoản <b className="required">(*)</b></span>
                        <div className="ps-employee-email-input">
                            <input className="form-control" value={form.data.email_local} onChange={(event) => form.setData('email_local', event.target.value)} required />
                            <span>{suffix}</span>
                        </div>
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Mật khẩu{!isUpdate && <b className="required"> (*)</b>}</span>
                        <input className="form-control" type="password" value={form.data.password} onChange={(event) => {
                            const password = event.target.value;
                            form.setData({ ...form.data, password, password_confirmation: password });
                        }} required={!isUpdate} placeholder={isUpdate ? 'Để trống nếu không đổi' : ''} />
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Số ĐT</span>
                        <input className="form-control" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Mã nhân viên</span>
                        <input className="form-control" value={form.data.employee_code} onChange={(event) => form.setData('employee_code', event.target.value)} />
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Trưởng nhóm/QL trực tiếp</span>
                        <select className="form-control" value={form.data.manager_user_id} onChange={(event) => form.setData('manager_user_id', event.target.value)}>
                            <option value="">--Chọn quản lý--</option>
                            {managers.map((manager) => <option key={manager.id} value={manager.id}>{manager.name}</option>)}
                        </select>
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Đội nhóm</span>
                        <select className="form-control" value={form.data.team_id} onChange={(event) => form.setData('team_id', event.target.value)}>
                            <option value="">--Chọn đội nhóm--</option>
                            {teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}
                        </select>
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Lương cứng</span>
                        <input className="form-control" type="number" min="0" value={form.data.base_salary} onChange={(event) => form.setData('base_salary', event.target.value)} />
                    </label>

                    <label className="ps-employee-form-field">
                        <span>Ca làm việc</span>
                        <select className="form-control" value={form.data.work_shift_id} onChange={(event) => form.setData('work_shift_id', event.target.value)}>
                            <option value="">--Ca làm việc--</option>
                            {workShifts.map((shift) => <option key={shift.id} value={shift.id}>{shift.name}</option>)}
                        </select>
                    </label>
                </div>

                <div className="ps-employee-switch-row">
                    <label><input type="checkbox" checked={form.data.is_team_leader} onChange={(event) => form.setData('is_team_leader', event.target.checked)} /> Trưởng nhóm</label>
                    <label><input type="checkbox" checked={form.data.receive_data} onChange={(event) => form.setData('receive_data', event.target.checked)} /> Nhận dữ liệu</label>
                    <label><input type="checkbox" checked={!form.data.is_locked} onChange={(event) => form.setData('is_locked', !event.target.checked)} /> Đang sử dụng</label>
                </div>

                <div className="ps-employee-dialog-note">
                    {isUpdate
                        ? 'Cập nhật chức vụ/đội nhóm/nhận dữ liệu sẽ ảnh hưởng trực tiếp đến phân bổ data, báo cáo và quyền tác nghiệp.'
                        : 'Tài khoản mới sẽ được gắn vào đơn vị hiện tại và dùng ngay trong phân bổ data, báo cáo, kho/sale/marketing theo chức vụ.'}
                </div>

                <div className="ps-employee-action-row">
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button className="btn btn-primary btn-sm" disabled={form.processing}><i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {actionText}</button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

function BulkAccountDialog({ open, onClose, roles = [] }) {
    const form = useForm({
        template: '',
        quantity: '',
        start: '',
        role: '',
        accounts: '',
        password: '',
        password_confirmation: '',
        receive_data: false,
    });

    if (!open) return null;

    const generateAccounts = () => {
        const qty = Math.max(0, Number.parseInt(form.data.quantity || '0', 10));
        const start = Number.parseInt(form.data.start || '1', 10);
        const template = form.data.template || 'user{n}';
        if (!qty) return;
        const lines = Array.from({ length: qty }, (_, index) => {
            const n = start + index;
            return template.includes('{n}') ? template.replaceAll('{n}', String(n)) : `${template}${n}`;
        });
        form.setData('accounts', lines.join('\n'));
    };

    const submit = (event) => {
        event.preventDefault();
        form.post('/admin/users/bulk', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <PushsaleDialog open={open} onOpenChange={(nextOpen) => !nextOpen && onClose()} title="THÊM NHIỀU TÀI KHOẢN" width="900px" bodyClassName="ps-employee-dialog-body ps-employee-bulk-dialog">
            <form className="ps-employee-form" onSubmit={submit}>
                {Object.keys(form.errors).length > 0 && <div className="ps-employee-error">{Object.values(form.errors).join(' · ')}</div>}
                <div className="ps-employee-form-row">
                    <label>Hỗ trợ tạo nhanh:</label>
                    <div className="ps-employee-support">
                        <input className="form-control" placeholder="Mẫu, ví dụ sale{n}" value={form.data.template} onChange={(event) => form.setData('template', event.target.value)} />
                        <input className="form-control" placeholder="Số lượng" type="number" min="1" value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} />
                        <input className="form-control" placeholder="Bắt đầu" type="number" min="1" value={form.data.start} onChange={(event) => form.setData('start', event.target.value)} />
                        <button type="button" className="btn btn-default btn-sm" onClick={generateAccounts}>Tạo TK</button>
                    </div>
                </div>
                <div className="ps-employee-form-row">
                    <label>Chức vụ <span className="required">(*)</span>:</label>
                    <select className="form-control" value={form.data.role} onChange={(event) => form.setData('role', event.target.value)} required>
                        <option value="">--Chức vụ--</option>
                        {roles.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
                    </select>
                </div>
                <div className="ps-employee-form-row">
                    <label>Tài khoản <span className="required">(*)</span>:<br /><span className="small-tip">Mỗi dòng một tài khoản, không nhập đuôi email</span></label>
                    <textarea
                                className="form-control"
                                rows="10"
                                value={form.data.accounts}
                                onChange={(event) => form.setData('accounts', event.target.value)}
                                required
                                placeholder={`Mỗi dòng một tài khoản. Ví dụ:\nsale01\nsale02\nmarketing01`}
                            />
                </div>
                <div className="ps-employee-form-row">
                    <label>Mật khẩu:</label>
                    <input className="form-control" type="password" value={form.data.password} onChange={(event) => {
                        const password = event.target.value;
                        form.setData({ ...form.data, password, password_confirmation: password });
                    }} required />
                </div>
                <div className="ps-employee-form-row">
                    <label></label>
                    <label className="ps-employee-check"><input type="checkbox" checked={form.data.receive_data} onChange={(event) => form.setData('receive_data', event.target.checked)} /> Nhận dữ liệu ngay sau khi tạo</label>
                </div>
                <div className="ps-employee-dialog-note">Nhập mỗi dòng một mã tài khoản, ví dụ sale01, sale02, mkt01. Không nhập khoảng trắng hoặc đuôi email; hệ thống tự sinh email theo đơn vị hiện tại, tạo hồ sơ vận hành và đưa tài khoản vào luồng phân bổ data.</div>
                <div className="ps-employee-action-row">
                    <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                    <button className="btn btn-primary btn-sm" disabled={form.processing}><i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> Tạo tài khoản</button>
                </div>
            </form>
        </PushsaleDialog>
    );
}

export default function UsersIndex({ users, filters = {}, roles = [], workShifts = [], teams = [], managers = [], emailIdentity = {}, accountCount = 0, canCreate = true }) {
    const [passwordUser, setPasswordUser] = useState(null);
    const [accountDialog, setAccountDialog] = useState({ mode: null, user: null });
    const [bulkDialogOpen, setBulkDialogOpen] = useState(false);
    const [form, setForm] = useState({
        search: filters.search ?? '',
        role: filters.role ?? '',
        leader: filters.leader ?? '',
        receive_data: filters.receive_data ?? '',
        locked: filters.locked ?? '',
    });
    const rows = users?.data ?? [];
    const visibleAccountCount = accountCount || users?.total || rows.length;

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

    const title = <>Danh sách nhân viên <span className="ps-title-divider">|</span> <span className="ps-orange">Số TK: {visibleAccountCount}</span></>;
    const actions = (
        <form className="ps-header-search ps-users-header-search" onSubmit={submit}>
            <input
                type="text"
                className="form-control"
                value={form.search}
                onChange={(event) => setForm((old) => ({ ...old, search: event.target.value }))}
            />
            <button className="btn btn-sm btn-primary" type="submit"><i className="fa fa-search" /> Tìm kiếm</button>
        </form>
    );
    const filtersNode = (
        <form className="ps-filter-row" onSubmit={submit}>
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
        </form>
    );

    return (
        <AppLayout>
            <Head title="Danh sách nhân viên" />
            <PushsalePageFrame title={title} actions={actions} filters={filtersNode} className="ps-adminlte-page ps-users-page ps-standard-list-page" data-page-code="1.2.1">
                <div className="ps-toolbar">
                    {canCreate && (
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => setAccountDialog({ mode: 'create', user: null })}>
                            <i className="fa fa-plus" /> Thêm tài khoản
                        </button>
                    )}
                    <button type="button" className="btn btn-sm btn-default" onClick={() => setBulkDialogOpen(true)}>
                        <i className="fa fa-gears" /> Thêm nhiều tài khoản
                    </button>
                    <button type="button" className="btn btn-sm btn-default" disabled={!canBulk} title="Bật/tắt nhận dữ liệu trực tiếp tại từng dòng hoặc trong dialog cập nhật">
                        <i className="fa fa-gears" /> Cập nhật nhận dữ liệu
                    </button>
                    <button type="button" className="btn btn-sm btn-default" onClick={exportCsv}>
                        <i className="fa fa-file-excel-o" /> Xuất Excel
                    </button>
                </div>

                <div className="ps-table-scroll ps-users-table-wrap">
                    <table className="table table-bordered table-striped ps-source-table ps-users-table">
                        <thead>
                            <tr>
                                <th className="ps-col-stt">STT</th>
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
                                <th className="ps-action-col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row.id} className={row.is_locked ? 'disableRow' : ''}>
                                    <td className="text-center">{(users.from ?? 1) + index}</td>
                                    <td className="text-center ps-user-name">{row.name} <span>({row.email?.split('@')[0]})</span></td>
                                    <td className="text-center">{row.role_label || roleLabel(roles, row.role)}</td>
                                    <td className="text-center">{row.employee_code}</td>
                                    <td className="text-right">{row.base_salary ? formatCurrency(row.base_salary) : ''}</td>
                                    <td className="text-center">{row.phone ?? ''}</td>
                                    <td className="text-center">{row.email}</td>
                                    <td className="text-center">{row.is_team_leader ? (row.team_name || 'Trưởng nhóm') : (row.manager_name ?? '')}</td>
                                    <td className="text-center"><CircleStatus active={row.receive_data} disabled={!row.can_manage} title={row.receive_data ? 'Tắt nhận dữ liệu' : 'Bật nhận dữ liệu'} onClick={row.can_manage ? () => router.patch(`/admin/users/${row.id}/operational-status`, { receive_data: !row.receive_data }, { preserveScroll: true }) : undefined} /></td>
                                    <td className="text-center">{row.work_shift || optionName(workShifts, row.work_shift_id)}</td>
                                    <td className="text-center"><CircleStatus active={!row.is_locked} disabled={!row.can_manage} title={row.is_locked ? 'Mở khóa tài khoản' : 'Khóa tài khoản'} onClick={row.can_manage ? () => router.patch(`/admin/users/${row.id}/operational-status`, { is_locked: !row.is_locked }, { preserveScroll: true }) : undefined} /></td>
                                    <td className="text-center ps-update-cell">
                                        {row.updated_by && <strong>{row.updated_by}</strong>}
                                        <span>{row.updated_at}</span>
                                    </td>
                                    <td className="text-center ps-row-actions-cell">
                                        {row.can_manage ? (
                                            <div className="ps-row-actions">
                                                <button type="button" title="Cập nhật" onClick={() => setAccountDialog({ mode: 'update', user: row })}><i className="fa fa-edit" /></button>
                                                <button type="button" title="Thay đổi mật khẩu" onClick={() => setPasswordUser(row)}><i className="fa fa-retweet" /></button>
                                                <button type="button" title="Xóa" onClick={() => window.confirm(`Xóa tài khoản ${row.name}?`) && router.delete(`/admin/users/${row.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button>
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan="13" className="ps-empty">Không có tài khoản phù hợp.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination
                    meta={users}
                    routeUrl="/admin/users"
                    filters={currentFilters()}
                    itemLabel="tài khoản"
                />
            </PushsalePageFrame>
            {accountDialog.mode && (
                <AccountDialog
                    mode={accountDialog.mode}
                    user={accountDialog.user}
                    onClose={() => setAccountDialog({ mode: null, user: null })}
                    roles={roles}
                    workShifts={workShifts}
                    teams={teams}
                    managers={managers}
                    emailIdentity={emailIdentity}
                />
            )}
            <BulkAccountDialog open={bulkDialogOpen} onClose={() => setBulkDialogOpen(false)} roles={roles} />
            <PasswordDialog user={passwordUser} onClose={() => setPasswordUser(null)} />
        </AppLayout>
    );
}
