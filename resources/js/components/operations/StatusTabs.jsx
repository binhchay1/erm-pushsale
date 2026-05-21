import { cn } from '@/lib/utils';
import { useReportSearch } from '@/hooks/useReportSearch';

export function StatusTabs({ routeUrl, filters, tabs, filterKey = 'operation_stage' }) {
    const { search } = useReportSearch(routeUrl, filters);
    const active = filters[filterKey] ?? 'all';

    return (
        <div className="flex flex-wrap gap-1.5">
            {tabs?.map((tab) => (
                <button
                    key={tab.status}
                    type="button"
                    onClick={() => search({ [filterKey]: tab.status === 'all' ? null : tab.status })}
                    className={cn(
                        'rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                        active === tab.status
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background hover:bg-muted'
                    )}
                >
                    {tab.label}
                    <span className="ml-1 tabular-nums opacity-90">({tab.count})</span>
                </button>
            ))}
        </div>
    );
}
