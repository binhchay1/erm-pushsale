import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsaleDialog } from '@/components/ui/pushsale-dialog';
import AppLayout from '@/layouts/AppLayout';
import { useConfirm } from '@/hooks/use-confirm';

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

function flattenErrors(errors = {}) {
    return Object.values(errors).flatMap((value) => (Array.isArray(value) ? value : [value])).filter(Boolean);
}

const emptyCreate = {
    care_user_id: '',
    sale_team_ids: [],
    warehouse_team_id: '',
    quota: 0,
    receive_data: true,
};

export default function CareDistributionRules({
    rows = [],
    routeUrl = '/admin/hr/care-distribution-rules',
    filterOptions = {},
}) {
    const { ask } = useConfirm();
    const params = currentFilters();
    const [filters, setFilters] = useState({
        search: params.search ?? '',
        sale_leader_id: params.sale_leader_id ?? '',
        sale_team_id: params.sale_team_id ?? '',
        warehouse_team_id: params.warehouse_team_id ?? '',
        care_user_id: params.care_user_id ?? '',
        receive_data: params.receive_data ?? '',
    });
    const [selected, setSelected] = useState(() => new Set());
    const [quotaDraft, setQuotaDraft] = useState(() => Object.fromEntries(rows.map((row) => [String(row._record_id), row.quota ?? 0])));
    const [editingRow, setEditingRow] = useState(null);
    const [creating, setCreating] = useState(false);
    const [updating, setUpdating] = useState(false);
    const createForm = useForm(emptyCreate);
    const editForm = useForm(emptyCreate);

    const saleLeaders = filterOptions.saleLeaders ?? [];
    const saleTeams = filterOptions.saleTeams ?? [];
    const warehouseTeams = filterOptions.warehouseTeams ?? [];
    const careUsers = filterOptions.careUsers ?? [];

    const visibleSaleTeams = useMemo(() => {
        if (!filters.sale_leader_id) return saleTeams;
        return saleTeams.filter((team) => String(team.leader_user_id ?? '') === String(filters.sale_leader_id));
    }, [filters.sale_leader_id, saleTeams]);

    const leaderTeamIds = useMemo(() => new Set(visibleSaleTeams.map((team) => String(team.id))), [visibleSaleTeams]);

    const filteredRows = useMemo(() => {
        const needle = filters.search.trim().toLocaleLowerCase('vi');
        return rows.filter((row) => {
            const form = row._form ?? {};
            const rowTeamIds = (form.sale_team_ids ?? []).map(String);
            if (filters.sale_leader_id && !rowTeamIds.some((id) => leaderTeamIds.has(id))) return false;
            if (filters.sale_team_id && !rowTeamIds.includes(String(filters.sale_team_id))) return false;
            if (filters.warehouse_team_id && String(form.warehouse_team_id ?? '') !== String(filters.warehouse_team_id)) return false;
            if (filters.care_user_id && String(form.care_user_id ?? '') !== String(filters.care_user_id)) return false;
            if (filters.receive_data !== '' && String(Number(Boolean(row.receive_data))) !== String(filters.receive_data)) return false;
            if (!needle) return true;
            return String(row.care_user ?? '').toLocaleLowerCase('vi').includes(needle)
                || String(row.sales_teams ?? '').toLocaleLowerCase('vi').includes(needle);
        });
    }, [filters, leaderTeamIds, rows]);

    useEffect(() => {
        setQuotaDraft((current) => {
            const next = { ...current };
            rows.forEach((row) => {
                const key = String(row._record_id);
                if (next[key] === undefined) next[key] = row.quota ?? 0;
            });
            return next;
        });
    }, [rows]);

    const search = (event) => {
        event?.preventDefault?.();
        const query = Object.fromEntries(Object.entries({
            search: filters.search.trim(),
            sale_leader_id: filters.sale_leader_id,
            sale_team_id: filters.sale_team_id,
            warehouse_team_id: filters.warehouse_team_id,
            care_user_id: filters.care_user_id,
            receive_data: filters.receive_data,
        }).filter(([, value]) => value !== ''));
        router.get(routeUrl, query, { replace: true, preserveState: true });
    };

    const toggleAll = (checked) => {
        setSelected(checked ? new Set(filteredRows.map((row) => String(row._record_id))) : new Set());
    };

    const toggleOne = (id) => {
        setSelected((current) => {
            const next = new Set(current);
            const key = String(id);
            if (next.has(key)) next.delete(key);
            else next.add(key);
            return next;
        });
    };

    const saveCreate = (event) => {
        event.preventDefault();
        if (!createForm.data.care_user_id) {
            toast.error('Vui lòng chọn tài khoản care đơn.');
            return;
        }
        if (!createForm.data.sale_team_ids.length) {
            toast.error('Vui lòng chọn ít nhất một nhóm Sales.');
            return;
        }

        router.post(`${routeUrl}/records`, {
            payload: {
                care_user_id: Number(createForm.data.care_user_id),
                sale_team_ids: createForm.data.sale_team_ids.map(Number),
                warehouse_team_id: createForm.data.warehouse_team_id ? Number(createForm.data.warehouse_team_id) : null,
                quota: Number(createForm.data.quota) || 0,
                receive_data: Boolean(createForm.data.receive_data),
            },
        }, {
            preserveScroll: true,
            onStart: () => setCreating(true),
            onFinish: () => setCreating(false),
            onSuccess: () => {
                createForm.setData(emptyCreate);
                toast.success('Đã thêm cấu hình chia số care đơn.');
            },
            onError: (errors) => toast.error(flattenErrors(errors).join(' · ') || 'Không thêm được cấu hình.'),
        });
    };

    const openEdit = (row) => {
        const payload = row._form ?? {};
        setEditingRow(row);
        editForm.setData({
            care_user_id: payload.care_user_id ? String(payload.care_user_id) : '',
            sale_team_ids: Array.isArray(payload.sale_team_ids) ? payload.sale_team_ids.map(String) : [],
            warehouse_team_id: payload.warehouse_team_id ? String(payload.warehouse_team_id) : '',
            quota: payload.quota ?? row.quota ?? 0,
            receive_data: payload.receive_data !== false,
        });
        editForm.clearErrors();
    };

    const saveEdit = (event) => {
        event.preventDefault();
        if (!editingRow?._record_id) return;
        router.put(`${routeUrl}/records/${editingRow._record_id}`, {
            payload: {
                care_user_id: Number(editForm.data.care_user_id),
                sale_team_ids: editForm.data.sale_team_ids.map(Number),
                warehouse_team_id: editForm.data.warehouse_team_id ? Number(editForm.data.warehouse_team_id) : null,
                quota: Number(editForm.data.quota) || 0,
                receive_data: Boolean(editForm.data.receive_data),
            },
        }, {
            preserveScroll: true,
            onStart: () => setUpdating(true),
            onFinish: () => setUpdating(false),
            onSuccess: () => {
                setEditingRow(null);
                toast.success('Đã cập nhật cấu hình.');
            },
            onError: (errors) => toast.error(flattenErrors(errors).join(' · ') || 'Không cập nhật được.'),
        });
    };

    const updateSelectedQuotas = async () => {
        const ids = [...selected];
        if (!ids.length) {
            toast.error('Vui lòng chọn ít nhất một dòng.');
            return;
        }
        try {
            await Promise.all(ids.map((id) => {
                const row = rows.find((item) => String(item._record_id) === String(id));
                const form = row?._form ?? {};
                return fetch(`${routeUrl}/records/${id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        payload: {
                            care_user_id: form.care_user_id,
                            sale_team_ids: form.sale_team_ids ?? [],
                            warehouse_team_id: form.warehouse_team_id || null,
                            receive_data: Boolean(row?.receive_data),
                            quota: Number(quotaDraft[id]) || 0,
                        },
                    }),
                }).then(async (response) => {
                    if (!response.ok) {
                        const body = await response.json().catch(() => ({}));
                        throw new Error(flattenErrors(body.errors).join(' ') || body.message || 'Lỗi cập nhật định mức.');
                    }
                });
            }));
            toast.success('Đã cập nhật định mức.');
            router.reload({ preserveScroll: true, only: ['rows', 'pagination'] });
        } catch (error) {
            toast.error(error.message || 'Không cập nhật được định mức.');
        }
    };

    const setReceiveDataSelected = async (receiveData) => {
        const ids = [...selected];
        if (!ids.length) {
            toast.error('Vui lòng chọn ít nhất một dòng.');
            return;
        }
        try {
            await Promise.all(ids.map((id) => {
                const row = rows.find((item) => String(item._record_id) === String(id));
                const form = row?._form ?? {};
                return fetch(`${routeUrl}/records/${id}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        payload: {
                            care_user_id: form.care_user_id,
                            sale_team_ids: form.sale_team_ids ?? [],
                            warehouse_team_id: form.warehouse_team_id || null,
                            quota: Number(quotaDraft[id] ?? row?.quota ?? 0),
                            receive_data: receiveData,
                        },
                    }),
                }).then(async (response) => {
                    if (!response.ok) throw new Error('Lỗi cập nhật nhận data.');
                });
            }));
            toast.success(receiveData ? 'Đã bật nhận data.' : 'Đã dừng nhận data.');
            router.reload({ preserveScroll: true, only: ['rows'] });
        } catch (error) {
            toast.error(error.message || 'Không cập nhật được nhận data.');
        }
    };

    const deleteSelected = async () => {
        const ids = [...selected];
        if (!ids.length) {
            toast.error('Vui lòng chọn ít nhất một dòng.');
            return;
        }
        const ok = await ask({ description: `Bạn chắc chắn muốn xóa ${ids.length} cấu hình?`, confirmLabel: 'Xóa', variant: 'destructive' });
        if (!ok) return;
        try {
            await Promise.all(ids.map((id) => fetch(`${routeUrl}/records/${id}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(async (response) => {
                if (!response.ok) throw new Error('Không xóa được.');
            })));
            setSelected(new Set());
            toast.success('Đã xóa cấu hình đã chọn.');
            router.reload({ preserveScroll: true, only: ['rows'] });
        } catch (error) {
            toast.error(error.message || 'Không xóa được một số cấu hình.');
        }
    };

    const destroyOne = async (row) => {
        if (!row._record_id) return;
        const ok = await ask({ description: 'Xóa cấu hình này?', confirmLabel: 'Xóa', variant: 'destructive' });
        if (!ok) return;
        router.delete(`${routeUrl}/records/${row._record_id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa cấu hình.'),
            onError: () => toast.error('Không xóa được cấu hình.'),
        });
    };

    return (
        <AppLayout>
            <Head title="Danh sách cấu hình chia số care đơn" />
            <PushsalePageShell
                title="Danh sách cấu hình chia số care đơn"
                pageCode="1.2.6"
                className="ps-hr-config-page ps-care-dist-page"
                collapsible={false}
                actions={(
                    <form className="ps-hr-config-search" onSubmit={search}>
                        <input
                            className="form-control"
                            value={filters.search}
                            onChange={(event) => setFilters((old) => ({ ...old, search: event.target.value }))}
                            placeholder="Nhập từ khóa"
                        />
                        <PushsaleSearchButton type="submit" label="Tìm kiếm" />
                    </form>
                )}
            >
                <div className="ps-care-dist-layout">
                    <div>
                        <div className="ps-care-dist-filters">
                            <select
                                className="form-control"
                                value={filters.sale_leader_id}
                                onChange={(event) => setFilters((old) => ({
                                    ...old,
                                    sale_leader_id: event.target.value,
                                    sale_team_id: '',
                                }))}
                            >
                                <option value="">--Trưởng nhóm Sales--</option>
                                {saleLeaders.map((user) => <option key={user.id} value={user.id}>{user.label ?? user.name}</option>)}
                            </select>
                            <select className="form-control" value={filters.sale_team_id} onChange={(event) => setFilters((old) => ({ ...old, sale_team_id: event.target.value }))}>
                                <option value="">--Nhóm Sales--</option>
                                {visibleSaleTeams.map((team) => <option key={team.id} value={team.id}>{team.label ?? team.name}</option>)}
                            </select>
                            <select className="form-control" value={filters.warehouse_team_id} onChange={(event) => setFilters((old) => ({ ...old, warehouse_team_id: event.target.value }))}>
                                <option value="">--Nhóm Vận đơn--</option>
                                {warehouseTeams.map((team) => <option key={team.id} value={team.id}>{team.label ?? team.name}</option>)}
                            </select>
                            <select className="form-control" value={filters.care_user_id} onChange={(event) => setFilters((old) => ({ ...old, care_user_id: event.target.value }))}>
                                <option value="">--TK care đơn--</option>
                                {careUsers.map((user) => <option key={user.id} value={user.id}>{user.label ?? user.name}</option>)}
                            </select>
                            <select className="form-control" value={filters.receive_data} onChange={(event) => setFilters((old) => ({ ...old, receive_data: event.target.value }))}>
                                <option value="">--TT nhận dữ liệu--</option>
                                <option value="1">Có nhận dữ liệu</option>
                                <option value="0">Không nhận dữ liệu</option>
                            </select>
                        </div>

                        <div className="ps-hr-config-table-wrap">
                            <table className="table table-bordered table-multi-select ps-hr-config-table">
                                <thead>
                                    <tr>
                                        <th className="text-center" style={{ width: 52 }}>
                                            <input
                                                type="checkbox"
                                                checked={filteredRows.length > 0 && filteredRows.every((row) => selected.has(String(row._record_id)))}
                                                onChange={(event) => toggleAll(event.target.checked)}
                                            />
                                        </th>
                                        <th className="text-left">User care đơn</th>
                                        <th className="text-center" style={{ width: 100 }}>Định mức</th>
                                        <th className="text-center" style={{ width: 90 }}>Nhận data</th>
                                        <th className="text-center">Nhóm Sales</th>
                                        <th className="text-center" style={{ width: 130 }}>Cập nhật</th>
                                        <th className="text-center" style={{ width: 80 }}>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredRows.length ? filteredRows.map((row) => (
                                        <tr key={row._record_id}>
                                            <td className="text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={selected.has(String(row._record_id))}
                                                    onChange={() => toggleOne(row._record_id)}
                                                />
                                            </td>
                                            <td style={{ whiteSpace: 'pre-line' }}>{row.care_user}</td>
                                            <td className="text-center">
                                                <input
                                                    className="form-control ps-care-quota-input"
                                                    type="number"
                                                    min="0"
                                                    value={quotaDraft[String(row._record_id)] ?? row.quota ?? 0}
                                                    onChange={(event) => setQuotaDraft((old) => ({ ...old, [String(row._record_id)]: event.target.value }))}
                                                />
                                            </td>
                                            <td className="text-center">
                                                {row.receive_data
                                                    ? <i className="fa fa-check-circle text-green" title="Có nhận data" />
                                                    : <i className="fa fa-circle-o" title="Không nhận data" />}
                                            </td>
                                            <td>{row.sales_teams || '—'}</td>
                                            <td className="text-center no-wrap">{formatDate(row.updated_at)}</td>
                                            <td className="text-center">
                                                <div className="ps-hr-config-actions">
                                                    <button type="button" className="btn-icon" title="Cập nhật" onClick={() => openEdit(row)}>
                                                        <i className="fa fa-pencil" />
                                                    </button>
                                                    <button type="button" className="btn-icon" title="Xóa" onClick={() => destroyOne(row)}>
                                                        <i className="fa fa-trash" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={7} className="text-center ps-hr-config-empty">Chưa có cấu hình chia số care đơn.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="ps-care-dist-bulk">
                            <button type="button" className="btn btn-sm btn-primary" onClick={updateSelectedQuotas}>
                                <i className="fa fa-save" /> Cập nhật định mức
                            </button>
                            <button type="button" className="btn btn-sm btn-danger" onClick={deleteSelected}>
                                <i className="fa fa-trash" /> Xóa
                            </button>
                        </div>
                    </div>

                    <div className="ps-care-create-panel">
                        <form onSubmit={saveCreate}>
                            <table className="table table-bordered table-line">
                                <tbody>
                                    <tr>
                                        <td>Tài khoản care đơn <span className="text-red">(*)</span></td>
                                        <td>
                                            <select
                                                className="form-control"
                                                value={createForm.data.care_user_id}
                                                onChange={(event) => createForm.setData('care_user_id', event.target.value)}
                                            >
                                                <option value="">-- Chọn tài khoản --</option>
                                                {careUsers.map((user) => (
                                                    <option key={user.id} value={user.id}>{user.label ?? user.name}</option>
                                                ))}
                                            </select>
                                            <div className="ps-care-create-links">
                                                <button type="button" className="linkish" onClick={() => setReceiveDataSelected(true)}>
                                                    <i className="fa fa-refresh" /> Nhận data
                                                </button>
                                                <button type="button" className="linkish" onClick={() => setReceiveDataSelected(false)}>
                                                    <i className="fa fa-refresh" /> Dừng nhận data
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Nhóm sales <span className="text-red">(*)</span></td>
                                        <td>
                                            <select
                                                className="form-control"
                                                multiple
                                                size={Math.min(6, Math.max(4, saleTeams.length || 4))}
                                                value={createForm.data.sale_team_ids.map(String)}
                                                onChange={(event) => createForm.setData('sale_team_ids', [...event.target.selectedOptions].map((option) => option.value))}
                                            >
                                                {saleTeams.map((team) => (
                                                    <option key={team.id} value={String(team.id)}>{team.label ?? team.name}</option>
                                                ))}
                                            </select>
                                            <div className="ps-care-create-links" style={{ flexDirection: 'row', gap: 16, marginTop: 8 }}>
                                                <button type="button" className="linkish" onClick={() => createForm.setData('sale_team_ids', saleTeams.map((team) => String(team.id)))}>
                                                    Chọn toàn bộ
                                                </button>
                                                <button type="button" className="linkish" onClick={() => createForm.setData('sale_team_ids', [])}>
                                                    Xóa toàn bộ
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Nhóm vận đơn</td>
                                        <td>
                                            <select
                                                className="form-control"
                                                value={createForm.data.warehouse_team_id}
                                                onChange={(event) => createForm.setData('warehouse_team_id', event.target.value)}
                                            >
                                                <option value="">--Nhóm Vận đơn--</option>
                                                {warehouseTeams.map((team) => (
                                                    <option key={team.id} value={team.id}>{team.label ?? team.name}</option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Định mức chia số</td>
                                        <td>
                                            <input
                                                className="form-control"
                                                type="number"
                                                min="0"
                                                value={createForm.data.quota}
                                                onChange={(event) => createForm.setData('quota', event.target.value)}
                                            />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Nhận data</td>
                                        <td>
                                            <label style={{ fontWeight: 400, margin: 0 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(createForm.data.receive_data)}
                                                    onChange={(event) => createForm.setData('receive_data', event.target.checked)}
                                                />{' '}
                                                Có nhận dữ liệu
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td />
                                        <td>
                                            <button type="submit" className="btn btn-sm btn-primary" disabled={creating}>
                                                <i className={`fa ${creating ? 'fa-spinner fa-spin' : 'fa-plus'}`} /> Thêm mới
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    </div>
                </div>
            </PushsalePageShell>

            <PushsaleDialog
                open={Boolean(editingRow)}
                onOpenChange={(open) => !open && setEditingRow(null)}
                title="Cập nhật cấu hình chia số care đơn"
                width="560px"
            >
                <form className="ps-report-access-form" onSubmit={saveEdit}>
                    <div className="ps-report-access-row">
                        <label>Tài khoản care đơn <span className="required">(*)</span></label>
                        <select className="form-control" value={editForm.data.care_user_id} onChange={(event) => editForm.setData('care_user_id', event.target.value)}>
                            <option value="">-- Chọn --</option>
                            {careUsers.map((user) => <option key={user.id} value={user.id}>{user.label ?? user.name}</option>)}
                        </select>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Nhóm Sales <span className="required">(*)</span></label>
                        <select
                            className="form-control"
                            multiple
                            size={5}
                            value={editForm.data.sale_team_ids.map(String)}
                            onChange={(event) => editForm.setData('sale_team_ids', [...event.target.selectedOptions].map((option) => option.value))}
                        >
                            {saleTeams.map((team) => <option key={team.id} value={String(team.id)}>{team.label ?? team.name}</option>)}
                        </select>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Nhóm vận đơn</label>
                        <select className="form-control" value={editForm.data.warehouse_team_id} onChange={(event) => editForm.setData('warehouse_team_id', event.target.value)}>
                            <option value="">--Nhóm Vận đơn--</option>
                            {warehouseTeams.map((team) => <option key={team.id} value={team.id}>{team.label ?? team.name}</option>)}
                        </select>
                    </div>
                    <div className="ps-report-access-row">
                        <label>Định mức</label>
                        <input className="form-control" type="number" min="0" value={editForm.data.quota} onChange={(event) => editForm.setData('quota', event.target.value)} />
                    </div>
                    <div className="ps-report-access-row">
                        <label>Nhận data</label>
                        <label style={{ fontWeight: 400, margin: 0 }}>
                            <input type="checkbox" checked={Boolean(editForm.data.receive_data)} onChange={(event) => editForm.setData('receive_data', event.target.checked)} /> Có nhận dữ liệu
                        </label>
                    </div>
                    <div className="ps-report-access-actions">
                        <button type="submit" className="btn btn-sm btn-primary" disabled={updating}>
                            <i className="fa fa-save" /> Cập nhật
                        </button>
                    </div>
                </form>
            </PushsaleDialog>
        </AppLayout>
    );
}
