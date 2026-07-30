import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import {
    AccountDialog,
    BulkAccountDialog,
    GoogleAuthDialog,
    PasswordDialog,
    ReceiveDataDialog,
} from '@/components/admin/users/UserDialogs';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency } from '@/lib/format';
import { PushsalePageFrame } from '@/components/pushsale/PushsalePageFrame';
import { useConfirm } from '@/hooks/use-confirm';

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function CircleStatus({ active, title, onClick, disabled = false }) {
    const checked = Boolean(active);

    if (!onClick) {
        return (
            <span
                className={`ps-round-tick ${checked ? 'is-on' : 'is-off'}`}
                title={title}
                aria-hidden="true"
            >
                <i className={`fa ${checked ? 'fa-check' : ''}`} />
            </span>
        );
    }

    return (
        <label
            className={`ps-round-tick-wrap ${disabled ? 'is-disabled' : ''}`}
            title={title}
        >
            <input
                type="checkbox"
                className="ps-round-tick-input"
                checked={checked}
                disabled={disabled}
                onChange={() => {
                    if (!disabled) onClick();
                }}
                aria-label={title}
            />
            <span className={`ps-round-tick ${checked ? 'is-on' : 'is-off'}`} aria-hidden="true">
                {checked ? <i className="fa fa-check" /> : null}
            </span>
        </label>
    );
}

function roleLabel(roles, value) {
    return roles.find((role) => String(role.value) === String(value))?.label ?? value ?? '';
}

function optionName(options, id) {
    return options.find((item) => String(item.id) === String(id))?.name ?? '';
}

