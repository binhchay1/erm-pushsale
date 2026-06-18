import { useLabels } from '@/hooks/use-labels';
import { cn } from '@/lib/utils';

const styles = {
    waitingDelivery: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    cancelWaybill: 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300',
    delivering: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    delivered: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    paid: 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300',
    returned: 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
};

const statusKeys = {
    waitingDelivery: 'waiting_waybill',
    cancelWaybill: 'cancel_waybill',
    delivering: 'delivering',
    delivered: 'delivered',
    paid: 'paid',
    returned: 'returned',
};

export function StatusSummaryBar({ summary }) {
    const labels = useLabels();

    return (
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            {Object.entries(statusKeys).map(([key, statusKey]) => {
                const label =
                    labels.delivery_status?.[statusKey] ??
                    labels.delivery_status_short?.[statusKey] ??
                    key;

                return (
                    <div
                        key={key}
                        className={cn(
                            'rounded-lg px-4 py-3 text-center text-sm font-semibold shadow-sm',
                            styles[key]
                        )}
                    >
                        <div>{label}</div>
                        <div className="text-2xl font-bold tabular-nums">{summary?.[key] ?? 0}</div>
                    </div>
                );
            })}
        </div>
    );
}
