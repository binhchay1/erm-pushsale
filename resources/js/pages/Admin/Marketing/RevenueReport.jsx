import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';

export default function MarketingRevenueReport({ filters, filterOptions, report }) {
    return (
        <AppLayout>
            <Head title="BC doanh số Marketing" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Báo cáo doanh số Marketing</h1>
                    <p className="text-sm text-muted-foreground">Công thức chỉ số (1)–(19)</p>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/marketing/revenue"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <ul className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                    {report.formulaLegend?.map((f) => (
                        <li key={f.key} className="rounded-md bg-muted px-2 py-1">
                            <strong>{f.key}.</strong> {f.label}
                        </li>
                    ))}
                </ul>

                <RevenueMetricsTable
                    rows={report.rows}
                    nameKey="marketerName"
                    nameLabel="Marketing"
                />
            </div>
        </AppLayout>
    );
}
