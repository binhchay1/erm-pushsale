import { useState } from 'react';
import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
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
    const [activeKey, setActiveKey] = useState(departments?.[0]?.key ?? 'sales');

    const activeDept =
        departments.find((dept) => dept.key === activeKey) ?? departments[0];

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
                                <p className="font-semibold tabular-nums">{formatCurrency(myRank.avgOrderValue)}</p>
                                <p className="text-muted-foreground">TB/đơn</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {showDepartmentTabs && departments.length > 1 && (
                    <div className="inline-flex flex-wrap gap-2">
                        {departments.map((dept) => (
                            <button
                                key={dept.key}
                                type="button"
                                onClick={() => setActiveKey(dept.key)}
                                className={cn(
                                    'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors',
                                    dept.key === activeKey
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                                )}
                            >
                                {dept.label}
                            </button>
                        ))}
                    </div>
                )}

                <Card>
                    <CardContent className="pt-6">
                        <RevenueRankingChart
                            chartItems={activeDept?.chartItems ?? activeDept?.items}
                            tableItems={activeDept?.items}
                            highlightUserId={highlightUserId}
                            departmentLabel={activeDept?.label}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
