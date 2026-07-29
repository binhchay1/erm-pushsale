import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { SelectBox } from '@/components/customers/CareCampaignDialogs';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';

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

const money = new Intl.NumberFormat('vi-VN');

export default function SpendingReport({
    pageTitle = 'Thống kê khách hàng chi trả',
    routeUrl = '/admin/customers/reports/spending',
    filters = {},
    filterOptions = {},
    rows = [],
}) {
    const [draft, setDraft] = useState({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        date_type: filters.date_type ?? 'data_arrival',
        sale_id: filters.sale_id ? String(filters.sale_id) : '',
        marketer_id: filters.marketer_id ? String(filters.marketer_id) : '',
        delivery_status: filters.delivery_status ?? '',
    });
    const [dateRange, setDateRange] = useState(toDateRangeLabel(filters));
    const queryFilters = useMemo(() => {
        const parsed = parseDateRange(dateRange);
        return cleanPayload({ ...draft, ...(parsed ?? {}) });
    }, [draft, dateRange]);

    const search = () => {
        router.get(routeUrl, queryFilters, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <PushsalePageShell
                title={pageTitle}
                pageCode="3.3.2"
                className="ps-customer-spending-report-page"
                filters={(
                    <>
                        <input
                            className="form-control date-range"
                            value={dateRange}
                            onChange={(event) => setDateRange(event.target.value)}
                            onBlur={() => {
                                const parsed = parseDateRange(dateRange);
                                if (parsed) setDraft((c) => ({ ...c, ...parsed }));
                            }}
                        />
                        <SelectBox
                            value={draft.delivery_status}
                            onChange={(value) => setDraft((c) => ({ ...c, delivery_status: value }))}
                            options={filterOptions.deliveryStatuses ?? []}
                            placeholder="-- Trạng thái giao hàng --"
                        />
                    </>
                )}
                actions={(
                    <button type="button" className="btn btn-sm btn-primary" onClick={search}>
                        <i className="fa fa-search" /> Tìm kiếm
                    </button>
                )}
                advancedFilters={(
                    <div className="ps-adv-filter-panel">
                        <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': 2 }}>
                            <SelectBox
                                value={draft.sale_id}
                                onChange={(value) => setDraft((c) => ({ ...c, sale_id: value }))}
                                options={filterOptions.sales ?? []}
                                placeholder="--Chọn sale--"
                            />
                            <SelectBox
                                value={draft.marketer_id}
                                onChange={(value) => setDraft((c) => ({ ...c, marketer_id: value }))}
                                options={filterOptions.marketers ?? []}
                                placeholder="--Chọn marketing--"
                            />
                        </div>
                    </div>
                )}
            >
                <div className="ps-customer-report-table-wrap">
                    <table className="table table-bordered table-striped ps-customer-report-table">
                        <thead>
                            <tr>
                                <th className="text-center">STT</th>
                                <th>Loại khách</th>
                                <th>Trạng thái giao hàng</th>
                                <th className="text-center">Số lượng khách</th>
                                <th className="text-center">Phần trăm</th>
                                <th className="text-right">Doanh số</th>
                                <th>Mô tả</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={`${row.index}-${row.customer_type}-${row.delivery_status}`}>
                                    <td className="text-center">{row.index}</td>
                                    <td>{row.customer_type}</td>
                                    <td>{row.delivery_status}</td>
                                    <td className="text-center">{row.customer_count}</td>
                                    <td className="text-center">{row.ratio}%</td>
                                    <td className="text-right">{money.format(Number(row.revenue) || 0)}</td>
                                    <td>{row.description}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={7} className="text-center">Không có dữ liệu trong kỳ lọc</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </PushsalePageShell>
        </AppLayout>
    );
}