export default function UsersIndex({
    users,
    filters = {},
    roles = [],
    workShifts = [],
    teams = [],
    managers = [],
    emailIdentity = {},
    accountCount = 0,
    canCreate = true,
}) {
    const { ask } = useConfirm();
    const [passwordUser, setPasswordUser] = useState(null);
    const [googleUser, setGoogleUser] = useState(null);
    const [accountDialog, setAccountDialog] = useState({ mode: null, user: null });
    const [bulkDialogOpen, setBulkDialogOpen] = useState(false);
    const [receiveDialogOpen, setReceiveDialogOpen] = useState(false);
    const [form, setForm] = useState({
        search: filters.search ?? '',
        role: filters.role ?? '',
        leader: filters.leader ?? '',
        receive_data: filters.receive_data ?? '',
        locked: filters.locked ?? '',
    });

    const rows = users?.data ?? [];
    const visibleAccountCount = accountCount || users?.total || rows.length;
    const manageableRows = useMemo(() => rows.filter((row) => row.can_manage), [rows]);

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

    const receiveDialogSeed = manageableRows.map((row) => row.id).join('\n');

    const title = (
        <>
            Danh sách nhân viên <span className="ps-title-divider">|</span>{' '}
            <span className="ps-orange">Số TK: {visibleAccountCount}</span>
        </>
    );

    const actions = (
        <div className="ps-header-search ps-users-header-search">
            <input
                type="text"
                className="form-control"
                value={form.search}
                onChange={(event) => setForm((old) => ({ ...old, search: event.target.value }))}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') submit(event);
                }}
            />
            <button className="btn btn-sm btn-primary" type="button" onClick={submit}>
                <i className="fa fa-search" /> Tìm kiếm
            </button>
        </div>
    );

    const advancedFilters = (
        <div className="ps-filter-row">
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
    );

    const toolbar = (
        <div className="ps-toolbar ps-users-toolbar">
            {canCreate && (
                <button type="button" className="btn btn-sm btn-primary mr15" onClick={() => setAccountDialog({ mode: 'create', user: null })}>
                    <i className="fa fa-plus" /> Thêm tài khoản
                </button>
            )}
            <button type="button" className="btn btn-sm btn-default mr15" onClick={() => setBulkDialogOpen(true)}>
                <i className="fa fa-gears" /> Thêm nhiều tài khoản
            </button>
            <button
                type="button"
                className="btn btn-sm btn-default mr15"
                onClick={() => setReceiveDialogOpen(true)}
                disabled={manageableRows.length === 0}
            >
                <i className="fa fa-gears" /> Cập nhật nhận dữ liệu
            </button>
            <button type="button" className="btn btn-sm btn-default mr15" onClick={exportCsv}>
                <i className="fa fa-file-excel-o" /> Xuất Excel
            </button>
        </div>
    );

    return (
        <AppLayout>
            <Head title="Danh sách nhân viên" />
            <PushsalePageFrame
                title={title}
                actions={actions}
                advancedFilters={advancedFilters}
                toolbar={toolbar}
                collapsible={false}
                pageCode="1.2.1"
                className="ps-adminlte-page ps-users-page ps-standard-list-page"
            >
                <div className="ps-table-scroll ps-users-table-wrap dragscroll1">
                    <table className="table table-bordered table-multi-select ps-users-table">
                        <thead>
                            <tr>
                                <th className="text-center ps-col-stt">STT</th>
                                <th className="text-center ps-col-id">#</th>
                                <th className="text-center no-wrap">Họ tên</th>
                                <th className="text-center no-wrap">Chức vụ</th>
                                <th className="text-center no-wrap">Mã nhân viên</th>
                                <th className="text-center no-wrap">Lương cứng</th>
                                <th className="text-center no-wrap">Số điện thoại</th>
                                <th className="text-center no-wrap">Email</th>
                                <th className="text-center no-wrap">Trưởng nhóm</th>
                                <th className="text-center no-wrap ps-col-flag">Nhận dữ liệu</th>
                                <th className="text-center no-wrap">Ca làm việc</th>
                                <th className="text-center no-wrap ps-col-flag">Đang sử dụng</th>
                                <th className="text-center no-wrap ps-col-updated">Ngày cập nhật</th>
                                <th className="text-center ps-action-col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row.id} className={row.is_locked ? 'disableRow' : ''} title={String(row.id)}>
                                    <td className="text-center">{(users.from ?? 1) + index}</td>
                                    <td className="text-center">{row.id}</td>
                                    <td className="text-center ps-user-name">
                                        {row.name} <span>({row.email?.split('@')[0]})</span>
                                    </td>
                                    <td className="text-center">{row.role_label || roleLabel(roles, row.role)}</td>
                                    <td className="text-center">{row.employee_code}</td>
                                    <td className="text-right">{row.base_salary ? formatCurrency(row.base_salary) : ''}</td>
                                    <td className="text-center">{row.phone ?? ''}</td>
                                    <td className="text-center">{row.email}</td>
                                    <td className="text-center">
                                        {row.is_team_leader
                                            ? <i className="fa fa-check check1" title={row.team_name || 'Trưởng nhóm'} />
                                            : <i className="fa fa-check check0 hidden" />}
                                    </td>
                                    <td className="text-center ps-col-flag">
                                        {row.is_super_admin ? null : (
                                            <CircleStatus
                                                active={row.receive_data}
                                                disabled={!row.can_manage}
                                                title={row.receive_data ? 'Tắt nhận dữ liệu' : 'Bật nhận dữ liệu'}
                                                onClick={row.can_manage
                                                    ? () => router.patch(`/admin/users/${row.id}/operational-status`, {
                                                        receive_data: row.receive_data ? 0 : 1,
                                                    }, { preserveScroll: true })
                                                    : undefined}
                                            />
                                        )}
                                    </td>
                                    <td className="text-center">{row.work_shift || optionName(workShifts, row.work_shift_id)}</td>
                                    <td className="text-center ps-col-flag">
                                        {row.is_super_admin ? null : (
                                            <CircleStatus
                                                active={!row.is_locked}
                                                disabled={!row.can_manage}
                                                title={row.is_locked ? 'Mở khóa tài khoản' : 'Khóa tài khoản'}
                                                onClick={row.can_manage
                                                    ? () => router.patch(`/admin/users/${row.id}/operational-status`, {
                                                        is_locked: row.is_locked ? 0 : 1,
                                                    }, { preserveScroll: true })
                                                    : undefined}
                                            />
                                        )}
                                    </td>
                                    <td className="text-center ps-update-cell no-wrap">
                                        {row.updated_by && <strong>{row.updated_by}</strong>}
                                        <span>{row.updated_at}</span>
                                    </td>
                                    <td className="text-center ps-row-actions-cell no-wrap">
                                        {row.can_manage ? (
                                            <div className="ps-row-actions">
                                                <button type="button" className="btn-icon aoh" title="Cập nhật" onClick={() => setAccountDialog({ mode: 'update', user: row })}>
                                                    <i className="fa fa-edit" />
                                                </button>
                                                <button type="button" className="btn-icon aoh" title="Thay đổi mật khẩu" onClick={() => setPasswordUser(row)}>
                                                    <i className="fa fa-retweet" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn-icon aoh"
                                                    title="Xóa tài khoản"
                                                    onClick={async () => {
                                                        const ok = await ask({
                                                            description: `Bạn chắc chắn muốn xóa tài khoản ${row.name}?`,
                                                            confirmLabel: 'Xóa',
                                                            variant: 'destructive',
                                                        });
                                                        if (!ok) return;
                                                        router.delete(`/admin/users/${row.id}`, { preserveScroll: true });
                                                    }}
                                                >
                                                    <i className="fa fa-trash" />
                                                </button>
                                                <button type="button" className="btn-icon aoh" title="Thiết lập google authenticator" onClick={() => setGoogleUser(row)}>
                                                    <i className="fa fa-google-plus" />
                                                </button>
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            )) : (
                                <tr><td colSpan="14" className="text-center ps-empty">Không có tài khoản phù hợp.</td></tr>
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
            <ReceiveDataDialog
                key={receiveDialogOpen ? receiveDialogSeed : 'closed'}
                open={receiveDialogOpen}
                onClose={() => setReceiveDialogOpen(false)}
                initialAccounts={receiveDialogSeed}
            />
            <PasswordDialog user={passwordUser} onClose={() => setPasswordUser(null)} />
            <GoogleAuthDialog user={googleUser} onClose={() => setGoogleUser(null)} />
        </AppLayout>
    );
}
