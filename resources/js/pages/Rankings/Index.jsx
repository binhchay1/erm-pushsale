import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { RankingFilterBar } from '@/components/rankings/RankingFilterBar';
import { RevenueRankingChart } from '@/components/rankings/RevenueRankingChart';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export default function RankingsIndex({
    routeUrl,
    showDepartmentTabs = true,
    highlightUserId,
    myRank,
    filters,
    filterOptions,
    periods,
    departments,
}) {
    const t = useT();
    const deptList = departments ?? [];
    const showAllDepartments = showDepartmentTabs && deptList.length > 1;

    return (
        <AppLayout>
            <Head title={t('rankings.title')} />

            <div className="ps-role-rankings-page space-y-6 overflow-x-hidden px-0 sm:px-0">
                <PageHeader title={t('rankings.title')} description={t('rankings.desc')} />

                <RankingFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    periods={periods}
                />

                {myRank && (
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">{t('rankings.my_rank')}</CardTitle>
                            <CardDescription>{t('rankings.my_rank_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-6 text-sm">
                            <div>
                                <p className="text-2xl font-bold tabular-nums text-primary">#{myRank.rank}</p>
                                <p className="text-muted-foreground">{t('rankings.current_rank')}</p>
                            </div>
                            <div>
                                <p className="font-semibold tabular-nums">{formatCurrency(myRank.revenue)}</p>
                                <p className="text-muted-foreground">{t('rankings.closed_revenue')}</p>
                            </div>
                            <div>
                                <p className="font-semibold tabular-nums">{formatNumber(myRank.orders)}</p>
                                <p className="text-muted-foreground">{t('rankings.closed_orders')}</p>
                            </div>
                            <div>
                                <p className="font-semibold tabular-nums">
                                    {formatCurrency(myRank.avgOrderValue)}
                                </p>
                                <p className="text-muted-foreground">{t('rankings.avg_order')}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className={showAllDepartments ? 'grid gap-8 xl:grid-cols-1' : 'space-y-0'}>
                    {deptList.map((dept) => (
                        <Card key={dept.key} className="overflow-hidden">
                            <CardHeader className="border-b border-border/60 bg-muted/20 pb-4">
                                <CardTitle className="text-lg">{dept.label}</CardTitle>
                                <CardDescription>
                                    {t('rankings.chart_desc', { count: dept.chartItems?.length ?? 0 })}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="pt-6">
                                <RevenueRankingChart
                                    chartItems={dept.chartItems ?? dept.items}
                                    tableItems={dept.items}
                                    highlightUserId={highlightUserId}
                                    departmentLabel={dept.label}
                                    compactTable={showAllDepartments}
                                />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
