import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { PushsalePageFrame } from '@/pages/Pushsale/components/PushsalePageFrame';

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function teamFormPayload(team = null, fallbackType = 'marketing') {
    return {
        name: team?.name ?? '',
        type: team?.type ?? fallbackType,
        parent_id: team?.parent_id ?? '',
        leader_user_id: team?.leader_user_id ?? '',
        permissions: team?.permissions ?? {},
    };
}

function optionLabel(options, value) {
    return options.find((item) => String(item.value ?? item.id) === String(value))?.label
        ?? options.find((item) => String(item.value ?? item.id) === String(value))?.name
        ?? '';
}

function TeamDialog({ open, mode, team, types, parents, leaders, onClose }) {
    const isEdit = mode === 'edit';
    const fallbackType = types[0]?.value ?? 'marketing';
    const { data, setData, post, put, processing, errors, clearErrors, reset } = useForm(teamFormPayload(team, fallbackType));

    useEffect(() => {
        if (!open) return;
        reset();
        const next = teamFormPayload(team, fallbackType);
        Object.entries(next).forEach(([key, value]) => setData(key, value));
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, team?.id, fallbackType]);

    if (!open) return null;

    const submit = (event) => {
        event.preventDefault();
        clearErrors();

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
        };

        if (isEdit && team?.id) {
            put(`/admin/teams/${team.id}`, options);
            return;
        }

        post('/admin/teams', options);
    };

    const title = isEdit ? 'Cập nhật đội nhóm' : 'Thêm đội nhóm';

    return (
        <div className="ps-modal-backdrop ps-team-modal-backdrop" role="presentation">
            <div className="ps-modal ps-team-modal" role="dialog" aria-modal="true" aria-label={title}>
                <div className="ps-modal-header">
                    <span>{title}</span>
                    <button type="button" className="ps-modal-close" onClick={onClose} aria-label="Đóng">×</button>
                </div>
                <form onSubmit={submit}>
                    <div className="ps-modal-body ps-team-modal-body">
                        <div className="ps-team-form-grid">
                            <label className="ps-field">
                                <span>Loại nhóm <b className="text-danger">(*)</b></span>
                                <select className="form-control" value={data.type} onChange={(event) => setData('type', event.target.value)}>
                                    {types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                                </select>
                                {errors.type && <small className="text-danger">{errors.type}</small>}
                            </label>

                            <label className="ps-field">
                                <span>Mã nhóm</span>
                                <input className="form-control" value={isEdit ? team?.code ?? '' : 'Tự động sinh'} disabled />
                            </label>

                            <label className="ps-field ps-field-wide">
                                <span>Tên nhóm <b className="text-danger">(*)</b></span>
                                <input className="form-control" value={data.name} onChange={(event) => setData('name', event.target.value)} autoFocus />
                                {errors.name && <small className="text-danger">{errors.name}</small>}
                            </label>

                            <label className="ps-field">
                                <span>Trưởng nhóm</span>
                                <select className="form-control" value={data.leader_user_id ?? ''} onChange={(event) => setData('leader_user_id', event.target.value)}>
                                    <option value="">-- Chọn trưởng nhóm --</option>
                                    {leaders.map((leader) => <option key={leader.id} value={leader.id}>{leader.name}</option>)}
                                </select>
                                {errors.leader_user_id && <small className="text-danger">{errors.leader_user_id}</small>}
                            </label>

                            <label className="ps-field">
                                <span>Nhóm cha / nhóm liên kết</span>
                                <select className="form-control" value={data.parent_id ?? ''} onChange={(event) => setData('parent_id', event.target.value)}>
                                    <option value="">-- Không chọn --</option>
                                    {parents.map((parent) => <option key={parent.id} value={parent.id}>{'— '.repeat(parent.depth ?? 0)}{parent.name}</option>)}
                                </select>
                                {errors.parent_id && <small className="text-danger">{errors.parent_id}</small>}
                            </label>
                        </div>

                        <div className="ps-team-business-note">
                            <b>Liên kết nghiệp vụ:</b> đội nhóm này được dùng cho phân quyền theo trưởng nhóm, phân bổ data, dashboard marketing/sale và các báo cáo theo team. Thành viên được gán ở phần danh sách nhân viên.
                        </div>
                    </div>
                    <div className="ps-modal-footer">
                        <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Đóng</button>
                        <button type="submit" className="btn btn-primary btn-sm" disabled={processing}>
                            <i className="fa fa-save" /> {isEdit ? 'Cập nhật' : 'Thêm đội nhóm'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function TeamsIndex({ teams, filters = {}, types = [], leaders = [], parents = [] }) {
    const [form, setForm] = useState({
        type: filters.type ?? '',
        leader_id: filters.leader_id ?? '',
        search: filters.search ?? '',
    });
    const [dialog, setDialog] = useState({ open: false, mode: 'create', team: null });
    const rows = teams?.data ?? [];

    const rowsById = useMemo(() => new Map(rows.map((row) => [row.id, row])), [rows]);

    const submit = (event) => {
        event.preventDefault();
        router.get('/admin/teams', Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')), {
            preserveState: true,
            replace: true,
        });
    };

    const closeDialog = () => setDialog({ open: false, mode: 'create', team: null });
    const openCreateDialog = () => setDialog({ open: true, mode: 'create', team: null });
    const openEditDialog = (team) => setDialog({ open: true, mode: 'edit', team });

    return (
        <AppLayout>
            <Head title="Quản lý đội nhóm" />
            <PushsalePageFrame
                title="Quản lý đội nhóm"
                className="ps-teams-page ps-adminlte-page"
                data-page-code="1.2.2"
                actions={(
                    <form className="ps-header-search" onSubmit={submit}>
                        <input className="form-control" placeholder="Tên nhóm, mã nhóm, trưởng nhóm" value={form.search} onChange={(event) => setForm((old) => ({ ...old, search: event.target.value }))} />
                        <button className="btn btn-sm btn-primary" type="submit"><i className="fa fa-search" /> Tìm kiếm</button>
                    </form>
                )}
                filters={(
                    <form className="ps-filter-row ps-team-filter-row" onSubmit={submit}>
                        <select className="form-control" value={form.type} onChange={(event) => setForm((old) => ({ ...old, type: event.target.value }))}>
                            <option value="">--Kiểu nhóm--</option>
                            {types.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                        </select>
                        <select className="form-control" value={form.leader_id} onChange={(event) => setForm((old) => ({ ...old, leader_id: event.target.value }))}>
                            <option value="">-- Trưởng nhóm --</option>
                            {leaders.map((leader) => <option key={leader.id} value={leader.id}>{leader.name}</option>)}
                        </select>
                        <select className="form-control" value="" onChange={() => {}}>
                            <option value="">-- Nhóm liên kết --</option>
                        </select>
                        <button type="button" className="btn btn-success ps-team-add-btn" onClick={openCreateDialog}>
                            <i className="fa fa-plus" /> Thêm đội nhóm
                        </button>
                    </form>
                )}
            >
                <div className="ps-table-wrap ps-teams-table-wrap">
                    <table className="table table-bordered ps-source-table ps-team-table">
                        <thead>
                            <tr>
                                <th className="ps-col-stt">STT</th>
                                <th>Loại nhóm</th>
                                <th>Mã</th>
                                <th>Tên</th>
                                <th>Trưởng nhóm</th>
                                <th>Số thành viên</th>
                                <th>Thành viên</th>
                                <th>Nhóm liên kết</th>
                                <th>Cập nhật</th>
                                <th className="ps-action-col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((team, index) => (
                                <tr key={team.id}>
                                    <td className="text-center">{(teams.from ?? 1) + index}</td>
                                    <td className="text-center">{team.type_label || optionLabel(types, team.type)}</td>
                                    <td className="text-center">{team.code}</td>
                                    <td className="text-center ps-team-name-cell">{team.name}</td>
                                    <td className="text-center">
                                        <b>{team.leader_name || ''}</b>
                                        {team.leader_email && <small>({team.leader_email.split('@')[0]})</small>}
                                    </td>
                                    <td className="text-center">{team.members_count}</td>
                                    <td className="ps-members-cell">
                                        {team.members.map((member) => <span key={member.id}>{member.account}({member.name})</span>)}
                                    </td>
                                    <td className="text-center">{team.parent_name ?? ''}</td>
                                    <td className="text-center">{team.updated_at}</td>
                                    <td className="text-center ps-row-actions-cell">
                                        <span className="ps-row-actions">
                                            <button type="button" title="Cập nhật" onClick={() => openEditDialog(rowsById.get(team.id) ?? team)}><i className="fa fa-pencil-square-o" /></button>
                                            <button type="button" title="Xóa" onClick={() => window.confirm(`Xóa nhóm ${team.name}?`) && router.delete(`/admin/teams/${team.id}`, { preserveScroll: true })}><i className="fa fa-trash" /></button>
                                        </span>
                                    </td>
                                </tr>
                            )) : <tr><td colSpan="10" className="ps-empty">Không có đội nhóm phù hợp.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination
                    meta={teams}
                    routeUrl="/admin/teams"
                    filters={currentFilters()}
                    itemLabel="đội nhóm"
                />
            </PushsalePageFrame>

            <TeamDialog
                open={dialog.open}
                mode={dialog.mode}
                team={dialog.team}
                types={types}
                parents={dialog.mode === 'edit' && dialog.team?.id ? parents.filter((parent) => parent.id !== dialog.team.id) : parents}
                leaders={leaders}
                onClose={closeDialog}
            />
        </AppLayout>
    );
}
