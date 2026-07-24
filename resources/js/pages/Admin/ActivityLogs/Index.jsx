import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsalePager } from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function cleanPayload(payload = {}) {
    return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== '' && value !== '-1'));
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
    }));

    useEffect(() => {
        setDraft({
            action: filters?.action ?? '',
            user_id: filters?.user_id ?? '',
            subject_type: filters?.subject_type ?? '',
            search: filters?.search ?? '',
            date_from: filters?.date_from ?? '',
            date_to: filters?.date_to ?? '',
        });
    }, [filters]);

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

    const actionFilters = useMemo(() => (
        <form className="ps-activity-filter-grid" onSubmit={(event) => { event.preventDefault(); search({ page: 1 }); }}>
            <label>
                <span>{t('activity.filter_action')}</span>
                <select className="form-control input-sm" value={draft.action} onChange={(event) => setField('action', event.target.value)}>
                    <option value="">{t('activity.all')}</option>
                    {actionOptions.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                </select>
            </label>
            <label>
                <span>{t('activity.filter_user')}</span>
                <select className="form-control input-sm" value={draft.user_id} onChange={(event) => setField('user_id', event.target.value)}>
                    <option value="">{t('activity.all')}</option>
                    {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                </select>
            </label>
            <label>
                <span>{t('activity.filter_subject')}</span>
                <select className="form-control input-sm" value={draft.subject_type} onChange={(event) => setField('subject_type', event.target.value)}>
                    <option value="">{t('activity.all')}</option>
                    {subjectTypeOptions.map((opt) => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                </select>
            </label>
            <label>
                <span>{t('activity.filter_search')}</span>
                <input className="form-control input-sm" value={draft.search} onChange={(event) => setField('search', event.target.value)} placeholder="Từ khóa / nhãn / đối tượng" />
            </label>
            <label>
                <span>{t('activity.filter_date_from')}</span>
                <input className="form-control input-sm" type="date" value={draft.date_from} onChange={(event) => setField('date_from', event.target.value)} />
            </label>
            <label>
                <span>{t('activity.filter_date_to')}</span>
                <input className="form-control input-sm" type="date" value={draft.date_to} onChange={(event) => setField('date_to', event.target.value)} />
            </label>
        </form>
    ), [actionOptions, draft, subjectTypeOptions, t, users]);

    const actions = (
        <div className="ps-activity-actions">
            <button type="button" className="btn btn-sm btn-primary" onClick={() => search({ page: 1 })}>
                <i className="fa fa-search" aria-hidden="true" /> Tìm kiếm
            </button>
            <button type="button" className="btn btn-sm btn-default" onClick={reset} title="Xóa lọc">
                <i className="fa fa-refresh" aria-hidden="true" />
            </button>
        </div>
    );

    return (
        <AppLayout>
            <Head title={t('activity.title')} />
            <PushsalePageShell
                title={t('activity.title')}
                subtitle={t('activity.desc')}
                primaryFilters={actionFilters}
                actions={actions}
                className="ps-activity-log-page pushsale-page"
                data-page-code="activity-logs"
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

                <div className="ps-activity-pagination">
                    <div className="ps-activity-record-info">
                        {meta.total ?? 0} bản ghi · Trang {meta.current_page ?? 1}/{meta.last_page ?? 1}
                    </div>
                    <PushsalePager current={meta.current_page ?? 1} totalPages={meta.last_page ?? 1} onPage={(page) => search({ page })} />
                </div>
            </PushsalePageShell>
            <ActivityDetailModal selected={selected} onClose={() => setSelected(null)} t={t} />
        </AppLayout>
    );
}
