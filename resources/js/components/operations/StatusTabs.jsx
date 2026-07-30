import { cn } from '@/lib/utils';
import { useInertiaFilters } from '@/hooks/useInertiaFilters';

export function StatusTabs({ routeUrl, filters, tabs, filterKey = 'operation_stage' }) {
    const { search } = useInertiaFilters(routeUrl, filters, { sync: false });
    const active = filters[filterKey] ?? 'all';

    return (
        <div className="pushsale-status-tabs">
            {tabs?.map((tab) => (
                <button
                    key={tab.status}
                    type="button"
                    onClick={() => search({ [filterKey]: tab.status === 'all' ? null : tab.status })}
                    className={cn(
                        'pushsale-status-tab',
                        active === tab.status ? 'is-active' : ''
                    )}
                >
                    {tab.label}
                    <span className="ml-1 tabular-nums opacity-90">({tab.count})</span>
                </button>
            ))}
        </div>
    );
}
