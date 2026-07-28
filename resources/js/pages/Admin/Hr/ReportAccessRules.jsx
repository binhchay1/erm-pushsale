import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';

const TEAM_TYPES = [
    { value: 'sale', label: 'Nhóm sale' },
    { value: 'care', label: 'Nhóm CSKH' },
    { value: 'marketing', label: 'Nhóm Marketing' },
];

const emptyForm = {
    team_type: 'sale',
    user_id: '',
    team_ids: [],
    is_active: true,
};

function currentFilters() {
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function teamTypeLabel(value) {
    return TEAM_TYPES.find((item) => item.value === value)?.label
        ?? (value === 'warehouse' ? 'Nhóm CSKH' : value === 'all' ? 'Tất cả' : (value || ''));
}

function flattenErrors(errors = {}) {
    return Object.values(errors).flatMap((value) => (Array.isArray(value) ? value : [value])).filter(Boolean);
}

export default function ReportAccessRules({
    rows = [],
    pagination,
    routeUrl = '/admin/hr/report-access-rules',
    filterOptions = {},
}) {
    const { ask } = useConfirm();
    const params = currentFilters();
    const [filters, setFilters] = useState({
        search: params.search ?? '',
        team_type: params.team_type ?? '',
        user_id: params.user_id ?? '',
    });
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const form = useForm(emptyForm);

    const leaders = filterOptions.teamLeaders?.length
        ? filterOptions.teamLeaders
        : (filterOptions.users ?? []);
    const allTeams = filterOptions.teams ?? [];
    const saleTeams = filterOptions.saleTeams ?? allTeams;
    const marketingTeams = filterOptions.marketingTeams ?? allTeams;
    const careTeams = filterOptions.warehouseTeams?.length
        ? filterOptions.warehouseTeams
        : allTeams;

    const dialogTeams = useMemo(() => {
        if (form.data.team_type === 'sale') return saleTeams;
        if (form.data.team_type === 'marketing') return marketingTeams;
        if (form.data.team_type === 'care') return careTeams;
        return allTeams;
    }, [allTeams, careTeams, form.data.team_type, marketingTeams, saleTeams]);

    const visibleRows = useMemo(() => rows.filter((row) => {
        const formData = row._form ?? {};
        if (filters.team_type) {
            const type = formData.team_type === 'warehouse' ? 'care' : formData.team_type;
            if (String(type) !== String(filters.team_type) && String(row.team_type) !== String(filters.team_type)) return false;
        }
        if (filters.user_id && String(formData.user_id ?? '') !== String(filters.user_id)) return false;
        return true;
    }), [filters.team_type, filters.user_id, rows]);

    const fieldError = (key) => form.errors[key] ?? form.errors[`payload.${key}`] ?? '';

    const search = (event) => {
        event?.preventDefault?.();
        const query = Object.fromEntries(
            Object.entries({
                search: filters.search.trim(),
                team_type: filters.team_type,
                user_id: filters.user_id,
            }).filter(([, value]) => value !== ''),
        );
        router.get(routeUrl, query, { replace: true, preserveState: true });
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData(emptyForm);
        form.clearErrors();
        setEditorOpen(true);
    };

    const openEdit = (row) => {
        const payload = row._form ?? {};
        setEditingId(row._record_id);
        form.setData({
            team_type: payload.team_type === 'warehouse' ? 'care' : (payload.team_type || 'sale'),
            user_id: payload.user_id ? String(payload.user_id) : '',
            team_ids: Array.isArray(payload.team_ids) ? payload.team_ids.map(String) : [],
            is_active: payload.is_active !== false,
        });
        form.clearErrors();
        setEditorOpen(true);
    };

    const toggleTeam = (teamId) => {
        const id = String(teamId);
        const current = form.data.team_ids.map(String);
        form.setData(
            'team_ids',
            current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
        );
    };

    const validateClient = () => {
        const next = {};
        if (!form.data.team_type) next.team_type = 'Kiểu nhóm bắt buộc.';
        if (!form.data.user_id) next.user_id = 'Trưởng nhóm bắt buộc.';
        if (!form.data.team_ids?.length) next.team_ids = 'Danh sách nhóm xem báo cáo bắt buộc.';
        return next;
    };

    const save = (event) => {
        event.preventDefault();
        form.clearErrors();
        const clientErrors = validateClient();
        if (Object.keys(clientErrors).length) {
            Object.entries(clientErrors).forEach(([key, message]) => form.setError(key, message));
            toast.error('Vui lòng nhập đầy đủ các trường bắt buộc.');
            return;
        }

        const payload = {
            team_type: form.data.team_type,
            user_id: Number(form.data.user_id),
            team_ids: form.data.team_ids.map(Number),
            is_active: Boolean(form.data.is_active),
        };

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setEditorOpen(false);
            },
            onError: (errors) => {
                Object.entries(errors ?? {}).forEach(([key, value]) => {
                    form.setError(key.replace(/^payload\./, ''), Array.isArray(value) ? value[0] : value);
                });
                toast.error(flattenErrors(errors).join(' · ') || 'Không lưu được cấu hình.');
            },
        };

        if (editingId) router.put(`${routeUrl}/records/${editingId}`, { payload }, options);
        else router.post(`${routeUrl}/records`, { payload }, options);
    };

    const destroy = async (row) => {
        if (!row._record_id) return;
        const ok = await ask({
            title: 'Xóa cấu hình xem báo cáo',
            description: 'Bạn chắc chắn muốn xóa cấu hình xem báo cáo này? Hành động này không thể hoàn tác.',
            confirmLabel: 'Xóa',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, {
            preserveScroll: true,
            onError: () => toast.error('Không xóa được cấu hình.'),
        });
    };

    return (
        <AppLayout>
            <Head title="Cấu hình tài khoản xem báo cáo" />
            <PushsalePageShell
                title="Cấu hình tài khoản xem báo cáo"
                pageCode="1.2.5"
                className="ps-hr-config-page ps-report-access-page"
                collapsible={false}
                primaryFilters={(
                    <div className="ps-hr-config-filters">
                        <select
                            className="form-control"
                            value={filters.team_type}
                            onChange={(event) => setFilters((old) => ({ ...old, team_type: event.target.value }))}
                        >
                            <option value="">--Kiểu nhóm--</option>
                            {TEAM_TYPES.map((item) => (
                                <option key={item.value} value={item.value}>{item.label}</option>
                            ))}
                        </select>
                        <select
                            className="form-control"
                            value={filters.user_id}
                            onChange={(event) => setFilters((old) => ({ ...old, user_id: event.target.value }))}
                        >
                            <option value="">-- Trưởng nhóm --</option>
                            {leaders.map((user) => (
                                <option key={user.id} value={user.id}>{user.label ?? user.name}</option>
                            ))}
                        </select>
                    </div>
                )}
                actions={(
                    <form className="ps-hr-config-search" onSubmit={search}>
                        <input
                            className="form-control"
                            value={filters.search}
                            onChange={(event) => setFilters((old) => ({ ...old, search: event.target.value }))}
                            placeholder="Tìm tài khoản / nhóm"
                        />
                        <PushsaleSearchButton type="submit" label="Tìm kiếm" />
                    </form>
                )}
            >
                <div className="ps-hr-config-table-wrap">
                    <table className="table table-bordered ps-hr-config-table">
                        <thead>
                            <tr>
                                <th className="text-center" style={{ width: 56 }}>STT</th>
                                <th className="text-center">Tài khoản</th>
                                <th className="text-center">Nhóm được xem báo cáo</th>
                                <th className="text-center">Kiểu nhóm</th>
                                <th className="text-center" style={{ width: 140 }}>Cập nhật</th>
                                <th className="text-center ps-col-add" style={{ width: 88 }}>
                                    <button type="button" className="btn-icon ps-th-add" title="Thêm cấu hình" onClick={openCreate}>
                                        <i className="fa fa-plus" /> <span>Thêm</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibleRows.length ? visibleRows.map((row, index) => (
                                <tr key={row._record_id ?? index}>
                                    <td className="text-center">{row.index ?? ((pagination?.from ?? 1) + index)}</td>
                                    <td className="text-center" style={{ whiteSpace: 'pre-line' }}>{row.account}</td>
                                    <td>{row.visible_teams || '—'}</td>
                                    <td className="text-center">{teamTypeLabel(row.team_type)}</td>
                                    <td className="text-center no-wrap">{formatDate(row.updated_at)}</td>
                                    <td className="text-center">
                                        <div className="ps-hr-config-actions">
                                            <button type="button" className="btn-icon" title="Cập nhật" onClick={() => openEdit(row)}>
                                                <i className="fa fa-pencil" />
                                            </button>
                                            <button type="button" className="btn-icon" title="Xóa" onClick={() => destroy(row)}>
                                                <i className="fa fa-trash" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={6} className="text-center ps-hr-config-empty">Chưa có cấu hình xem báo cáo.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={currentFilters()} itemLabel="cấu hình" />
            </PushsalePageShell>

            <PushsaleDialog
                open={editorOpen}
                onOpenChange={(open) => !open && setEditorOpen(false)}
                title={editingId ? 'Cập nhật cấu hình' : 'Thêm mới cấu hình'}
                description="Cập nhật thông tin xem báo cáo"
                width="560px"
                className="ps-report-access-dialog"
                showClose
            >
                <form className="ps-report-access-form" onSubmit={save} noValidate>
                    <div className="ps-report-access-row">
                        <label>Kiểu nhóm <span className="required">(*)</span></label>
                        <div>
                            <select
                                className="form-control"
                                value={form.data.team_type}
                                onChange={(event) => form.setData({ ...form.data, team_type: event.target.value, team_ids: [] })}
                            >
                                {TEAM_TYPES.map((item) => (
                                    <option key={item.value} value={item.value}>{item.label}</option>
                                ))}
                            </select>
                            {fieldError('team_type') ? <div className="ps-report-access-error">{fieldError('team_type')}</div> : null}
                        </div>
                    </div>

                    <div className="ps-report-access-row">
                        <label>Trưởng nhóm <span className="required">(*)</span></label>
                        <div>
                            <select
                                className="form-control"
                                value={form.data.user_id}
                                onChange={(event) => form.setData('user_id', event.target.value)}
                            >
                                <option value="">-- Chọn trưởng nhóm --</option>
                                {leaders.map((user) => (
                                    <option key={user.id} value={user.id}>{user.label ?? user.name}</option>
                                ))}
                            </select>
                            {fieldError('user_id') ? <div className="ps-report-access-error">{fieldError('user_id')}</div> : null}
                        </div>
                    </div>

                    <div className="ps-report-access-row">
                        <label>Danh sách nhóm xem báo cáo <span className="required">(*)</span></label>
                        <div>
                            <select
                                className="form-control"
                                multiple
                                size={Math.min(8, Math.max(4, dialogTeams.length || 4))}
                                value={form.data.team_ids.map(String)}
                                onChange={(event) => {
                                    const selected = [...event.target.selectedOptions].map((option) => option.value);
                                    form.setData('team_ids', selected);
                                }}
                            >
                                {dialogTeams.map((team) => (
                                    <option key={team.id} value={String(team.id)} onDoubleClick={() => toggleTeam(team.id)}>
                                        {team.label ?? team.name}
                                    </option>
                                ))}
                            </select>
                            {fieldError('team_ids') ? <div className="ps-report-access-error">{fieldError('team_ids')}</div> : null}
                            {!dialogTeams.length ? <div className="ps-report-access-error">Chưa có nhóm phù hợp kiểu đã chọn.</div> : null}
                        </div>
                    </div>

                    <div className="ps-report-access-actions">
                        <button type="submit" className="btn btn-sm btn-primary" disabled={form.processing}>
                            <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-plus'}`} />{' '}
                            {editingId ? 'Cập nhật' : 'Thêm mới'}
                        </button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
