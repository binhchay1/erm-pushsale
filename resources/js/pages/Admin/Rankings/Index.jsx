import { useState } from 'react';
import { Head, router } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { RevenueRankingBoard } from '@/components/rankings/RevenueRankingBoard';

export default function RankingsIndex({ period, periods, departments }) {
    const [activeKey, setActiveKey] = useState(departments?.[0]?.key ?? 'sales');

    const activeDept =
        departments.find((dept) => dept.key === activeKey) ?? departments[0];

    function changePeriod(nextPeriod) {
        if (nextPeriod === period) return;
        router.get(
            '/admin/rankings',
            { period: nextPeriod },
            { preserveState: true, preserveScroll: true, only: ['departments', 'period'] }
        );
    }

    return (
        <AppLayout>
            <Head title="Xếp hạng doanh thu" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Xếp hạng doanh thu</h1>
                        <p className="text-sm text-muted-foreground">
                            Top doanh số chốt theo từng ban — chỉ áp dụng cho Telesale và Marketing.
                        </p>
                    </div>

                    <div className="inline-flex rounded-lg border bg-card p-1">
                        {periods.map((item) => (
                            <Button
                                key={item.value}
                                type="button"
                                size="sm"
                                variant={item.value === period ? 'default' : 'ghost'}
                                onClick={() => changePeriod(item.value)}
                            >
                                {item.label}
                            </Button>
                        ))}
                    </div>
                </div>

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

                <Card>
                    <CardHeader>
                        <CardTitle>Bảng xếp hạng {activeDept?.label}</CardTitle>
                        <CardDescription>
                            Doanh số chốt = tổng giá trị đơn đã chốt trong kỳ, quy về từng nhân sự phụ trách.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <RevenueRankingBoard items={activeDept?.items} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
