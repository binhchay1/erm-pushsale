import { Head } from '@inertiajs/react';

import { SelectBox } from '@/components/customers/CareCampaignDialogs';
import {
    CustomerReportAdvancedFilters,
    CustomerReportDateRangeInput,
    useCustomerReportFilters,
} from '@/components/customers/CustomerReportFilters';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import AppLayout from '@/layouts/AppLayout';

export default function MultidimensionalReport({
    pageTitle = 'Thống kê khách hàng đa chiều',
    routeUrl = '/admin/customers/reports/multidimensional',
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
        dimension: filters.dimension ?? 'repurchase',
    });

    return (
        <AppLayout>
            <Head title={pageTitle} />
            <PushsalePageShell
                title={pageTitle}
                pageCode="3.3.1"
                className="ps-customer-multi-report-page"
                filters={(
                    <>
                        <CustomerReportDateRangeInput
                            dateRange={dateRange}
                            setDateRange={setDateRange}
                            onBlur={onDateRangeBlur}
                        />
                        <SelectBox
                            value={draft.dimension}
                            onChange={(value) => set('dimension', value)}
                            options={filterOptions.dimensions ?? []}
                            placeholder="Chọn chiều thống kê"
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
                                <th>Chỉ số</th>
                                <th className="text-center">Số lượng</th>
                                <th className="text-center">Tỉ trọng</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length ? rows.map((row) => (
                                <tr key={`${row.index}-${row.dimension}`}>
                                    <td className="text-center">{row.index}</td>
                                    <td>{row.dimension}</td>
                                    <td className="text-center">{row.quantity}</td>
                                    <td className="text-center">{row.ratio}%</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={4} className="text-center">Không có dữ liệu trong kỳ lọc</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </PushsalePageShell>
        </AppLayout>
    );
}
