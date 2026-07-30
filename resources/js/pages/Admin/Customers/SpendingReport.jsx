import { Head } from '@inertiajs/react';

import { SelectBox } from '@/components/customers/CareCampaignDialogs';
import {
    CustomerReportAdvancedFilters,
    CustomerReportDateRangeInput,
    useCustomerReportFilters,
} from '@/components/customers/CustomerReportFilters';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';

const money = new Intl.NumberFormat('vi-VN');

export default function SpendingReport({
    pageTitle = 'Thống kê khách hàng chi trả',
    routeUrl = '/admin/customers/reports/spending',
    filters = {},
    filterOptions = {},
    rows = [],
}) {
    const {
        draft,
        set,
        dateRange,
        setDateRange,
        onDateRangeBlur,
        search,
    } = useCustomerReportFilters(routeUrl, {
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        date_type: filters.date_type ?? 'data_arrival',
        sale_id: filters.sale_id ? String(filters.sale_id) : '',
        marketer_id: filters.marketer_id ? String(filters.marketer_id) : '',
        delivery_status: filters.delivery_status ?? '',
    });

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <PushsalePageShell
                title={pageTitle}
                pageCode="3.3.2"
                className="ps-customer-spending-report-page"
                filters={(
                    <>
                        <CustomerReportDateRangeInput
                            dateRange={dateRange}
                            setDateRange={setDateRange}
                            onBlur={onDateRangeBlur}
                        />
                        <SelectBox
                            value={draft.delivery_status}
                            onChange={(value) => set('delivery_status', value)}
                            options={filterOptions.deliveryStatuses ?? []}
                            placeholder="-- Trạng thái giao hàng --"
                        />
                    </>
                )}
                actions={(
                    <button type="button" className="btn btn-sm btn-primary" onClick={() => search()}>
                        <i className="fa fa-search" /> Tìm kiếm
                    </button>
                )}
                advancedFilters={(
                    <CustomerReportAdvancedFilters
                        draft={draft}
                        set={set}
                        filterOptions={filterOptions}
                    />
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
