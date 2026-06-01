import { Skeleton } from '@/components/ui/skeleton';

const roleCopy = {
    admin: {
        titleWidth: 'w-40',
        subtitleWidth: 'w-[420px]',
        cards: 5,
        chartCols: 'lg:grid-cols-3',
        chartRows: 2,
        showRankings: true,
        showFunnel: true,
        showAlerts: true,
    },
    sales: {
        titleWidth: 'w-52',
        subtitleWidth: 'w-[360px]',
        cards: 4,
        chartCols: 'lg:grid-cols-3',
        chartRows: 2,
        showFunnel: true,
    },
    marketing: {
        titleWidth: 'w-56',
        subtitleWidth: 'w-[430px]',
        cards: 4,
        chartCols: 'lg:grid-cols-3',
        chartRows: 2,
        showFunnel: true,
        showRankings: true,
    },
    warehouse: {
        titleWidth: 'w-44',
        subtitleWidth: 'w-[380px]',
        cards: 4,
        chartCols: 'lg:grid-cols-3',
        chartRows: 1,
        showAlerts: true,
    },
    accounting: {
        titleWidth: 'w-52',
        subtitleWidth: 'w-[400px]',
        cards: 4,
        chartCols: 'lg:grid-cols-2',
        chartRows: 1,
        chartLayout: 'equal',
        showExtraChart: true,
        showAlerts: true,
    },
    allocator: {
        titleWidth: 'w-52',
        subtitleWidth: 'w-[420px]',
        cards: 5,
        chartCols: 'lg:grid-cols-3',
        chartRows: 2,
        showFunnel: true,
        showAlerts: true,
    },
};

function KpiSkeleton({ count }) {
    return (
        <div className={`grid gap-4 sm:grid-cols-2 ${count === 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4'}`}>
            {Array.from({ length: count }).map((_, index) => (
                <div key={index} className="rounded-xl border bg-card p-4 shadow-sm">
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-3">
                            <Skeleton className="h-4 w-28" />
                            <Skeleton className="h-8 w-24" />
                        </div>
                        <Skeleton className="size-10 rounded-lg" />
                    </div>
                    <Skeleton className="mt-4 h-3 w-36" />
                </div>
            ))}
        </div>
    );
}

function ChartSkeleton({ compact = false }) {
    return (
        <div className="rounded-xl border bg-card p-5 shadow-sm">
            <div className="space-y-2">
                <Skeleton className="h-5 w-40" />
                <Skeleton className="h-3 w-56" />
            </div>
            <div className="mt-6 flex h-48 items-end gap-3">
                {Array.from({ length: compact ? 7 : 10 }).map((_, index) => (
                    <Skeleton
                        key={index}
                        className="flex-1 rounded-t-lg"
                        style={{ height: `${35 + ((index * 17) % 55)}%` }}
                    />
                ))}
            </div>
        </div>
    );
}

function FunnelSkeleton() {
    return (
        <div className="rounded-xl border bg-card p-5 shadow-sm">
            <div className="mb-5 flex items-start justify-between gap-4">
                <div className="space-y-2">
                    <Skeleton className="h-5 w-36" />
                    <Skeleton className="h-3 w-64" />
                </div>
                <Skeleton className="h-14 w-28 rounded-xl" />
            </div>
            <div className="grid gap-3 md:grid-cols-5">
                {Array.from({ length: 5 }).map((_, index) => (
                    <div key={index} className="rounded-xl border p-4">
                        <div className="flex justify-between gap-3">
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-7 w-16" />
                            </div>
                            <Skeleton className="h-6 w-12 rounded-full" />
                        </div>
                        <Skeleton className="mt-5 h-2 w-full rounded-full" />
                        <Skeleton className="mt-3 h-3 w-24" />
                    </div>
                ))}
            </div>
        </div>
    );
}

function ListSkeleton() {
    return (
        <div className="rounded-xl border bg-card p-5 shadow-sm">
            <div className="mb-4 space-y-2">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-3 w-56" />
            </div>
            <div className="space-y-3">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="flex items-center justify-between gap-3 rounded-xl border p-3">
                        <div className="flex items-center gap-3">
                            <Skeleton className="size-8 rounded-full" />
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-32" />
                                <Skeleton className="h-3 w-24" />
                            </div>
                        </div>
                        <Skeleton className="h-5 w-24" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function DashboardSkeleton({ role = 'admin' }) {
    const config = roleCopy[role] ?? roleCopy.admin;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="max-w-2xl space-y-3">
                    <Skeleton className={`h-8 ${config.titleWidth}`} />
                    <Skeleton className={`h-4 max-w-full ${config.subtitleWidth}`} />
                </div>
                <Skeleton className="h-9 w-28 rounded-full" />
            </div>

            <KpiSkeleton count={config.cards} />

            {config.chartRows ? (
                Array.from({ length: config.chartRows }).map((_, rowIndex) => (
                    <div key={rowIndex} className={`grid gap-4 ${config.chartCols}`}>
                        {config.chartLayout === 'sidebar' ? (
                            <>
                                <div className="col-span-full space-y-4 lg:col-span-2">
                                    <ChartSkeleton />
                                    <ChartSkeleton />
                                </div>
                                <div className="col-span-full space-y-4 lg:col-span-1">
                                    <ChartSkeleton compact />
                                    <ChartSkeleton compact />
                                </div>
                            </>
                        ) : config.chartLayout === 'equal' ? (
                            <>
                                <ChartSkeleton compact />
                                <ChartSkeleton compact />
                            </>
                        ) : rowIndex === 0 ? (
                            <>
                                <div className="col-span-full lg:col-span-2">
                                    <ChartSkeleton />
                                </div>
                                <ChartSkeleton compact />
                            </>
                        ) : (
                            <>
                                <ChartSkeleton compact />
                                <div className="col-span-full lg:col-span-2">
                                    <ChartSkeleton />
                                </div>
                            </>
                        )}
                    </div>
                ))
            ) : (
                <div className={`grid gap-4 ${config.chartCols}`}>
                    <ChartSkeleton compact />
                    {config.chartCols !== 'lg:grid-cols-1' && <ChartSkeleton compact />}
                    {config.chartCols === 'lg:grid-cols-3' && <ChartSkeleton compact />}
                </div>
            )}

            {config.showExtraChart && <ChartSkeleton />}

            {config.showFunnel && <FunnelSkeleton />}

            {config.showRankings && (
                <div className="grid gap-4 xl:grid-cols-2">
                    <ListSkeleton />
                    <ListSkeleton />
                </div>
            )}

            {config.showAlerts && <ListSkeleton />}
        </div>
    );
}
