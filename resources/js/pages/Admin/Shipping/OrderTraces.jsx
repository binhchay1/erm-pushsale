import { Head, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

function formatWhen(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}

export default function OrderTraces({ q = '', orders = [], events = [] }) {
    const t = useT();
    const [term, setTerm] = useState(q);
    const [openId, setOpenId] = useState(null);

    const submit = (event) => {
        event.preventDefault();
        router.get('/admin/shipping/order-traces', { q: term.trim() }, {
            preserveState: true,
            replace: true,
        });
    };

    const searchForm = (
        <form id="order-trace-search" className="ps-order-trace-search" onSubmit={submit}>
            <input
                className="form-control input-sm"
                value={term}
                placeholder={t('shipping.order_trace.search_placeholder')}
                onChange={(event) => setTerm(event.target.value)}
            />
            <button type="submit" className="btn btn-sm btn-primary">
                <i className="fa fa-search" />
                {' '}
                {t('shipping.order_trace.search')}
            </button>
        </form>
    );

    return (
        <AppLayout>
            <Head title={t('shipping.order_trace.title')} />
            <PushsalePageShell
                title={t('shipping.order_trace.title')}
                subtitle={t('shipping.order_trace.subtitle')}
                actions={searchForm}
                pageCode="1.4.1"
                className="ps-order-trace-page pushsale-page"
            >
                {orders.length > 0 ? (
                    <div className="ps-order-trace-orders">
                        {orders.map((order) => (
                            <div className="ps-order-trace-order" key={order.id}>
                                <strong>{order.orderCode}</strong>
                                <span>{order.customerName}</span>
                                <span>{order.phone}</span>
                                <span>{order.trackingNumber || '—'}</span>
                            </div>
                        ))}
                    </div>
                ) : null}

                <div className="ps-order-trace-table-wrap">
                    <table className="table table-bordered table-condensed ps-order-trace-table">
                        <thead>
                            <tr>
                                <th>{t('shipping.order_trace.col_time')}</th>
                                <th>{t('shipping.order_trace.col_stage')}</th>
                                <th>{t('shipping.order_trace.col_code')}</th>
                                <th>{t('shipping.order_trace.col_status')}</th>
                                <th>{t('shipping.order_trace.col_detail')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="ps-order-trace-empty">
                                        {q
                                            ? t('shipping.order_trace.empty_result')
                                            : t('shipping.order_trace.empty_prompt')}
                                    </td>
                                </tr>
                            ) : events.map((row) => {
                                const open = openId === row.id;
                                const code = row.externalCode || row.orderCode || row.gatewayOrderId || row.phone || '—';

                                return (
                                    <Fragment key={row.id}>
                                        <tr>
                                            <td className="ps-order-trace-time">{formatWhen(row.at)}</td>
                                            <td>{t(`shipping.order_trace.stage.${row.stage}`)}</td>
                                            <td>{code}</td>
                                            <td>{row.statusCode || '—'}</td>
                                            <td>
                                                <div>{row.summary || row.action || '—'}</div>
                                                {row.payload ? (
                                                    <button
                                                        type="button"
                                                        className="btn btn-xs btn-default ps-order-trace-toggle"
                                                        onClick={() => setOpenId(open ? null : row.id)}
                                                    >
                                                        {open
                                                            ? t('shipping.order_trace.hide_payload')
                                                            : t('shipping.order_trace.show_payload')}
                                                    </button>
                                                ) : null}
                                            </td>
                                        </tr>
                                        {open ? (
                                            <tr className="ps-order-trace-payload-row">
                                                <td colSpan={5}>
                                                    <pre className="ps-order-trace-payload">{JSON.stringify(row.payload, null, 2)}</pre>
                                                </td>
                                            </tr>
                                        ) : null}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </PushsalePageShell>
        </AppLayout>
    );
}
