import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function cleanPayload(payload = {}) {
    return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== '' && value !== '-1'));
}

function currentFilters() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function DetailTable({ rows = [], emptyText }) {
    if (!rows.length) {
        return <div className="ps-activity-empty-detail">{emptyText}</div>;
    }

    return (
        <table className="table table-bordered table-condensed ps-activity-detail-table">
            <tbody>
                {rows.map((row, index) => (
                    <tr key={`${row.label}-${index}`}>
                        <th>{row.label}</th>
                        <td>{row.value || '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function ActivityDetailModal({ selected, onClose, t }) {
    if (!selected) return null;

    const metaRows = selected.meta_details ?? [];
    const detailRows = selected.details ?? [];
    const rawRows = selected.raw_properties ?? [];

    return (
        <>
            <div className="modal-backdrop fade in ps-activity-modal-backdrop" onClick={onClose} />
            <div className="modal fade modal-common in ps-activity-detail-modal" role="dialog" aria-modal="true" style={{ display: 'block' }}>
                <div className="modal-dialog modal-lg">
                    <div className="modal-content">
                        <div className="modal-header">
                            <button type="button" className="close" aria-label="Đóng" onClick={onClose}>×</button>
                            <h4 className="modal-title">{selected.action_label}</h4>
                        </div>
                        <div className="modal-body">
                            <div className="ps-activity-detail-summary">
                                <div>
                                    <span>Thời gian</span>
                                    <strong>{selected.created_at || '—'}</strong>
                                </div>
                                <div>
                                    <span>Người thực hiện</span>
                                    <strong>{selected.actor_name || '—'}</strong>
                                </div>
                                <div>
                                    <span>Đối tượng</span>
                                    <strong>{selected.subject_label || selected.subject_type_label || '—'}</strong>
                                </div>
                                <div>
                                    <span>IP</span>
                                    <strong>{selected.ip_address || '—'}</strong>
                                </div>
                            </div>

                            <section className="ps-activity-detail-section">
                                <h5>Nội dung xử lý</h5>
                                <div className="ps-activity-summary-box">{selected.summary || '—'}</div>
                            </section>

                            <section className="ps-activity-detail-section">
                                <h5>Thông tin nghiệp vụ</h5>
                                <DetailTable rows={detailRows} emptyText={t('activity.detail_empty')} />
                            </section>

                            <section className="ps-activity-detail-section">
                                <h5>Thông tin kỹ thuật</h5>
                                <DetailTable rows={metaRows} emptyText="Không có thông tin kỹ thuật bổ sung." />
                            </section>

                            {rawRows.length ? (
                                <section className="ps-activity-detail-section">
                                    <h5>Dữ liệu gốc đã lưu</h5>
                                    <DetailTable rows={rawRows} emptyText="Không có dữ liệu gốc." />
                                </section>
                            ) : null}
                        </div>
                        <div className="modal-footer">
                            <button type="button" className="btn btn-sm btn-default" onClick={onClose}>Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

export default function ActivityLogsIndex({ logs, filters, actionOptions = [], subjectTypeOptions = [], users = [] }) {
    const t = useT();
    const rows = logs?.data ?? [];
    const meta = logs?.meta ?? {};
    const [selected, setSelected] = useState(null);
    const [draft, setDraft] = useState(() => ({
        action: filters?.action ?? '',
        user_id: filters?.user_id ?? '',
        subject_type: filters?.subject_type ?? '',
        search: filters?.search ?? '',
        date_from: filters?.date_from ?? '',
        date_to: filters?.date_to ?? '',
        per_page: filters?.per_page ?? meta.per_page ?? 25,
    }));

    useEffect(() => {
        setDraft({
            action: filters?.action ?? '',
            user_id: filters?.user_id ?? '',
            subject_type: filters?.subject_type ?? '',
            search: filters?.search ?? '',
            date_from: filters?.date_from ?? '',
            date_to: filters?.date_to ?? '',
            per_page: filters?.per_page ?? meta.per_page ?? 25,
        });
    }, [filters, meta.per_page]);

    const setField = (key, value) => setDraft((current) => ({ ...current, [key]: value }));

    const search = (overrides = {}) => {
        router.get('/admin/activity-logs', cleanPayload({ ...draft, ...overrides }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const reset = () => {
        router.get('/admin/activity-logs', {}, { preserveState: false, preserveScroll: false, replace: true });
    };

    const filterToolbar = useMemo(() => (
        <form
            className="ps-activity-filter-toolbar"
            onSubmit={(event) => {
                event.preventDefault();
                search({ page: 1 });
            }}
        >
            <select
                className="form-control input-sm"
                value={draft.action}
                onChange={(event) => setField('action', event.target.value)}
                aria-label={t('activity.filter_action')}
            >
                <option value="">{t('activity.filter_action')}</option>
                {actionOptions.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
            </select>
            <select
                className="form-control input-sm"
                value={draft.user_id}
                onChange={(event) => setField('user_id', event.target.value)}
                aria-label={t('activity.filter_user')}
            >
                <option value="">{t('activity.filter_user')}</option>
                {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
            </select>
            <select
                className="form-control input-sm"
                value={draft.subject_type}
                onChange={(event) => setField('subject_type', event.target.value)}
                aria-label={t('activity.filter_subject')}
            >
                <option value="">{t('activity.filter_subject')}</option>
                {subjectTypeOptions.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
            </select>
            <input
                className="form-control input-sm"
                value={draft.search}
                onChange={(event) => setField('search', event.target.value)}
                placeholder={t('activity.filter_search')}
                aria-label={t('activity.filter_search')}
            />
            <input
                className="form-control input-sm"
                type="date"
                value={draft.date_from}
                onChange={(event) => setField('date_from', event.target.value)}
                aria-label={t('activity.filter_date_from')}
            />
            <input
                className="form-control input-sm"
                type="date"
                value={draft.date_to}
                onChange={(event) => setField('date_to', event.target.value)}
                aria-label={t('activity.filter_date_to')}
            />
            <PushsaleSearchButton type="submit" label={t('common.search')} />
            <button type="button" className="btn btn-sm btn-default" onClick={reset} title="Xóa lọc">
                <i className="fa fa-refresh" aria-hidden="true" />
            </button>
        </form>
    ), [actionOptions, draft, subjectTypeOptions, t, users]);

    return (
        <AppLayout>
            <Head title={t('activity.title')} />
            <PushsalePageShell
                title={t('activity.title')}
                actions={filterToolbar}
                className="ps-activity-log-page pushsale-page"
                data-page-code="activity-logs"
                collapsible={false}
            >
                <div className="ps-table-scroll ps-activity-table-wrap">
                    <table className="table table-bordered table-striped table-condensed ps-activity-table">
                        <thead>
                            <tr>
                                <th>{t('activity.col_time')}</th>
                                <th>{t('activity.col_action')}</th>
                                <th>{t('activity.col_summary')}</th>
                                <th>{t('activity.col_actor')}</th>
                                <th>{t('activity.col_ip')}</th>
                                <th>{t('activity.view_detail')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={row.id} onDoubleClick={() => setSelected(row)}>
                                    <td className="nowrap">{row.created_at}</td>
                                    <td className="ps-activity-action-cell">{row.action_label}</td>
                                    <td className="ps-activity-content-cell">{row.summary || row.subject_label || '—'}</td>
                                    <td>{row.actor_name}</td>
                                    <td className="ps-activity-ip-cell">{row.ip_address ?? '—'}</td>
                                    <td className="ps-activity-action-btn-cell">
                                        <button type="button" className="btn btn-xs btn-default" onClick={() => setSelected(row)}>
                                            <Eye className="size-3" /> Xem chi tiết
                                        </button>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan="6" className="ps-empty">{t('activity.empty')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination
                    meta={meta}
                    routeUrl="/admin/activity-logs"
                    filters={currentFilters()}
                    itemLabel="bản ghi"
                    perPageOptions={[25, 50, 100]}
                />
            </PushsalePageShell>
            <ActivityDetailModal selected={selected} onClose={() => setSelected(null)} t={t} />
        </AppLayout>
    );
}
