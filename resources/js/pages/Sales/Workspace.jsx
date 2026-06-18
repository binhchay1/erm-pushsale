import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/providers/I18nProvider';

export default function Workspace({ filters, filterOptions, filterFields, report, operationStatusOptions }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.workspace.title')} />

            <div className="space-y-8 pb-8">
                <div className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{t('pages.workspace.title')}</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                        {t('pages.workspace.desc_detail')}
                    </p>
                </div>

                <ReportFilterBar
                    routeUrl="/sales/workspace"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle>{t('pages.workspace.pipeline')}</CardTitle>
                        <CardDescription>{t('pages.workspace.pipeline_desc')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <StatusTabs
                            routeUrl="/sales/workspace"
                            filters={filters}
                            tabs={report.statusTabs}
                        />
                        <OperationOrderTable
                            rows={report.rows}
                            enableSaleActions
                            enableCloseOrder
                            operationStatusOptions={operationStatusOptions}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
