import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import { PushsaleSearchButton } from '@/components/actions/PushsaleSearchButton';
import { DateRangeFilter } from '@/components/filters/DateRangeFilter';
import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { PushsaleSelect } from '@/components/pushsale/PushsaleSelect';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatDateTime, formatNumber } from '@/lib/format';

const CONTRACT_TYPE_OPTIONS = [
    { value: 'PushSale Advance', label: 'PushSale Advance' },
    { value: 'Pushsale Basic', label: 'Pushsale Basic' },
    { value: 'Tổng đài PushCall', label: 'Tổng đài PushCall' },
    { value: 'Tổng đài OmiCall', label: 'Tổng đài OmiCall' },
    { value: 'Tổng đài EZ Call', label: 'Tổng đài EZ Call' },
    { value: 'SMS', label: 'SMS' },
    { value: 'Zalo ZNS', label: 'Zalo ZNS' },
    { value: 'Khác', label: 'Khác' },
    { value: 'Mới', label: 'Mới' },
    { value: 'Gia hạn', label: 'Gia hạn' },
    { value: 'Nâng cấp', label: 'Nâng cấp' },
];

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function visitFilters(routeUrl, next) {
    const params = Object.fromEntries(
        Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== '-1'),
    );
    router.get(routeUrl, params, { preserveState: true, preserveScroll: true, replace: true });
}

export default function SubscriptionHistory({
    schema = {},
    rows = [],
    pagination = {},
    filterOptions = {},
    routeUrl = '/admin/company/subscription-history',
    pageRuntimeError = null,
    activeMenuCode = '1.1.2',
}) {
    const initial = currentQuery();
    const [search, setSearch] = useState(initial.search ?? '');
    const [dateFrom, setDateFrom] = useState(initial.date_from ?? '');
    const [dateTo, setDateTo] = useState(initial.date_to ?? '');
    const [contractType, setContractType] = useState(initial.contract_type ?? '');

    useEffect(() => {
        const next = currentQuery();
        setSearch(next.search ?? '');
        setDateFrom(next.date_from ?? '');
        setDateTo(next.date_to ?? '');
        setContractType(next.contract_type ?? '');
    }, [rows, pagination?.current_page]);

    const contractOptions = useMemo(() => {
        const fromApi = filterOptions.contractTypes ?? filterOptions.contract_types ?? [];
        if (fromApi.length) {
            return fromApi.map((item) => ({
                value: String(item.value ?? item.id ?? item),
                label: String(item.label ?? item.name ?? item),
            }));
        }
        return CONTRACT_TYPE_OPTIONS;
    }, [filterOptions]);

    const title = schema.title || 'Lịch sử đăng ký gói dịch vụ';
    const apply = (overrides = {}) => {
        visitFilters(routeUrl, {
            search,
            date_from: dateFrom,
            date_to: dateTo,
            contract_type: contractType,
            ...overrides,
        });
    };

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <section className="ps-subscription-history-page" data-page-code="1.1.2">
                <PageHeader
                    title={title}
                    pageCode="1.1.2"
                    className="ps-subscription-history-header"
                    defaultCollapsed={false}
                    actions={(
                        <form
                            className="ps-header-search"
                            onSubmit={(event) => {
                                event.preventDefault();
                                apply({ search });
                            }}
                        >
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Mã hợp đồng"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                            <PushsaleSearchButton type="submit" label="Tìm kiếm" />
                        </form>
                    )}
                    advanced={(
                        <div className="ps-adv-filter-panel">
                            <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': 4 }}>
                                <DateRangeFilter
                                    className="ps-adv-date-cluster"
                                    from={dateFrom}
                                    to={dateTo}
                                    withTimeLabel={false}
                                    onChange={({ date_from, date_to }) => {
                                        setDateFrom(date_from);
                                        setDateTo(date_to);
                                        apply({ date_from, date_to });
                                    }}
                                />
                                <PushsaleSelect
                                    value={contractType}
                                    onChange={(value) => {
                                        setContractType(value);
                                        apply({ contract_type: value });
                                    }}
                                    options={contractOptions}
                                    placeholder="--Loại hợp đồng--"
                                />
                            </div>
                        </div>
                    )}
                />

                {pageRuntimeError ? (
                    <div className="pushsale-error-banner">
                        <i className="fa fa-exclamation-triangle" /> {pageRuntimeError}
                    </div>
                ) : null}

                <div className="ps-table-scroll">
                    <table className="table table-bordered ps-source-table">
                        <thead>
                            <tr>
                                <th className="text-center" style={{ width: 40 }}>#</th>
                                <th>Đơn vị / Mã thanh toán</th>
                                <th>Loại hợp đồng</th>
                                <th>Mô tả</th>
                                <th className="text-right">Giá trị</th>
                                <th>Ngày thanh toán</th>
                                <th className="text-center">Thời gian sử dụng (tháng)</th>
                                <th>Thời gian hết hạn</th>
                                <th>Cập nhật</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row, index) => (
                                <tr key={row._record_id ?? row.id ?? index}>
                                    <td className="text-center">{row.id ?? index + 1}</td>
                                    <td style={{ whiteSpace: 'pre-line' }}>{row.unit_payment}</td>
                                    <td>{row.contract_type || '—'}</td>
                                    <td>{row.description || '—'}</td>
                                    <td className="text-right">{formatCurrency(row.amount)}</td>
                                    <td>{row.paid_at ? formatDateTime(row.paid_at) : '—'}</td>
                                    <td className="text-center">{formatNumber(row.duration_months ?? 0)}</td>
                                    <td>{row.expires_at ? formatDateTime(row.expires_at) : '—'}</td>
                                    <td>{row.updated_at ? formatDateTime(row.updated_at) : '—'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={9} className="ps-empty text-center">Chưa có lịch sử đăng ký gói phù hợp bộ lọc.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination
                    meta={pagination}
                    routeUrl={routeUrl}
                    filters={{ search, date_from: dateFrom, date_to: dateTo, contract_type: contractType }}
                    itemLabel="hợp đồng"
                />
            </section>
        </AppLayout>
    );
}
