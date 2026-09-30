import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function payloadRows(payload) {
    if (!payload || typeof payload !== 'object') return [];

    return Object.entries(payload).map(([key, value]) => ({
        label: key,
        value: value !== null && typeof value === 'object' ? JSON.stringify(value) : String(value ?? ''),
    }));
}

function TraceDetailModal({ selected, onClose, t }) {
    if (!selected) return null;

    const rows = [
        { label: t('shipping.order_trace.col_time'), value: selected.at },
        { label: t('shipping.order_trace.col_stage'), value: t(`shipping.order_trace.stage.${selected.stage}`) },
        { label: t('shipping.order_trace.col_order'), value: selected.orderCode },
        { label: t('shipping.order_trace.col_customer'), value: selected.customerName },
        { label: t('shipping.order_trace.col_phone'), value: selected.phone },
        { label: t('shipping.order_trace.col_bill'), value: selected.externalCode },
        { label: t('shipping.order_trace.col_gateway'), value: selected.gatewayOrderId },
        { label: t('shipping.order_trace.col_status'), value: selected.statusCode },
        { label: t('shipping.order_trace.col_detail'), value: selected.summary || selected.action },
    ].filter((row) => row.value);

    const rawRows = payloadRows(selected.payload);

    return (
        <>
            <div className="modal-backdrop fade in ps-activity-modal-backdrop" onClick={onClose} />
            <div className="modal fade modal-common in ps-activity-detail-modal" role="dialog" aria-modal="true" style={{ display: 'block' }}>
                <div className="modal-dialog modal-lg">
                    <div className="modal-content">
                        <div className="modal-header">
                            <button type="button" className="close" aria-label={t('shipping.order_trace.close')} onClick={onClose}>×</button>
                            <h4 className="modal-title">{t(`shipping.order_trace.stage.${selected.stage}`)}</h4>
                        </div>
                        <div className="modal-body">
                            <section className="ps-activity-detail-section">
                                <h5>{t('shipping.order_trace.section_order')}</h5>
                                <table className="table table-bordered table-condensed ps-activity-detail-table">
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr key={row.label}>
                                                <th>{row.label}</th>
                                                <td>{row.value}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </section>
                            <section className="ps-activity-detail-section">
                                <h5>{t('shipping.order_trace.section_payload')}</h5>
                                {rawRows.length ? (
                                    <table className="table table-bordered table-condensed ps-activity-detail-table">
                                        <tbody>
                                            {rawRows.map((row) => (
                                                <tr key={row.label}>
                                                    <th>{row.label}</th>
                                                    <td>{row.value}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <div className="ps-activity-empty-detail">{t('shipping.order_trace.payload_empty')}</div>
                                )}
                            </section>
                        </div>
                        <div className="modal-footer">
                            <button type="button" className="btn btn-sm btn-default" onClick={onClose}>{t('shipping.order_trace.close')}</button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

export default function OrderTraces({ q = '', events = [] }) {
    const t = useT();
    const [term, setTerm] = useState(q);
    const [selected, setSelected] = useState(null);

    const submit = (event) => {
        event.preventDefault();
        router.get('/admin/shipping/order-traces', { q: term.trim() }, {
            preserveState: true,
            replace: true,
        });
    };

    const filters = (
        <form className="ps-sale-search-wrap ps-order-trace-search" onSubmit={submit}>
            <input
                className="form-control input-sm"
                value={term}
                placeholder={t('shipping.order_trace.search_placeholder')}
                aria-label={t('shipping.order_trace.search_placeholder')}
                onChange={(event) => setTerm(event.target.value)}
            />
            <PushsaleSearchButton type="submit" label={t('shipping.order_trace.search')} />
        </form>
    );

    return (
        <AppLayout>
            <Head title={t('shipping.order_trace.title')} />
            <PushsalePageShell
                title={t('shipping.order_trace.title')}
                filters={filters}
                pageCode="10.1.6"
                className="ps-order-trace-page pushsale-page"
                collapsible={false}
            >
                <div className="ps-table-scroll">
                    <table className="table table-bordered table-condensed ps-order-trace-table">
                        <thead>
                            <tr>
                                <th>{t('shipping.order_trace.col_time')}</th>
                                <th>{t('shipping.order_trace.col_stage')}</th>
                                <th>{t('shipping.order_trace.col_order')}</th>
                                <th>{t('shipping.order_trace.col_customer')}</th>
                                <th>{t('shipping.order_trace.col_phone')}</th>
                                <th>{t('shipping.order_trace.col_bill')}</th>
                                <th>{t('shipping.order_trace.col_status')}</th>
                                <th>{t('shipping.order_trace.col_detail')}</th>
                                <th>{t('activity.view_detail')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.length ? events.map((row) => (
                                <tr key={row.id} onDoubleClick={() => setSelected(row)}>
                                    <td className="nowrap">{row.at || '—'}</td>
                                    <td>{t(`shipping.order_trace.stage.${row.stage}`)}</td>
                                    <td>{row.orderCode || '—'}</td>
                                    <td>{row.customerName || '—'}</td>
                                    <td>{row.phone || '—'}</td>
                                    <td>{row.externalCode || row.gatewayOrderId || '—'}</td>
                                    <td>{row.statusCode || '—'}</td>
                                    <td className="ps-order-trace-detail">{row.summary || row.action || '—'}</td>
                                    <td className="ps-activity-action-btn-cell">
                                        <button type="button" className="btn btn-xs btn-default" onClick={() => setSelected(row)}>
                                            <Eye className="size-3" /> {t('activity.view_detail')}
                                        </button>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={9} className="ps-empty">
                                        {q ? t('shipping.order_trace.empty_result') : t('shipping.order_trace.empty_prompt')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </PushsalePageShell>
            <TraceDetailModal selected={selected} onClose={() => setSelected(null)} t={t} />
        </AppLayout>
    );
}
