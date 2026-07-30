import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { CareCampaignFormDialog, SelectBox } from '@/components/customers/CareCampaignDialogs';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { ConfirmActionDialog } from '@/components/ui/ConfirmActionDialog';
import AppLayout from '@/layouts/AppLayout';
import { apiRequest } from '@/lib/api';

function toDateRangeLabel(filters) {
    const from = filters?.date_from ? String(filters.date_from).split('-').reverse().join('/') : '';
    const to = filters?.date_to ? String(filters.date_to).split('-').reverse().join('/') : '';
    if (!from && !to) return '';
    return `${from} 00:00 - ${to} 23:59`.trim();
}

function parseDateRange(value) {
    const matches = String(value ?? '').match(/(\d{1,2})\/(\d{1,2})\/(\d{4}).*?(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (!matches) return null;
    const [, fd, fm, fy, td, tm, ty] = matches;
    return {
        date_from: `${fy}-${String(fm).padStart(2, '0')}-${String(fd).padStart(2, '0')}`,
        date_to: `${ty}-${String(tm).padStart(2, '0')}-${String(td).padStart(2, '0')}`,
    };
}

function cleanPayload(values) {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

export default function CareCampaigns({
    pageTitle = 'Quản lý chiến dịch chăm sóc',
    routeUrl = '/admin/customers/care-campaigns',
    filters = {},
    filterOptions = {},
    rows = [],
    pagination = {},
}) {
    const [draft, setDraft] = useState({
        status: filters.status ?? '',
        search: filters.search ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        per_page: filters.per_page ?? 20,
    });
    const [dateRange, setDateRange] = useState(toDateRangeLabel(filters));
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(null);

    const queryFilters = useMemo(() => {
        const parsed = parseDateRange(dateRange);
        return cleanPayload({ ...draft, ...(parsed ?? {}) });
    }, [draft, dateRange]);

    const search = () => {
        router.get(routeUrl, { ...queryFilters, page: 1 }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const saveCampaign = async (form) => {
        if (!String(form.name ?? '').trim()) {
            toast.warning('Nhập tên chiến dịch');
            return;
        }
        setProcessing(true);
        try {
            const payload = {
                name: form.name.trim(),
                starts_at: form.starts_at || null,
                ends_at: form.ends_at || null,
                status: form.status || 'active',
                repeat_days: Number(form.repeat_days || 0),
                customer_condition: { filters: form.filters ?? {} },
            };
            if (editing?.id) {
                await apiRequest(`${routeUrl}/records/${editing.id}`, { method: 'PUT', body: payload });
                toast.success('Đã cập nhật chiến dịch');
            } else {
                await apiRequest(`${routeUrl}/records`, { method: 'POST', body: payload });
                toast.success('Đã tạo chiến dịch');
            }
            setDialogOpen(false);
            setEditing(null);
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(error.message ?? 'Không lưu được chiến dịch');
        } finally {
            setProcessing(false);
        }
    };

    const removeCampaign = async () => {
        if (!confirmDelete?.id) return;
        setProcessing(true);
        try {
            await apiRequest(`${routeUrl}/records/${confirmDelete.id}`, { method: 'DELETE' });
            toast.success('Đã xóa chiến dịch');
            setConfirmDelete(null);
            router.reload({ preserveScroll: true });
        } catch (error) {
            toast.error(error.message ?? 'Không xóa được chiến dịch');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <PushsalePageShell
                title={pageTitle}
                pageCode="3.2"
                className="ps-care-campaigns-page"
                filters={(
                    <>
                        <SelectBox
                            value={draft.status}
                            onChange={(value) => setDraft((c) => ({ ...c, status: value }))}
                            options={filterOptions.statuses ?? []}
                            placeholder="-- Trạng thái --"
                        />
                        <input
                            className="form-control date-range"
                            value={dateRange}
                            placeholder="Từ ngày - Đến ngày"
                            onChange={(event) => setDateRange(event.target.value)}
                            onBlur={() => {
                                const parsed = parseDateRange(dateRange);
                                if (parsed) setDraft((c) => ({ ...c, ...parsed }));
                            }}
                        />
                        <input
                            className="form-control"
                            value={draft.search}
                            placeholder="Tên chiến dịch"
                            onChange={(event) => setDraft((c) => ({ ...c, search: event.target.value }))}
                            onKeyDown={(event) => event.key === 'Enter' && search()}
                        />
                    </>
                )}
                actions={(
                    <button type="button" className="btn btn-sm btn-primary" onClick={search}>
                        <i className="fa fa-search" /> Tìm kiếm
                    </button>
                )}
                toolbar={(
                    <button
                        type="button"
                        className="btn btn-sm btn-primary"
                        onClick={() => {
                            setEditing(null);
                            setDialogOpen(true);
                        }}
                    >
                        <i className="fa fa-plus" /> Thêm chiến dịch
                    </button>
                )}
                collapsible={false}
            >
                <div className="ps-care-campaigns-table-wrap">
                    <table className="table table-bordered table-striped ps-care-campaigns-table">
                        <thead>
                            <tr>
                                <th className="text-center">STT</th>
                                <th>Tên chiến dịch</th>
                                <th>Điều kiện khách hàng</th>
                                <th className="text-center">Số ngày lặp lại</th>
                                <th className="text-center">Ngày bắt đầu</th>
                                <th className="text-center">Ngày kết thúc</th>
                                <th className="text-center">Trạng thái</th>
                                <th className="text-center">Cập nhật</th>
                                <th className="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row.id}>
                                    <td className="text-center">{row.index}</td>
                                    <td>{row.name}</td>
                                    <td>{row.customer_condition}</td>
                                    <td className="text-center">{row.repeat_days}</td>
                                    <td className="text-center">{row.starts_at ?? '—'}</td>
                                    <td className="text-center">{row.ends_at ?? '—'}</td>
                                    <td className="text-center">{row.status_label}</td>
                                    <td className="text-center">{row.updated_at ?? '—'}</td>
                                    <td className="text-center ps-row-actions">
                                        <button
                                            type="button"
                                            className="btn-icon"
                                            title="Sửa"
                                            onClick={() => {
                                                setEditing({
                                                    id: row.id,
                                                    name: row.name,
                                                    starts_at: row.starts_at_iso ?? '',
                                                    ends_at: row.ends_at_iso ?? '',
                                                    status: row.status,
                                                    repeat_days: row.repeat_days,
                                                    filters: row.customer_condition_raw?.filters ?? {},
                                                });
                                                setDialogOpen(true);
                                            }}
                                        >
                                            <i className="fa fa-pencil" />
                                        </button>
                                        <button type="button" className="btn-icon" title="Xóa" onClick={() => setConfirmDelete(row)}>
                                            <i className="fa fa-trash" />
                                        </button>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={9} className="text-center">Chưa có chiến dịch chăm sóc</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <PushsalePagination routeUrl={routeUrl} filters={queryFilters} meta={pagination} />
            </PushsalePageShell>

            <CareCampaignFormDialog
                open={dialogOpen}
                onClose={() => {
                    setDialogOpen(false);
                    setEditing(null);
                }}
                onSave={saveCampaign}
                title={editing ? 'Cập nhật chiến dịch chăm sóc' : 'Thêm mới chiến dịch chăm sóc'}
                initial={editing ?? {}}
                filterOptions={filterOptions}
                processing={processing}
            />

            <ConfirmActionDialog
                open={Boolean(confirmDelete)}
                title="Xóa chiến dịch"
                description={`Xóa chiến dịch "${confirmDelete?.name ?? ''}"?`}
                processing={processing}
                onCancel={() => setConfirmDelete(null)}
                onConfirm={removeCampaign}
            />
        </AppLayout>
    );
}
