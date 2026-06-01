import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { RankingFilterBar } from '@/components/rankings/RankingFilterBar';
import { RevenueRankingChart } from '@/components/rankings/RevenueRankingChart';
import { formatCurrency, formatNumber } from '@/lib/format';

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
    const deptList = departments ?? [];
    const showAllDepartments = showDepartmentTabs && deptList.length > 1;

    return (
        <AppLayout>
            <Head title="Bảng xếp hạng" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Bảng xếp hạng</h1>
                    <p className="text-sm text-muted-foreground">
                        Xếp hạng doanh số chốt theo kỳ — lọc theo nhóm, tác nghiệp và cách tính chiết khấu.
                    </p>
                </div>

                <RankingFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    periods={periods}
                />

                {myRank && (
                    <Card className="border-primary/30 bg-primary/5">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">Thứ hạng của bạn</CardTitle>
                            <CardDescription>Trong bộ lọc hiện tại</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-6 text-sm">
                            <div>
                                <p className="text-2xl font-bold tabular-nums text-primary">#{myRank.rank}</p>
                                <p className="text-muted-foreground">Hạng hiện tại</p>
                            </div>
                            <div>
                                <p className="font-semibold tabular-nums">{formatCurrency(myRank.revenue)}</p>
                                <p className="text-muted-foreground">Doanh số chốt</p>
                            </div>
                            <div>
                                <p className="font-semibold tabular-nums">{formatNumber(myRank.orders)}</p>
                                <p className="text-muted-foreground">Đơn chốt</p>
                            </div>
                            <div>
                                <p className="font-semibold tabular-nums">
                                    {formatCurrency(myRank.avgOrderValue)}
                                </p>
                                <p className="text-muted-foreground">TB/đơn</p>
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
                                    Top {dept.chartItems?.length ?? 0} trên biểu đồ · tối đa 50 hạng
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
